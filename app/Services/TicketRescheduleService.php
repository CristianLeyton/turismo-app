<?php

namespace App\Services;

use App\Models\Seat;
use App\Models\SeatReservation;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDateChange;
use App\Models\Trip;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Reprogramación de boletos: mueve un ticket (ida y/o vuelta) a otro Trip
 * del mismo origen→destino, con otra fecha/horario y otro asiento.
 *
 * Reglas clave:
 * - Nunca borrar/recrear el ticket: solo UPDATE de trip_id / seat_id (conserva N° de boleto).
 * - En el viaje destino el asiento se valida como cualquier venta nueva.
 * - Todo dentro de una transacción con lockForUpdate de los viajes destino.
 * - Auditoría: una fila por tramo realmente movido.
 *
 * Estructura de un ida y vuelta: DOS tickets del mismo sale+passenger.
 *  - Boleto de ida: is_round_trip=true, return_trip_id=viaje de vuelta, price>0.
 *  - Boleto de vuelta: is_round_trip=true, return_trip_id=NULL, price=0,
 *    con origin/destination ya en dirección regreso.
 */
class TicketRescheduleService
{
    public const SCOPES = ['outbound', 'both'];

    /**
     * Si el parámetro de configuración lo exige, verifica la contraseña del
     * admin logueado antes de permitir la reprogramación.
     *
     * @throws ValidationException
     */
    protected function assertPasswordConfirmed(?string $password): void
    {
        if (! Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE)) {
            return;
        }

        if (! Hash::check((string) $password, Auth::user()?->password ?? '')) {
            throw ValidationException::withMessages([
                'confirm_password' => 'La contraseña no es correcta.',
            ]);
        }
    }

    /**
     * Reprogramar un boleto.
     *
     * @param  Ticket  $ticket  Boleto a mover (ida, o el tramo de vuelta del pasajero)
     * @param  array{scope: string, date: string, schedule_id: int, seat_id: int, return_date?: ?string, return_schedule_id?: ?int, return_seat_id?: ?int}  $data
     * @return array{ticket: Ticket, changes: array<int, array<string, mixed>>, moved_return: bool}
     *
     * @throws ValidationException
     */
    public function reschedule(Ticket $ticket, array $data): array
    {
        $this->validateAdmin();

        // Confirmación de contraseña (si el parámetro está activo) antes de
        // cualquier mutación o reserva: defensa server-side además del form.
        $this->assertPasswordConfirmed($data['confirm_password'] ?? null);

        $this->assertTicketIsReschedulable($ticket);

        $scope = $data['scope'] ?? 'outbound';
        if (! in_array($scope, self::SCOPES, true)) {
            throw ValidationException::withMessages(['scope' => 'Alcance de reprogramación inválido.']);
        }

        $isRoundTrip = (bool) $ticket->is_round_trip;

        if ($scope === 'both' && ! $isRoundTrip) {
            throw ValidationException::withMessages(['scope' => 'Este boleto no es de ida y vuelta.']);
        }

        // Boleto de vuelta (is_round_trip=true, return_trip_id=null, price=0):
        // solo se puede mover el propio tramo, no "ambos".
        $isReturnLegTicket = $isRoundTrip && is_null($ticket->return_trip_id);
        if ($isReturnLegTicket) {
            $scope = 'outbound'; // mueve únicamente este registro
        }

        // ---- Resolución de viajes destino ----
        $newOutboundTrip = null;
        $newOutboundSeat = null;

        if (! $isReturnLegTicket) {
            $this->validateScheduleForSegment(
                $data['schedule_id'] ?? null,
                (int) $ticket->origin_location_id,
                (int) $ticket->destination_location_id,
                'schedule_id',
                $data['date'] ?? null,
            );

            $newOutboundTrip = Trip::findOrCreateForBooking(
                (int) $data['schedule_id'],
                $data['date'],
                (int) $ticket->origin_location_id,
                (int) $ticket->destination_location_id,
            )['trip'] ?? null;

            if (! $newOutboundTrip) {
                throw ValidationException::withMessages(['schedule_id' => 'No se pudo resolver el viaje de ida para la fecha y horario elegidos.']);
            }

            // El boleto ocupa asiento: el nuevo tramo también debe tenerlo.
            if ($ticket->occupiesSeat() && blank($data['seat_id'] ?? null)) {
                throw ValidationException::withMessages(['seat_id' => 'Elegí el nuevo asiento de ida antes de confirmar.']);
            }

            $newOutboundSeat = $this->resolveSeat($data['seat_id'] ?? null, $newOutboundTrip, 'seat_id');
        }

        // Vuelta: si el boleto a mover ES el tramo de vuelta, sus origin/destination
        // ya están en dirección regreso; si es scope "both" desde el boleto de ida, se invierten.
        $newReturnTrip = null;
        $newReturnSeat = null;
        $hasReturnMove = $scope === 'both' || $isReturnLegTicket;

        $returnOriginId = $isReturnLegTicket
            ? (int) $ticket->origin_location_id
            : (int) $ticket->destination_location_id;
        $returnDestinationId = $isReturnLegTicket
            ? (int) $ticket->destination_location_id
            : (int) $ticket->origin_location_id;

        if ($hasReturnMove) {
            // Cuando el boleto a mover ES el tramo de vuelta, fecha/horario/asiento llegan en los campos principales.
            $returnScheduleId = $isReturnLegTicket ? ($data['schedule_id'] ?? null) : ($data['return_schedule_id'] ?? null);
            $returnDate = $isReturnLegTicket ? ($data['date'] ?? null) : ($data['return_date'] ?? null);
            $returnSeatId = $isReturnLegTicket ? ($data['seat_id'] ?? null) : ($data['return_seat_id'] ?? null);

            if (blank($returnScheduleId) || blank($returnDate)) {
                throw ValidationException::withMessages(['return_schedule_id' => 'Elegí fecha y horario de vuelta.']);
            }

            // El tramo de vuelta ocupa asiento: exigir asiento nuevo.
            $returnLeg = $isReturnLegTicket
                ? $ticket
                : Ticket::query()
                    ->where('sale_id', $ticket->sale_id)
                    ->where('passenger_id', $ticket->passenger_id)
                    ->where('is_round_trip', true)
                    ->whereNull('return_trip_id')
                    ->whereNull('deleted_at')
                    ->first();

            if ($returnLeg?->occupiesSeat() && blank($returnSeatId)) {
                throw ValidationException::withMessages([
                    $isReturnLegTicket ? 'seat_id' : 'return_seat_id' => 'Elegí el nuevo asiento de vuelta antes de confirmar.',
                ]);
            }

            $this->validateScheduleForSegment($returnScheduleId, $returnOriginId, $returnDestinationId, 'return_schedule_id', $returnDate);

            $newReturnTrip = Trip::findOrCreateForBooking(
                (int) $returnScheduleId,
                $returnDate,
                $returnOriginId,
                $returnDestinationId,
            )['trip'] ?? null;

            if (! $newReturnTrip) {
                throw ValidationException::withMessages(['return_schedule_id' => 'No se pudo resolver el viaje de vuelta para la fecha y horario elegidos.']);
            }

            $newReturnSeat = $this->resolveSeat($returnSeatId, $newReturnTrip, $isReturnLegTicket ? 'seat_id' : 'return_seat_id');
        }

        // ---- Orden ida→vuelta ----
        // La subida de vuelta debe ser posterior a la BAJADA de ida (llegada a la
        // parada de transbordo). Si solo se mueve la ida, se compara contra la
        // vuelta existente; si solo se mueve la vuelta, contra la ida existente.
        $transferLocationId = $isReturnLegTicket
            ? (int) $ticket->origin_location_id          // el boleto de vuelta sube donde terminó la ida
            : (int) $ticket->destination_location_id;    // la ida termina donde se sube a la vuelta

        $outboundTripForOrder = $isReturnLegTicket
            ? $this->findOutboundTicket($ticket)?->trip
            : $newOutboundTrip;

        $returnTripForOrder = $hasReturnMove ? $newReturnTrip : $ticket->returnTrip;

        $outboundArrival = $outboundTripForOrder
            ? $this->getStopDatetime($outboundTripForOrder, $transferLocationId, useArrival: true)
            : null;

        $returnDeparture = $returnTripForOrder
            ? $this->getStopDatetime($returnTripForOrder, $transferLocationId, useArrival: false)
            : null;

        if ($outboundArrival && $returnDeparture && $returnDeparture->lte($outboundArrival)) {
            throw ValidationException::withMessages([
                'return_schedule_id' => 'El viaje de vuelta (' . $returnDeparture->format('d/m/Y H:i') . ') debe ser posterior al viaje de ida (' . $outboundArrival->format('d/m/Y H:i') . '). Por favor seleccione una fecha congruente.',
            ]);
        }

        // ---- Transacción ----
        $changes = [];

        try {
            $result = DB::transaction(function () use ($ticket, $newOutboundTrip, $newOutboundSeat, $newReturnTrip, $newReturnSeat, $hasReturnMove, $isReturnLegTicket, &$changes) {
                $mainTicket = $ticket->fresh();
                if (! $mainTicket || $mainTicket->trashed()) {
                    throw ValidationException::withMessages(['ticket' => 'El boleto fue eliminado mientras se procesaba el cambio.']);
                }

                $movedReturn = false;

                if ($isReturnLegTicket) {
                    // El boleto principal ES el tramo de vuelta: se mueve como LEG_RETURN
                    // y se sincroniza el return_trip_id del boleto de ida.
                    $outboundTicketForSync = $this->findOutboundTicket($mainTicket);

                    $originalTripId = (int) $mainTicket->trip_id;
                    $originalSeatId = $mainTicket->seat_id;
                    $originalScheduleId = $mainTicket->trip?->schedule_id;

                    $movedReturn = $this->moveLeg(
                        ticket: $mainTicket,
                        toTrip: $newReturnTrip,
                        toSeat: $newReturnSeat,
                        excludeTicketId: $mainTicket->id,
                    );

                    if ($movedReturn) {
                        if ($outboundTicketForSync) {
                            $outboundTicketForSync->forceFill(['return_trip_id' => $newReturnTrip->id])->save();
                        }

                        $changes[] = [
                            'ticket_id' => $mainTicket->id,
                            'leg' => TicketDateChange::LEG_RETURN,
                            'from_trip_id' => $originalTripId,
                            'to_trip_id' => $newReturnTrip->id,
                            'from_schedule_id' => $originalScheduleId,
                            'to_schedule_id' => $newReturnTrip->schedule_id,
                            'from_seat_id' => $originalSeatId,
                            'to_seat_id' => $newReturnSeat?->id,
                        ];
                    }
                } else {
                    $originalOutboundTripId = (int) $mainTicket->trip_id;
                    $originalOutboundSeatId = $mainTicket->seat_id;
                    $originalOutboundScheduleId = $mainTicket->trip?->schedule_id;

                    // 1) Ida
                    $movedOutbound = $this->moveLeg(
                        ticket: $mainTicket,
                        toTrip: $newOutboundTrip,
                        toSeat: $newOutboundSeat,
                        excludeTicketId: $mainTicket->id,
                    );

                    if ($movedOutbound) {
                        $changes[] = [
                            'ticket_id' => $mainTicket->id,
                            'leg' => TicketDateChange::LEG_OUTBOUND,
                            'from_trip_id' => $originalOutboundTripId,
                            'to_trip_id' => $newOutboundTrip->id,
                            'from_schedule_id' => $originalOutboundScheduleId,
                            'to_schedule_id' => $newOutboundTrip->schedule_id,
                            'from_seat_id' => $originalOutboundSeatId,
                            'to_seat_id' => $newOutboundSeat?->id,
                        ];
                    }
                }

                // 2) Vuelta (scope "both" desde el boleto de ida: el tramo de vuelta
                // vive en OTRO registro del mismo sale+passenger).
                if ($hasReturnMove && ! $isReturnLegTicket) {
                    $returnTicket = Ticket::query()
                        ->where('sale_id', $mainTicket->sale_id)
                        ->where('passenger_id', $mainTicket->passenger_id)
                        ->where('is_round_trip', true)
                        ->whereNull('return_trip_id')
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->first();

                    if (! $returnTicket) {
                        throw ValidationException::withMessages(['return_schedule_id' => 'No se encontró el boleto de vuelta del pasajero.']);
                    }

                    $originalReturnTripId = (int) $returnTicket->trip_id;
                    $originalReturnSeatId = $returnTicket->seat_id;
                    $originalReturnScheduleId = $returnTicket->trip?->schedule_id;

                    $movedReturn = $this->moveLeg(
                        ticket: $returnTicket,
                        toTrip: $newReturnTrip,
                        toSeat: $newReturnSeat,
                        excludeTicketId: $returnTicket->id,
                    );

                    if ($movedReturn) {
                        // 3) Sincronizar return_trip_id en el boleto de ida
                        $mainTicket->forceFill(['return_trip_id' => $newReturnTrip->id])->save();

                        $changes[] = [
                            'ticket_id' => $returnTicket->id,
                            'leg' => TicketDateChange::LEG_RETURN,
                            'from_trip_id' => $originalReturnTripId,
                            'to_trip_id' => $newReturnTrip->id,
                            'from_schedule_id' => $originalReturnScheduleId,
                            'to_schedule_id' => $newReturnTrip->schedule_id,
                            'from_seat_id' => $originalReturnSeatId,
                            'to_seat_id' => $newReturnSeat?->id,
                        ];
                    }
                }

                // 4) Auditoría (una fila por tramo realmente movido)
                foreach ($changes as $change) {
                    TicketDateChange::create($change + ['user_id' => Auth::id()]);
                }

                // 5) Liberar reservas temporales de esta sesión (asientos elegidos en el selector)
                SeatReservation::releaseBySession(session()->getId());

                return ['moved_return' => $movedReturn];
            });

            return [
                'ticket' => $ticket->fresh(),
                'changes' => $changes,
                'moved_return' => $result['moved_return'],
            ];
        } catch (QueryException $e) {
            if ($this->isUniqueSeatViolation($e)) {
                throw ValidationException::withMessages([
                    'seat_id' => 'El asiento acaba de ser vendido por otro usuario. Elegí otro asiento.',
                ]);
            }

            throw $e;
        }
    }

    /**
     * Mover un ticket a otro viaje dentro de la transacción activa.
     */
    private function moveLeg(Ticket $ticket, Trip $toTrip, ?Seat $toSeat, ?int $excludeTicketId): bool
    {
        $fromTripId = (int) $ticket->trip_id;
        $toTripId = (int) $toTrip->id;

        $tripChanged = $fromTripId !== $toTripId;
        $seatChanged = (int) ($ticket->seat_id ?? 0) !== (int) ($toSeat?->id ?? 0);

        if (! $tripChanged && ! $seatChanged) {
            return false; // Nada que mover en este tramo
        }

        if ($toSeat && (int) $toSeat->bus_id !== (int) $toTrip->bus_id) {
            throw ValidationException::withMessages(['seat_id' => 'El asiento elegido no pertenece al colectivo del viaje destino.']);
        }

        // Bloquear el viaje destino (o el mismo viaje si solo cambia el asiento) y re-chequear.
        $lockedTrip = Trip::query()
            ->where('id', $toTripId)
            ->lockForUpdate()
            ->first();

        if (! $lockedTrip) {
            throw ValidationException::withMessages(['trip_id' => 'El viaje de destino no existe.']);
        }

        // En el viaje destino el asiento se valida como cualquier venta nueva.
        // El excludeTicketId evita que el propio ticket cuente como ocupación
        // cuando el pasajero se queda en el mismo asiento (mismo viaje u otro bus
        // con la misma numeración y el ticket aún apunta al viaje viejo).
        $this->assertSeatAvailableOnTrip($lockedTrip, $toSeat?->id, $excludeTicketId);

        // UPDATE condicionado: el ticket debe seguir en su viaje de origen al momento del UPDATE.
        $updated = Ticket::query()
            ->where('id', $ticket->id)
            ->whereNull('deleted_at')
            ->when($tripChanged, fn ($q) => $q->where('trip_id', $fromTripId))
            ->update([
                'trip_id' => $toTripId,
                'seat_id' => $toSeat?->id,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages(['trip_id' => 'El boleto cambió de viaje mientras se procesaba. Reintentá.']);
        }

        $ticket->setRawAttributes($ticket->fresh()->getAttributes());

        return true;
    }

    /**
     * Validar que un asiento esté libre en un viaje (bloqueado previamente).
     * Ocupación = tickets activos del viaje + reservas vigentes de OTRAS sesiones.
     * $excludeTicketId permite ignorar el propio ticket (mismo pasajero moviéndose de asiento).
     */
    private function assertSeatAvailableOnTrip(Trip $trip, ?int $seatId, ?int $excludeTicketId = null): void
    {
        if (is_null($seatId)) {
            return; // Sin asiento no hay nada que validar (menores/mascotas no ocupan asiento extra)
        }

        $occupied = Ticket::query()
            ->where('trip_id', $trip->id)
            ->whereNotNull('seat_id')
            ->when($excludeTicketId, fn ($q) => $q->where('id', '!=', $excludeTicketId))
            ->whereNull('deleted_at')
            ->pluck('seat_id');

        $reservedByOthers = SeatReservation::query()
            ->where('trip_id', $trip->id)
            ->where('expires_at', '>', now())
            ->where('user_session_id', '!=', session()->getId())
            ->pluck('seat_id');

        if ($occupied->contains($seatId) || $reservedByOthers->contains($seatId)) {
            $seat = Seat::find($seatId);

            throw ValidationException::withMessages([
                'seat_id' => 'El asiento ' . ($seat?->seat_number ?? $seatId) . ' no está disponible en el viaje destino. Elegí otro.',
            ]);
        }
    }

    /**
     * Validar que el horario exista y cubra el segmento origen→destino.
     * La restricción de horario/ruta inactivos la aplica findOrCreateForBooking
     * (bloquea CREAR viajes nuevos con horario inactivo, pero permite usar uno existente).
     */
    private function validateScheduleForSegment(mixed $scheduleId, int $originId, int $destinationId, string $field, ?string $tripDate = null): void
    {
        if (blank($scheduleId)) {
            throw ValidationException::withMessages([$field => 'Elegí un horario.']);
        }

        if (blank($tripDate)) {
            throw ValidationException::withMessages([$field => 'Elegí una fecha.']);
        }

        $schedule = Schedule::query()->find((int) $scheduleId);

        if (! $schedule) {
            throw ValidationException::withMessages([$field => 'El horario seleccionado no existe.']);
        }

        $route = $schedule->route;

        if (! $route || ! $route->isValidSegment($originId, $destinationId)) {
            throw ValidationException::withMessages([$field => 'El segmento origen→destino no es válido para este horario.']);
        }
    }

    /**
     * Validar que el asiento exista y pertenezca al bus del viaje destino.
     */
    private function resolveSeat(mixed $seatId, Trip $trip, string $field): ?Seat
    {
        if (blank($seatId)) {
            return null;
        }

        $seat = Seat::query()->find((int) $seatId);

        if (! $seat) {
            throw ValidationException::withMessages([$field => 'El asiento seleccionado no existe.']);
        }

        if ((int) $seat->bus_id !== (int) $trip->bus_id) {
            throw ValidationException::withMessages([$field => 'El asiento seleccionado no pertenece al colectivo del viaje destino.']);
        }

        return $seat;
    }

    /**
     * Datetime en una parada de un viaje: llegada (useArrival) o salida.
     */
    private function getStopDatetime(Trip $trip, int $locationId, bool $useArrival = false)
    {
        if (! $trip->route || ! $trip->schedule) {
            return null;
        }

        $time = $useArrival
            ? $trip->route->getArrivalTimeForStop($locationId, $trip->schedule)
            : $trip->route->getDepartureTimeForStop($locationId, $trip->schedule);

        if (! $time) {
            return null;
        }

        return $trip->trip_date->copy()->setTimeFromTimeString($time->format('H:i:s'));
    }

    /**
     * Buscar el boleto de ida del mismo pasajero en la misma venta.
     */
    private function findOutboundTicket(Ticket $returnLegTicket): ?Ticket
    {
        return Ticket::query()
            ->where('sale_id', $returnLegTicket->sale_id)
            ->where('passenger_id', $returnLegTicket->passenger_id)
            ->where('is_round_trip', true)
            ->whereNotNull('return_trip_id')
            ->whereNull('deleted_at')
            ->first();
    }

    private function assertTicketIsReschedulable(Ticket $ticket): void
    {
        if ($ticket->trashed()) {
            throw ValidationException::withMessages(['ticket' => 'No se puede reprogramar un boleto eliminado.']);
        }

        if (! $ticket->trip) {
            throw ValidationException::withMessages(['ticket' => 'El boleto no tiene viaje asignado.']);
        }
    }

    private function validateAdmin(): void
    {
        if (! Auth::check() || ! Auth::user()->is_admin) {
            throw ValidationException::withMessages(['user' => 'Solo un administrador puede reprogramar boletos.']);
        }
    }

    private function isUniqueSeatViolation(QueryException $e): bool
    {
        return $e->getCode() == 23000
            && str_contains($e->getMessage(), 'tickets_trip_seat_unique');
    }
}
