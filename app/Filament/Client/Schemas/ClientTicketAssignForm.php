<?php

namespace App\Filament\Client\Schemas;

use App\Models\Seat;
use App\Models\SeatReservation;
use App\Models\Ticket;
use App\Models\Trip;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Formulario del action "Elegir fecha" del panel de clientes: mismo
 * mecanismo que la reprogramación de admin (reservas temporales de asiento
 * + findOrCreateForBooking), pero en modo de un sólo tramo.
 *
 * El tramo (ida o vuelta) es el propio boleto que abre el form: si es el
 * tramo de vuelta, sus origin/destination ya vienen invertidos en el
 * registro, así que las opciones de horario salen de ahí.
 */
class ClientTicketAssignForm
{
    public static function make(Ticket $ticket): array
    {
        $isReturnLeg = $ticket->isReturnLeg();
        $legLabel = $isReturnLeg ? 'vuelta' : 'ida';
        $originId = (int) $ticket->origin_location_id;
        $destinationId = (int) $ticket->destination_location_id;

        return [
            DatePicker::make('date')
                ->label('Nueva fecha de ' . $legLabel)
                ->required()
                ->minDate(self::minDate($ticket))
                ->maxDate(self::maxDate($ticket))
                ->live()
                ->afterStateUpdated(function (Set $set) {
                    $set('trip_id', null);
                    $set('seat_ids', []);
                })
                ->validationMessages([
                    'required' => 'Elegí una fecha para tu viaje.',
                    'before_or_equal' => 'La fecha no puede quedar después de la vuelta.',
                    'after_or_equal' => 'La fecha no puede ser anterior a hoy ni a la otra fecha del pasaje.',
                ]),

            Select::make('schedule_id')
                ->label('Horario de ' . $legLabel)
                ->required()
                ->live()
                ->disabled(fn (Get $get) => blank($get('date')))
                ->placeholder(fn (Get $get) => blank($get('date'))
                    ? 'Elegí una fecha primero'
                    : 'Elegí un horario')
                ->options(function (Get $get) use ($originId, $destinationId): array {
                    $date = $get('date');

                    if (blank($date)) {
                        return [];
                    }

                    return self::scheduleOptions($originId, $destinationId);
                })
                ->afterStateUpdated(function (Set $set, Get $get, $state) use ($ticket) {
                    $set('seat_ids', []);

                    if (blank($state) || blank($get('date'))) {
                        $set('trip_id', null);

                        return;
                    }

                    $trip = self::resolveTrip($state, $get('date'), $ticket);

                    if (! $trip) {
                        $set('trip_id', null);

                        return;
                    }

                    $set('trip_id', $trip->id);

                    // Autoseleccionar el asiento con el mismo número si existe y está libre.
                    $sameNumberSeat = self::findSameNumberSeat($ticket, $trip);

                    if ($sameNumberSeat) {
                        $set('seat_ids', [$sameNumberSeat->id]);
                    }
                })
                ->helperText(function (Get $get) {
                    $tripId = $get('trip_id');

                    if (blank($tripId)) {
                        return null;
                    }

                    $trip = Trip::find((int) $tripId);

                    if (! $trip) {
                        return null;
                    }

                    $available = $trip->remainingSeats();

                    if ($available < 1) {
                        return 'Atención: este viaje ya no tiene asientos disponibles. Elegí otro horario.';
                    }

                    return 'Viaje disponible (' . $trip->bus?->name . '). Asientos disponibles: ' . $available;
                })
                ->validationMessages([
                    'required' => 'Elegí un horario.',
                ]),

            Hidden::make('trip_id')
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('seat_ids', [])),

            // Nota: el asiento se valida en el action del dashboard (con
            // notificación), no acá: un error atado a un Hidden es invisible
            // y dejaría el modal mudo al confirmar.
            Hidden::make('seat_ids')
                ->live()
                ->dehydrated(),

            ViewField::make('seat_selector')
                ->label('Tu asiento')
                ->columnSpanFull()
                ->view('tickets.seat-selector')
                ->visible(fn (Get $get) => filled($get('trip_id')) && Trip::find((int) $get('trip_id')) !== null)
                ->viewData(function (Get $get, ViewField $component) {
                    $trip = Trip::find((int) $get('trip_id'));
                    $sessionId = session()->getId();

                    if ($trip && filled($get('seat_ids'))) {
                        self::ensureReservationForSelectedSeats($trip, (array) $get('seat_ids'), $sessionId);
                    }

                    // Path de estado absoluto donde el selector sincroniza los
                    // asientos elegidos (“mountedActions.0.data.seat_ids” dentro
                    // del modal de action; "data.seat_ids" en pages con form).
                    $containerStatePath = (string) $component->getContainer()->getStatePath();

                    return [
                        'seatSelectorStatePath' => filled($containerStatePath)
                            ? $containerStatePath . '.seat_ids'
                            : 'data.seat_ids',
                        'trip_id' => $trip?->id,
                        'trip' => $trip,
                        'passengers_count' => 1,
                        'fieldId' => 'seat_ids',
                        'session_id' => $sessionId,
                        'enable_reservation' => true,
                        'reservation_timeout' => 5,
                    ];
                }),
        ];
    }

    /** Mínimo del calendario: hoy; para la vuelta, mínimo también la fecha de la ida. */
    private static function minDate(Ticket $ticket): ?Carbon
    {
        $today = Carbon::today();

        if (! $ticket->isReturnLeg()) {
            return $today;
        }

        // Vuelta: no puede salir antes que la ida ya asignada.
        $outbound = Ticket::query()
            ->where('sale_id', $ticket->sale_id)
            ->where('passenger_id', $ticket->passenger_id)
            ->where('is_round_trip', true)
            ->where('is_return_leg', false)
            ->whereNull('deleted_at')
            ->first();

        $outboundDate = $outbound?->trip?->trip_date?->copy()->startOfDay();

        if ($outboundDate && $outboundDate->gte($today)) {
            return $outboundDate;
        }

        return $today;
    }

    /** Máximo del calendario: la ida no puede quedar después de la vuelta ya asignada. */
    private static function maxDate(Ticket $ticket): ?Carbon
    {
        if ($ticket->isReturnLeg()) {
            return null;
        }

        $returnLeg = Ticket::query()
            ->where('sale_id', $ticket->sale_id)
            ->where('passenger_id', $ticket->passenger_id)
            ->where('is_return_leg', true)
            ->whereNull('deleted_at')
            ->first();

        $returnDate = $returnLeg?->trip?->trip_date?->copy()->startOfDay();

        if ($returnDate && $returnDate->gte(Carbon::today())) {
            return $returnDate;
        }

        return null;
    }

    /** Horarios activos que cubren el segmento del boleto. */
    private static function scheduleOptions(int $originId, int $destinationId): array
    {
        return \App\Models\Schedule::query()
            ->bookable()
            ->whereHas('route.stops', fn ($q) => $q->where('location_id', $originId))
            ->whereHas('route.stops', fn ($q) => $q->where('location_id', $destinationId))
            ->with('route.stops')
            ->get()
            ->filter(fn ($schedule) => $schedule->route->isValidSegment($originId, $destinationId))
            ->sortBy(fn (\App\Models\Schedule $schedule) => $schedule->display_name, SORT_NATURAL | SORT_FLAG_CASE)
            ->mapWithKeys(fn (\App\Models\Schedule $schedule) => [$schedule->id => $schedule->display_name])
            ->toArray();
    }

    /** Resuelve/crea el viaje destino con la misma maquinaria de ventas. */
    private static function resolveTrip(mixed $scheduleId, mixed $date, Ticket $ticket): ?Trip
    {
        $result = Trip::findOrCreateForBooking(
            (int) $scheduleId,
            self::formatDate($date),
            (int) $ticket->origin_location_id,
            (int) $ticket->destination_location_id,
        );

        return $result['trip'] ?? null;
    }

    /** Asiento con el mismo número en el bus del viaje destino, si está libre. */
    private static function findSameNumberSeat(Ticket $ticket, Trip $newTrip): ?Seat
    {
        $currentSeatNumber = $ticket->seat?->seat_number;

        if (blank($currentSeatNumber)) {
            return null;
        }

        $occupiedIds = $newTrip->activeSeatedTickets()->pluck('seat_id');

        return Seat::query()
            ->where('bus_id', $newTrip->bus_id)
            ->where('is_active', true)
            ->where('seat_number', $currentSeatNumber)
            ->whereNotIn('id', $occupiedIds)
            ->first();
    }

    /**
     * Reservar temporalmente los asientos elegidos para esta sesión mientras
     * el cliente completa el form (igual que el panel de admin).
     */
    private static function ensureReservationForSelectedSeats(Trip $trip, array $seatIds, string $sessionId): void
    {
        $validIds = Seat::query()
            ->where('bus_id', $trip->bus_id)
            ->where('is_active', true)
            ->whereIn('id', $seatIds)
            ->pluck('id')
            ->all();

        if (empty($validIds)) {
            return;
        }

        $alreadyReserved = SeatReservation::query()
            ->where('trip_id', $trip->id)
            ->whereIn('seat_id', $validIds)
            ->where('user_session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->pluck('seat_id')
            ->all();

        $missing = array_diff($validIds, $alreadyReserved);

        if (empty($missing)) {
            SeatReservation::query()
                ->where('user_session_id', $sessionId)
                ->where('trip_id', $trip->id)
                ->whereIn('seat_id', $validIds)
                ->update(['expires_at' => now()->addMinutes(5)]);

            return;
        }

        SeatReservation::reserveSeats($trip->id, $missing, $sessionId, 5);
    }

    private static function formatDate(mixed $date): string
    {
        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }

        return Carbon::parse($date)->format('Y-m-d');
    }
}
