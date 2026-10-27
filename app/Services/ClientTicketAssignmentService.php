<?php

namespace App\Services;

use App\Models\Clients;
use App\Models\Ticket;
use App\Support\ClientSession;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Asignación de primera fecha (y asiento) desde el panel público de clientes.
 *
 * Reglas de seguridad, todas server-side:
 * - El boleto debe pertenecer al cliente identificado en la sesión
 *   (búsqueda por DNI del pasajero; nunca por IDs que llegan por URL/form).
 * - Sólo boletos PENDIENTES (trip_id NULL): una vez asignada la fecha, el
 *   cliente queda bloqueado (only el personal reprograma).
 * - La fecha debe ser hoy o futura.
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

        if (blank($data['schedule_id'] ?? null)) {
            throw ValidationException::withMessages(['schedule_id' => 'Elegí un horario.']);
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
        ], null);
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
    public static function ticketsForCurrentClient(): \Illuminate\Database\Eloquent\Builder
    {
        $dni = ClientSession::dni();

        return Ticket::query()
            ->when(
                blank($dni),
                fn ($q) => $q->whereRaw('1 = 0'),
                fn ($q) => $q->whereHas('passenger', fn ($p) => $p->where('dni', $dni)),
            );
    }

    private static function formatDate(Carbon $date): string
    {
        return $date->format('Y-m-d');
    }
}
