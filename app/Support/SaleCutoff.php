<?php

namespace App\Support;

use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Trip;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Venta fuera de término (permiso `tickets.vender_pasado_limite`).
 *
 * Reglas (ambas requieren el permiso para saltárselas, y ambas se desactivan
 * con `tickets.venta_limite_horas = 0`, es decir "sin límite"):
 *
 *  1. Días anteriores: no se venden boletos para viajes con fecha < hoy.
 *  2. Ventana de horas: no se vende cuando el colectivo salió de la parada
 *     donde sube el pasajero hace más de las horas configuradas.
 *
 * Es la única fuente de verdad de los tres puntos de control, para que no
 * diverjan:
 *
 *  - Wizard paso 1 (TicketForm::afterValidation) → Halt antes de avanzar.
 *  - Campos seleccionables (minDate de Fecha de ida y options de Horario,
 *    tanto de ida como de vuelta).
 *  - Guard final al crear el boleto (CreateTicket::assertDepartureWithinLimit).
 */
class SaleCutoff
{
    /**
     * El límite está activo. 0 horas = sin límite = feature desactivada.
     */
    public static function enabled(): bool
    {
        return Setting::ventaLimiteHoras() > 0;
    }

    /**
     * El usuario actual tiene el permiso dedicado (o bypass de superadmin).
     */
    public static function userCanBypass(): bool
    {
        return auth()->user()?->can('tickets.vender_pasado_limite') ?? false;
    }

    /**
     * ¿Hay que aplicarle el límite al usuario actual?
     */
    public static function appliesToCurrentUser(): bool
    {
        return self::enabled() && ! self::userCanBypass();
    }

    /**
     * Fecha mínima seleccionable en el wizard para el usuario actual:
     * hoy si el límite aplica (no se venden días anteriores); un año atrás
     * si tiene permiso o si el límite está desactivado (0 horas).
     */
    public static function minSelectableDate(): CarbonInterface
    {
        return self::appliesToCurrentUser()
            ? now()->startOfDay()
            : now()->subYear()->startOfDay();
    }

    /**
     * Motivo por el que NO se puede vender el tramo, o null si está permitido.
     *
     * @param  int|string|null  $boardingLocationId  parada donde sube el pasajero (origen en la ida, destino en la vuelta)
     */
    public static function blockReasonForTrip(?Trip $trip, int|string|null $boardingLocationId, string $leg): ?string
    {
        if (! self::appliesToCurrentUser() || ! $trip) {
            return null;
        }

        // 1) Días anteriores: el viaje sale en un día anterior a hoy.
        if ($trip->trip_date && $trip->trip_date->lt(today())) {
            return sprintf(
                'El viaje de %s es del %s, una fecha anterior a hoy. Sólo pueden vender boletos para días anteriores los usuarios con el permiso "Vender después del límite de salida".',
                $leg,
                $trip->trip_date->format('d/m/Y'),
            );
        }

        if (blank($boardingLocationId)) {
            return null;
        }

        // 2) Pasadas las horas configuradas desde la salida en la parada de subida.
        $departure = $trip->departureDateTimeForStop((int) $boardingLocationId);
        $hours = Setting::ventaLimiteHoras();

        if (! $departure || now()->lessThanOrEqualTo($departure->copy()->addHours($hours))) {
            return null;
        }

        return sprintf(
            'El colectivo de %s salió el %s. La venta se cierra %s hora(s) después de la salida. Si necesitás emitirlo igual, pedí el permiso "Vender después del límite de salida".',
            $leg,
            $departure->format('d/m/Y H:i'),
            $hours,
        );
    }

    /**
     * Motivo por el que un horario NO debería poder elegirse en el wizard
     * (fecha ya anterior a hoy, o salida + horas fuera de término), o null si
     * está permitido. No requiere que el viaje exista aún: se calcula desde
     * la fecha seleccionada y el horario, igual que Trip::departureDateTimeForStop.
     */
    public static function blockReasonForSchedule(CarbonInterface|string|null $date, ?Schedule $schedule, int|string|null $boardingLocationId): ?string
    {
        if (! self::appliesToCurrentUser() || ! $date || ! $schedule) {
            return null;
        }

        $date = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date);

        // 1) Días anteriores.
        if ($date->copy()->startOfDay()->lt(today())) {
            return sprintf(
                'La fecha seleccionada (%s) es anterior a hoy. Sólo pueden vender boletos para días anteriores los usuarios con el permiso "Vender después del límite de salida".',
                $date->format('d/m/Y'),
            );
        }

        if (blank($boardingLocationId)) {
            return null;
        }

        // 2) Salida + horas ya vencida (mismo cálculo que el viaje resuelto).
        $time = $schedule->route
            ? $schedule->route->getDepartureTimeForStop((int) $boardingLocationId, $schedule)
            : $schedule->departure_time;

        if (! $time) {
            return null;
        }

        $departure = $date->copy()->setTimeFromTimeString($time->format('H:i:s'));
        $hours = Setting::ventaLimiteHoras();

        if (now()->lessThanOrEqualTo($departure->addHours($hours))) {
            return null;
        }

        return sprintf(
            'La salida del %s a las %s ya está fuera del límite de %s hora(s). Si necesitás venderlo, pedí el permiso "Vender después del límite de salida".',
            $date->format('d/m/Y'),
            $departure->format('H:i'),
            $hours,
        );
    }
}
