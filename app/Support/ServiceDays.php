<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Días de servicio (configuración en Configuración > Parametros).
 *
 * Una fecha tiene servicio si su día de la semana está configurado con
 * servicio, salvo que exista una excepción para esa fecha, que manda.
 * Quien tenga el permiso `tickets.vender_dia_sin_servicio` puede elegir
 * fechas sin servicio en la venta, la reprogramación y la creación de
 * viajes; el panel de clientes nunca lo aplica.
 *
 * Es la única fuente de verdad para los calendarios (disabledDates) y los
 * controles del servidor, igual que SaleCutoff para la venta fuera de término.
 */
class ServiceDays
{
    public const PERMISSION = 'tickets.vender_dia_sin_servicio';

    /**
     * ¿La fecha tiene servicio?
     */
    public static function hasService(CarbonInterface|string $date): bool
    {
        $date = self::toDate($date);
        $exception = self::exceptionFor($date);

        if ($exception !== null) {
            return $exception['has_service'];
        }

        return in_array($date->dayOfWeekIso, Setting::serviceWeekdays(), true);
    }

    /**
     * Excepción configurada para la fecha (si hay varias, la última), o null.
     *
     * @return array{date: string, has_service: bool, reason: ?string}|null
     */
    public static function exceptionFor(CarbonInterface|string $date): ?array
    {
        $key = self::toDate($date)->format('Y-m-d');

        return self::exceptionsByDate()[$key] ?? null;
    }

    /**
     * Fechas sin servicio entre dos fechas (inclusive), en formato Y-m-d.
     *
     * @return list<string>
     */
    public static function disabledDatesBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $weekdays = Setting::serviceWeekdays();
        $exceptions = self::exceptionsByDate();
        $disabled = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $hasService = isset($exceptions[$key])
                ? $exceptions[$key]['has_service']
                : in_array($day->dayOfWeekIso, $weekdays, true);

            if (! $hasService) {
                $disabled[] = $key;
            }
        }

        return $disabled;
    }

    /**
     * Fechas sin servicio para los calendarios de viaje: el mismo rango que
     * se usaba antes (un año atrás, dos adelante).
     *
     * @return list<string>
     */
    public static function disabledTravelDates(): array
    {
        return self::disabledDatesBetween(Carbon::today()->subYear(), Carbon::today()->addYears(2));
    }

    /**
     * Fechas a deshabilitar en un calendario del panel de administración:
     * ninguna si el usuario tiene el permiso.
     *
     * @return list<string>
     */
    public static function disabledTravelDatesForCurrentUser(): array
    {
        return self::userCanBypass() ? [] : self::disabledTravelDates();
    }

    /**
     * El usuario actual tiene el permiso (o bypass de superadmin).
     */
    public static function userCanBypass(): bool
    {
        return auth()->user()?->can(self::PERMISSION) ?? false;
    }

    /**
     * Motivo por el que la fecha no se puede usar, o null si está permitida.
     *
     * @param  bool  $allowBypass  false en el panel de clientes: nunca se aplica el permiso.
     */
    public static function blockReason(CarbonInterface|string|null $date, bool $allowBypass = true): ?string
    {
        if (blank($date) || self::hasService($date)) {
            return null;
        }

        if ($allowBypass && self::userCanBypass()) {
            return null;
        }

        return self::message($date).' Elegí otra fecha.';
    }

    /**
     * Aviso para quien tiene el permiso y elige una fecha sin servicio, o null.
     */
    public static function warning(CarbonInterface|string|null $date): ?string
    {
        if (blank($date) || self::hasService($date)) {
            return null;
        }

        return self::message($date).' Se permite por el permiso "Vender en días sin servicio".';
    }

    /**
     * "El 25/12/2026 es un día sin servicio (Navidad)."
     */
    private static function message(CarbonInterface|string $date): string
    {
        $date = self::toDate($date);
        $reason = self::exceptionFor($date)['reason'] ?? null;

        return sprintf(
            'El %s es un día sin servicio%s.',
            $date->format('d/m/Y'),
            filled($reason) ? " ({$reason})" : '',
        );
    }

    /**
     * Excepciones indexadas por fecha; ante fechas repetidas gana la última.
     *
     * @return array<string, array{date: string, has_service: bool, reason: ?string}>
     */
    private static function exceptionsByDate(): array
    {
        $byDate = [];

        foreach (Setting::serviceExceptions() as $exception) {
            $byDate[self::toDate($exception['date'])->format('Y-m-d')] = $exception;
        }

        return $byDate;
    }

    private static function toDate(CarbonInterface|string $date): CarbonInterface
    {
        return $date instanceof CarbonInterface
            ? $date->copy()->startOfDay()
            : Carbon::parse($date)->startOfDay();
    }
}
