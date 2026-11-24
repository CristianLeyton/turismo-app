<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;

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
