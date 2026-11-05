<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\Trip;
use Illuminate\Support\Collection;

/**
 * Busca boletos activos que ya ubican a una persona (por DNI) en un viaje.
 *
 * Una persona está en un viaje si su DNI es el del pasajero de un boleto
 * activo de ese viaje o el de un menor acompañante de ese pasajero. Los
 * boletos borrados (soft delete) y los sin fecha (trip_id nulo) no cuentan.
 */
class TripPassengerConflicts
{
    /**
     * @param  array<int, string|int|null>  $dnis
     * @param  array<int, int|string|null>  $tripIds
     * @param  array<int, int|string|null>  $excludeTicketIds
     * @return Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>
     */
    public static function find(array $dnis, array $tripIds, array $excludeTicketIds = []): Collection
    {
        $dnis = self::normalizeDnis($dnis);
        $tripIds = self::normalizeIds($tripIds);
        $excludeTicketIds = self::normalizeIds($excludeTicketIds);

        if ($dnis === [] || $tripIds === []) {
            return collect();
        }

        $tickets = Ticket::query()
            ->whereIn('trip_id', $tripIds)
            ->when($excludeTicketIds !== [], fn ($query) => $query->whereNotIn('id', $excludeTicketIds))
            ->where(fn ($query) => $query
                ->whereHas('passenger', fn ($passenger) => $passenger->whereIn('dni', $dnis))
                ->orWhereHas('passenger.children', fn ($child) => $child->whereIn('dni', $dnis)))
            ->with(['passenger.children', 'trip.schedule', 'trip.bus', 'seat', 'origin', 'destination'])
            ->orderBy('id')
            ->get();

        return $tickets
            ->flatMap(function (Ticket $ticket) use ($dnis): array {
                $ticketDnis = collect([$ticket->passenger?->dni])
                    ->merge($ticket->passenger?->children->pluck('dni') ?? [])
                    ->map(fn ($dni): string => trim((string) $dni))
                    ->filter(fn (string $dni): bool => in_array($dni, $dnis, true))
                    ->unique();

                return $ticketDnis
                    ->map(fn (string $dni): array => ['dni' => $dni, 'ticket' => $ticket, 'trip' => $ticket->trip])
                    ->all();
            })
            ->values();
    }

    /**
     * Viajes con fecha de una venta: la ida salvo que se venda sin fecha, y
     * la vuelta si es ida y vuelta y no se vende sin fecha.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public static function saleTripIds(array $data): array
    {
        return self::normalizeIds([
            (bool) ($data['sell_without_date_ida'] ?? false) ? null : ($data['trip_id'] ?? null),
            (bool) ($data['is_round_trip'] ?? false) && ! (bool) ($data['sell_without_date_vuelta'] ?? false)
                ? ($data['return_trip_id'] ?? null)
                : null,
        ]);
    }

    /**
     * DNI de todos los pasajeros de una venta, en orden: cada adulto seguido
     * de su menor acompañante (solo si viaja con menor). Incluye vacíos para
     * que la posición se pueda usar al marcar el campo repetido.
     *
     * @param  array<int|string, array<string, mixed>>  $passengers
     * @return list<array{key: int|string, field: string, dni: string}>
     */
    public static function saleDniSlots(array $passengers): array
    {
        $slots = [];

        foreach ($passengers as $key => $passenger) {
            $slots[] = ['key' => $key, 'field' => 'dni', 'dni' => trim((string) ($passenger['dni'] ?? ''))];

            if ((bool) ($passenger['travels_with_child'] ?? false)) {
                $slots[] = ['key' => $key, 'field' => 'child_data.dni', 'dni' => trim((string) ($passenger['child_data']['dni'] ?? ''))];
            }
        }

        return $slots;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $passengers
     * @return list<string>
     */
    public static function saleDnis(array $passengers): array
    {
        return self::normalizeDnis(array_column(self::saleDniSlots($passengers), 'dni'));
    }

    /**
     * DNI que aparecen más de una vez en la lista (vacíos ignorados).
     *
     * @param  array<int, string|int|null>  $dnis
     * @return list<string>
     */
    public static function repeatedDnis(array $dnis): array
    {
        return collect($dnis)
            ->map(fn ($dni): string => trim((string) $dni))
            ->filter(fn (string $dni): bool => $dni !== '')
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->keys()
            ->map(fn ($dni): string => (string) $dni)
            ->values()
            ->all();
    }

    /**
     * Texto corto del viaje para mensajes: "05/10/2026 08:00 - Linea 1".
     */
    public static function describeTrip(?Trip $trip): string
    {
        if (! $trip) {
            return 'viaje sin datos';
        }

        return trim(
            ($trip->trip_date?->format('d/m/Y') ?? '')
            .($trip->schedule?->departure_time ? ' '.$trip->schedule->departure_time->format('H:i') : '')
            .($trip->bus?->name ? ' - '.$trip->bus->name : '')
        );
    }

    /**
     * @param  array<int, string|int|null>  $dnis
     * @return list<string>
     */
    private static function normalizeDnis(array $dnis): array
    {
        return collect($dnis)
            ->map(fn ($dni): string => trim((string) $dni))
            ->filter(fn (string $dni): bool => $dni !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string|null>  $ids
     * @return list<int>
     */
    private static function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
