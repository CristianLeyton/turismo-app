<?php

namespace App\Support;

use App\Models\Schedule;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Anticipación mínima con la que un cliente puede elegir el horario de su
 * boleto sin fecha desde el panel de clientes (Parámetros > Clientes).
 *
 * La salida que cuenta es la del origen del servicio (la hora de salida del
 * horario), no la de la parada donde sube el pasajero. 0 horas = sin mínimo.
 *
 * Es la única fuente de verdad del selector de horarios del cliente y del
 * control del servidor, igual que ServiceDays y SaleCutoff. No aplica a la
 * venta, la reprogramación ni la creación de viajes del panel de administración.
 */
class ClientAdvanceNotice
{
    /**
     * Horas de anticipación vigentes.
     */
    public static function hours(): int
    {
        return Setting::clientAdvanceNoticeHours();
    }

    /**
     * ¿La anticipación exige algo? Con 0 horas no se excluye ningún horario.
     */
    public static function enabled(): bool
    {
        return self::hours() > 0;
    }

    /**
     * ¿El horario, en la fecha dada, sale con al menos las horas de
     * anticipación? Con exactamente las horas justas se permite.
     */
    public static function allows(Schedule $schedule, CarbonInterface|string $date): bool
    {
        if (! self::enabled()) {
            return true;
        }

        return self::departureAt($schedule, $date)->gte(now()->addHours(self::hours()));
    }

    /**
     * Momento en que sale el servicio ese día: la fecha con la hora de salida
     * del horario.
     */
    public static function departureAt(Schedule $schedule, CarbonInterface|string $date): Carbon
    {
        $time = $schedule->departure_time;

        return Carbon::parse($date)->startOfDay()->setTime((int) $time->format('H'), (int) $time->format('i'));
    }

    /**
     * "Elegí un horario con al menos 24hs de anticipación."
     */
    public static function message(): string
    {
        return 'Elegí un horario con al menos '.self::hours().'hs de anticipación.';
    }

    /**
     * Texto del selector de horario cuando el día no tiene ninguno que cumpla.
     */
    public static function emptyOptionsMessage(): string
    {
        return 'No hay horarios con '.self::hours().'hs de anticipación para ese día. Elegí otra fecha.';
    }
}
