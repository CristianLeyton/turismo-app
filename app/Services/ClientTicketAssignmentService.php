<?php

namespace App\Services;

use App\Models\Clients;
use App\Models\Schedule;
use App\Models\Ticket;
use App\Models\Trip;
use App\Support\ClientAdvanceNotice;
use App\Support\ClientSession;
use App\Support\ServiceDays;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Asignación de primera fecha (y asiento) desde el panel público de clientes.
 *
 * Reglas de seguridad, todas server-side:
 * - El boleto debe pertenecer al cliente identificado en la sesión
 *   (búsqueda por DNI del pasajero; nunca por IDs que llegan por URL/form).
 * - Sólo boletos PENDIENTES (trip_id NULL): una vez asignada la fecha, el
 *   cliente queda bloqueado (only el personal reprograma).
 * - La fecha debe ser hoy o futura y tener servicio (sin excepciones por permiso).
 * - El tramo ida y la vuelta se asignan por separado (scope "outbound" del
 *   núcleo de TicketRescheduleService, que mueve sólo el boleto en cuestión).
 */
class ClientTicketAssignmentService
{
    /**
     * Asignar fecha/horario/asiento a un boleto pendiente del cliente en sesión.
     *
     * @param  array{date: string, schedule_id: int, seat_id: int}  $data
     * @return array{ticket: Ticket, changes: array, moved_return: bool}
     */
    public function assign(Ticket $ticket, array $data): array
    {
        // Re-leer desde la base de datos: el objeto puede venir stale (snapshot
        // viejo de Livewire, objeto reusado) y un trip_id en memoria por detrás
        // del real permitiría reprocesar un boleto ya fechado.
        $ticket = Ticket::query()->findOrFail($ticket->id);

        $client = ClientSession::current();

        if (! $client || ! self::clientOwnsTicket($ticket, $client)) {
            throw ValidationException::withMessages(['ticket' => 'Boleto no encontrado.']);
        }

        if ($ticket->trashed()) {
            throw ValidationException::withMessages(['ticket' => 'El boleto no está disponible.']);
        }

        if (! $ticket->isPendingDate()) {
            // Bloqueo tras asignar: el cliente no reasigna; sólo el personal.
            throw ValidationException::withMessages([
                'ticket' => 'Este boleto ya tiene fecha asignada. Si necesitás cambiarla, contactá a boletería.',
            ]);
        }

        $date = Carbon::parse($data['date'] ?? '');

        if ($date->startOfDay()->lt(Carbon::today())) {
            throw ValidationException::withMessages(['date' => 'La fecha tiene que ser hoy o posterior.']);
        }

        // Días de servicio: el panel de clientes nunca aplica el permiso
        // `tickets.vender_dia_sin_servicio`, aunque haya un usuario del panel
        // de administración logueado en el mismo navegador.
        $serviceDayReason = ServiceDays::blockReason($date, allowBypass: false);

        if ($serviceDayReason !== null) {
            throw ValidationException::withMessages(['date' => $serviceDayReason]);
        }

        if (blank($data['schedule_id'] ?? null)) {
            throw ValidationException::withMessages(['schedule_id' => 'Elegí un horario.']);
        }

        // Anticipación mínima (Parámetros > Clientes): sólo aplica al panel de
        // clientes, no a la reprogramación del administrador.
        $schedule = Schedule::query()->find((int) $data['schedule_id']);

        if ($schedule === null || ! ClientAdvanceNotice::allows($schedule, $date)) {
            throw ValidationException::withMessages(['schedule_id' => ClientAdvanceNotice::message()]);
        }

        // El form del panel manda el asiento como "seat_ids" (array del selector
        // compartido con ventas); el núcleo de reprogramación espera "seat_id".
        $seatIds = array_values(array_filter(
            array_map(fn ($v) => (int) $v, (array) ($data['seat_ids'] ?? [])),
            fn ($v) => $v > 0,
        ));
        $seatId = count($seatIds) === 1
            ? $seatIds[0]
            : (int) ($data['seat_id'] ?? 0);

        if (blank($seatId)) {
            throw ValidationException::withMessages(['seat_id' => 'Elegí tu asiento antes de confirmar.']);
        }

        return app(TicketRescheduleService::class)->rescheduleForActor($ticket, [
            'scope' => 'outbound',
            'date' => self::formatDate($date),
            'schedule_id' => (int) $data['schedule_id'],
            'seat_id' => $seatId,
        ], null, allowServiceDayBypass: false, clientId: $client->id);
    }

    /**
     * ¿El boleto pertenece al cliente (identificado o dado)?
     * La unión es ticket → pasajero (dni) → clients (dni).
     */
    public static function clientOwnsTicket(Ticket $ticket, ?Clients $client = null): bool
    {
        $client ??= ClientSession::current();

        if (! $client) {
            return false;
        }

        return $ticket->passenger?->dni === $client->dni;
    }

    /**
     * Query base del panel: TODOS los boletos del cliente en sesión
     * (pendientes y ya fechados). Sin cliente en sesión: nada.
     */
    public static function ticketsForCurrentClient(): Builder
    {
        $dni = ClientSession::dni();

        return Ticket::query()
            ->when(
                blank($dni),
                fn ($q) => $q->whereRaw('1 = 0'),
                fn ($q) => $q->whereHas('passenger', fn ($p) => $p->where('dni', $dni)),
            );
    }

    /**
     * Boletos del cliente que esperan fecha (sin viaje asignado).
     */
    public static function pendingTicketsForCurrentClient(): Builder
    {
        return self::ticketsForCurrentClient()->whereNull('tickets.trip_id');
    }

    /**
     * Boletos con viaje de hoy en adelante. Un viaje de hoy cuenta como
     * próximo aunque su horario ya haya pasado: se decide por fecha.
     */
    public static function upcomingTicketsForCurrentClient(): Builder
    {
        return self::ticketsForCurrentClient()
            ->whereHas('trip', fn (Builder $trip) => $trip->whereDate('trip_date', '>=', Carbon::today()));
    }

    /**
     * Boletos con viaje anterior a hoy (viajes ya realizados).
     */
    public static function pastTicketsForCurrentClient(): Builder
    {
        return self::ticketsForCurrentClient()
            ->whereHas('trip', fn (Builder $trip) => $trip->whereDate('trip_date', '<', Carbon::today()));
    }

    /**
     * Boleto del próximo viaje: el de fecha y horario más cercanos desde hoy,
     * y con el menor número si coinciden.
     */
    public static function nextTripTicketForCurrentClient(): ?Ticket
    {
        return self::upcomingTicketsForCurrentClient()
            ->with(['origin', 'destination', 'trip.schedule', 'seat'])
            ->orderBy(Trip::query()
                ->select('trip_date')
                ->whereColumn('trips.id', 'tickets.trip_id'))
            ->orderBy(Trip::query()
                ->join('schedules', 'schedules.id', '=', 'trips.schedule_id')
                ->select('schedules.departure_time')
                ->whereColumn('trips.id', 'tickets.trip_id'))
            ->orderBy('tickets.id')
            ->first();
    }

    /**
     * Boleto sin fecha más antiguo (menor número), el que ofrece el aviso.
     */
    public static function oldestPendingTicketForCurrentClient(): ?Ticket
    {
        return self::pendingTicketsForCurrentClient()->orderBy('tickets.id')->first();
    }

    private static function formatDate(Carbon $date): string
    {
        return $date->format('Y-m-d');
    }
}
