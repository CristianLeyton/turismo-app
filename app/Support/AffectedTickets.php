<?php

namespace App\Support;

use App\Models\Route;
use App\Models\Schedule;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

/**
 * Boletos vendidos que verían una hora nueva si se cambia una ruta o un
 * horario.
 *
 * Los boletos no guardan horas: se calculan con el horario del viaje y los
 * minutos de cada parada. Por eso un cambio en horarios o paradas se refleja
 * en los boletos ya vendidos de viajes que todavía no partieron (ida o
 * vuelta). Un viaje parte en su fecha a la hora de salida de su horario.
 */
final class AffectedTickets
{
    public const WARNING = 'Hay :count boletos vendidos en viajes próximos que van a ver la hora nueva.';

    public static function forRoute(Route|int $route): int
    {
        $routeId = $route instanceof Route ? $route->getKey() : $route;

        return self::countWhereTrip(fn (Builder $trip) => $trip->where('route_id', $routeId));
    }

    public static function forSchedule(Schedule|int $schedule): int
    {
        $scheduleId = $schedule instanceof Schedule ? $schedule->getKey() : $schedule;

        return self::countWhereTrip(fn (Builder $trip) => $trip->where('schedule_id', $scheduleId));
    }

    /** Aviso para mostrar al usuario, o null si no hay boletos afectados. */
    public static function warning(int $count): ?string
    {
        if ($count === 0) {
            return null;
        }

        return $count === 1
            ? 'Hay 1 boleto vendido en un viaje próximo que va a ver la hora nueva.'
            : str_replace(':count', (string) $count, self::WARNING);
    }

    /**
     * @param  callable(Builder): Builder  $tripConstraint
     */
    private static function countWhereTrip(callable $tripConstraint): int
    {
        $now = now();

        $upcomingTrip = fn (Builder $trip) => $tripConstraint($trip->where(fn (Builder $query) => $query
            ->whereDate('trip_date', '>', $now->toDateString())
            ->orWhere(fn (Builder $today) => $today
                ->whereDate('trip_date', $now->toDateString())
                ->whereHas('schedule', fn (Builder $schedule) => $schedule
                    ->withTrashed()
                    ->whereTime('departure_time', '>', $now->format('H:i:s'))))));

        return Ticket::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('trip', $upcomingTrip)
                ->orWhereHas('returnTrip', $upcomingTrip))
            ->count();
    }
}
