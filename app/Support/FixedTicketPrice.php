<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Ticket;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Precio fijo de los boletos: única fuente del cálculo para la vista previa
 * de la venta, la emisión y la edición de boletos.
 *
 * Precio de un pasajero = (precio del boleto + seguro si viaja con menor),
 * por 2 si la venta es de ida y vuelta. El monto completo va en el boleto
 * de ida; la vuelta vale 0.
 *
 * Los montos salen del período de precio vigente en la fecha de venta: el
 * más reciente cuyo "desde" es ese día o anterior.
 */
final class FixedTicketPrice
{
    /** ¿El precio se calcula por parámetro? */
    public static function isActive(): bool
    {
        return Setting::precioFijoActivo();
    }

    /**
     * Período de precio vigente en una fecha, o null si todos empiezan después.
     *
     * @return array{desde: string, boleto: int, seguro: int}|null
     */
    public static function periodFor(CarbonInterface $date): ?array
    {
        $day = $date->copy()->timezone(config('app.timezone'))->format('Y-m-d');
        $current = null;

        foreach (Setting::precioFijoPeriodos() as $period) {
            if ($period['desde'] > $day) {
                break;
            }

            $current = $period;
        }

        return $current;
    }

    /** ¿Hay un período de precio que rige hoy? */
    public static function hasCurrentPeriod(): bool
    {
        return self::periodFor(today()) !== null;
    }

    /** Precio del boleto por tramo, del período de hoy (0 si no hay). */
    public static function basePrice(): int
    {
        return self::periodFor(today())['boleto'] ?? 0;
    }

    /** Seguro para menores por tramo, del período de hoy (0 si no hay). */
    public static function childInsurance(): int
    {
        return self::periodFor(today())['seguro'] ?? 0;
    }

    /** Precio que lleva el boleto de ida de un pasajero. */
    public static function forPassenger(bool $withChild, bool $isRoundTrip): int
    {
        return self::compute(self::basePrice(), self::childInsurance(), $withChild, $isRoundTrip);
    }

    /**
     * Precio calculado con la fotografía que guardó la venta al abrirse, o
     * null si en esa fotografía el precio fijo estaba apagado (precio a mano).
     * La venta muestra este valor: así lo que ve el vendedor es siempre lo
     * que la emisión va a validar contra la configuración vigente.
     */
    public static function fromSnapshot(?string $snapshot, bool $withChild, bool $isRoundTrip): ?int
    {
        $parts = explode('|', (string) $snapshot);

        if (count($parts) !== 3 || $parts[0] !== '1') {
            return null;
        }

        return self::compute((int) $parts[1], (int) $parts[2], $withChild, $isRoundTrip);
    }

    /** ¿La fotografía corresponde a una venta con el precio fijo prendido? */
    public static function snapshotIsActive(?string $snapshot): bool
    {
        return str_starts_with((string) $snapshot, '1|');
    }

    private static function compute(int $base, int $insurance, bool $withChild, bool $isRoundTrip): int
    {
        return ($base + ($withChild ? $insurance : 0)) * ($isRoundTrip ? 2 : 1);
    }

    /**
     * Cuánto cambia el precio de la ida al agregar (+seguro) o quitar
     * (−seguro) el menor en un tramo, con el seguro del período vigente en
     * la fecha de la venta. Una venta anterior al primer período usa el más
     * antiguo.
     */
    public static function insuranceDelta(bool $hadChild, bool $hasChild, CarbonInterface $saleDate): int
    {
        if ($hadChild === $hasChild) {
            return 0;
        }

        $period = self::periodFor($saleDate) ?? (Setting::precioFijoPeriodos()[0] ?? null);
        $insurance = $period['seguro'] ?? 0;

        return $hasChild ? $insurance : -$insurance;
    }

    /**
     * Cómo se vendió el boleto de ida, para guardarlo en él: con el precio
     * fijo, el modo "fijo" y el seguro por tramo de hoy (aunque no viaje un
     * menor, por si se agrega después); a mano, el modo "manual" sin seguro.
     * La vuelta no lleva estos datos: los lee de su ida.
     *
     * @return array{price_mode: string, child_insurance: int|null}
     */
    public static function outboundPricingAttributes(): array
    {
        if (! self::isActive()) {
            return ['price_mode' => Ticket::PRICE_MODE_MANUAL, 'child_insurance' => null];
        }

        return ['price_mode' => Ticket::PRICE_MODE_FIXED, 'child_insurance' => self::childInsurance()];
    }

    /**
     * Cuántos pasajes vendidos antes de registrar el modo de precio dependen
     * del período que va de `$from` (null = sin límite, el período más
     * antiguo también cubre las ventas anteriores) hasta el día anterior a
     * `$until` (null = en adelante). Son los que tomarían el seguro de otro
     * período si se borra este. Solo cuentan las idas vigentes cuya ida o
     * vuelta todavía no viajó o no tiene fecha: editar el menor de un viaje
     * ya hecho es raro, y así el aviso deja de salir solo.
     */
    public static function legacyTicketsDependingOn(?string $from, ?string $until): int
    {
        $timezone = config('app.timezone');
        $start = $from === null ? null : Carbon::parse($from, $timezone)->startOfDay();
        $end = $until === null ? null : Carbon::parse($until, $timezone)->startOfDay();
        $today = today()->format('Y-m-d');

        $inRange = function (Builder $query, string $column) use ($start, $end): void {
            $query
                ->when($start, fn (Builder $q) => $q->where($column, '>=', $start))
                ->when($end, fn (Builder $q) => $q->where($column, '<', $end));
        };

        return Ticket::query()
            ->whereNull('price_mode')
            ->where('is_return_leg', false)
            // Mismo criterio que la edición: la fecha de la venta, o la del
            // boleto si su venta fue eliminada.
            ->where(fn (Builder $query) => $query
                ->whereHas('sale', fn (Builder $sale) => $inRange($sale, 'created_at'))
                ->orWhere(fn (Builder $orphan) => $orphan
                    ->whereDoesntHave('sale')
                    ->where(fn (Builder $q) => $inRange($q, 'tickets.created_at'))))
            ->where(fn (Builder $query) => $query
                ->whereNull('trip_id')
                ->orWhereHas('trip', fn (Builder $trip) => $trip->whereDate('trip_date', '>=', $today))
                ->orWhereExists(fn (QueryBuilder $returnLeg) => $returnLeg
                    ->selectRaw('1')
                    ->from('tickets as return_legs')
                    ->leftJoin('trips as return_trips', 'return_trips.id', '=', 'return_legs.trip_id')
                    ->whereColumn('return_legs.sale_id', 'tickets.sale_id')
                    ->whereColumn('return_legs.passenger_id', 'tickets.passenger_id')
                    ->where('return_legs.is_return_leg', true)
                    ->whereNull('return_legs.deleted_at')
                    ->where(fn (QueryBuilder $q) => $q
                        ->whereNull('return_legs.trip_id')
                        ->orWhereDate('return_trips.trip_date', '>=', $today))))
            ->count();
    }

    /**
     * Fotografía de la configuración vigente (`activo|base|seguro`). La venta
     * la guarda al abrirse y la emisión la compara para detectar que alguien
     * cambió los precios con la venta abierta.
     */
    public static function snapshot(): string
    {
        return implode('|', [
            self::isActive() ? '1' : '0',
            self::basePrice(),
            self::childInsurance(),
        ]);
    }
}
