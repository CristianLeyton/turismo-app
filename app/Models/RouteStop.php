<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RouteStop extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'route_id',
        'location_id',
        'stop_order',
        'departure_offset_minutes',
        'arrival_offset_minutes',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Obtener la hora de salida de esta parada para un horario dado.
     * hora_salida_parada = schedule.departure_time + departure_offset_minutes
     */
    public function getDepartureTimeForSchedule(Schedule $schedule): ?Carbon
    {
        if (!$schedule || !$schedule->departure_time) {
            return null;
        }

        $offset = $this->departure_offset_minutes ?? 0;

        return $schedule->departure_time->copy()->addMinutes($offset);
    }

    /**
     * Obtener la hora de llegada a esta parada para un horario dado.
     * hora_llegada_parada = schedule.departure_time + arrival_offset_minutes
     * Fallback: si null, última parada usa schedule.arrival_time; otras usan departure_offset.
     */
    public function getArrivalTimeForSchedule(Schedule $schedule): ?Carbon
    {
        if (!$schedule) {
            return null;
        }

        $offset = $this->arrival_offset_minutes;

        if ($offset === null) {
            if ($schedule->arrival_time && $this->route && $this->route->lastStop()?->id === $this->id) {
                return $schedule->arrival_time;
            }
            $offset = $this->departure_offset_minutes ?? 0;
        }

        return $schedule->departure_time?->copy()->addMinutes($offset);
    }
}