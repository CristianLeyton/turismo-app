<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Precio fijo de los boletos: única fuente del cálculo para la vista previa
 * de la venta, la emisión y la edición de boletos.
 *
 * Precio de un pasajero = (precio del boleto + seguro si viaja con menor),
 * por 2 si la venta es de ida y vuelta. El monto completo va en el boleto
 * de ida; la vuelta vale 0.
 */
final class FixedTicketPrice
{
    /** ¿El precio se calcula por parámetro? */
    public static function isActive(): bool
    {
        return Setting::precioFijoActivo();
    }

    /** Precio del boleto por tramo. */
    public static function basePrice(): int
    {
        return Setting::precioFijoBoleto();
    }

    /** Seguro para menores por tramo. */
    public static function childInsurance(): int
    {
        return Setting::precioFijoSeguroMenor();
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
     * (−seguro) el menor en un tramo, con el seguro vigente.
     */
    public static function insuranceDelta(bool $hadChild, bool $hasChild): int
    {
        if ($hadChild === $hasChild) {
            return 0;
        }

        return $hasChild ? self::childInsurance() : -self::childInsurance();
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
