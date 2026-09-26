<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Trip;
use Carbon\Carbon;

/**
 * Calcula los horarios estipulados de partida y llegada de un tramo de viaje.
 *
 * Centraliza el cálculo usado por el resumen del wizard de venta (summary)
 * y el encabezado de pasos intermedios (wizard-step-header), para que ambos
 * muestren siempre la misma hora.
 *
 * La hora se toma de la parada real del pasajero en la ruta
 * (Route::getDepartureTimeForStop / getArrivalTimeForStop, que respetan los
 * offsets de cada parada según el schedule) con fallback a los horarios
 * generales del schedule.
 */
class TripTimesService
{
    /**
     * Horarios estipulados de un tramo: embarque en $boardingLocationId y
     * descenso en $alightingLocationId.
     *
     * Para la ida: boarding = origen de la venta, alighting = destino.
     * Para la vuelta: boarding = destino de la venta, alighting = origen.
     *
     * @param  Trip|null  $trip  Viaje seleccionado (resuelve la ruta con prioridad).
     * @param  Schedule|null  $schedule  Horario seleccionado (fallback de ruta y de horas).
     * @param  int|string|null  $boardingLocationId  Ubicación donde el pasajero sube.
     * @param  int|string|null  $alightingLocationId  Ubicación donde el pasajero baja.
     * @return array{departure: ?Carbon, arrival: ?Carbon}
     */
    public function getLegTimes(?Trip $trip, ?Schedule $schedule, int|string|null $boardingLocationId, int|string|null $alightingLocationId): array
    {
        $route = $trip?->route ?? $schedule?->route;

        return [
            'departure' => $route && $schedule && filled($boardingLocationId)
                ? $route->getDepartureTimeForStop((int) $boardingLocationId, $schedule)
                : $schedule?->departure_time,
            'arrival' => $route && $schedule && filled($alightingLocationId)
                ? $route->getArrivalTimeForStop((int) $alightingLocationId, $schedule)
                : $schedule?->arrival_time,
        ];
    }
}
