<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bus_id',
        'name',
    ];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    /**
     * Scope para filtrar rutas por colectivo.
     */
    public function scopeForBus(Builder $query, ?int $busId): Builder
    {
        if ($busId === null) {
            return $query;
        }
        return $query->where('bus_id', $busId);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)
            ->orderBy('stop_order');
    }

    /**
     * Primera parada (origen real)
     */
    public function firstStop(): ?RouteStop
    {
        return $this->stops()->first();
    }

    /**
     * Última parada (destino real)
     */
    public function lastStop(): ?RouteStop
    {
        return $this->stops()->latest('stop_order')->first();
    }

    /**
     * Saber si una ubicación pertenece a la ruta
     */
    public function hasLocation(int $locationId): bool
    {
        return $this->stops()
            ->where('location_id', $locationId)
            ->exists();
    }

    public function isValidSegment(int $originId, int $destinationId): bool
    {
        $originOrder = $this->stops()
            ->where('location_id', $originId)
            ->value('stop_order');

        $destinationOrder = $this->stops()
            ->where('location_id', $destinationId)
            ->value('stop_order');

        if (is_null($originOrder) || is_null($destinationOrder)) {
            return false;
        }

        return $originOrder < $destinationOrder;
    }

    public function getSegmentStops(int $originId, int $destinationId)
{
    $originOrder = $this->stops()
        ->where('location_id', $originId)
        ->value('stop_order');

    $destinationOrder = $this->stops()
        ->where('location_id', $destinationId)
        ->value('stop_order');

    return $this->stops()
        ->whereBetween('stop_order', [$originOrder, $destinationOrder])
        ->get();
}

public function schedules(): HasMany
{
    return $this->hasMany(Schedule::class)
        ->orderBy('departure_time');
}

public function activeSchedules(): HasMany
{
    return $this->hasMany(Schedule::class)
        ->where('is_active', true)
        ->orderBy('departure_time');
}

public function trips(): HasMany
{
    return $this->hasMany(Trip::class);
}

    /**
     * Obtener hora de salida de una parada (cuando el colectivo sale de ahí).
     * Centraliza la lógica para evitar duplicar consultas en vistas.
     */
    public function getDepartureTimeForStop(int $locationId, Schedule $schedule): ?Carbon
    {
        $routeStop = $this->stops()->where('location_id', $locationId)->first();

        if (!$routeStop) {
            return $schedule->departure_time;
        }

        return $routeStop->getDepartureTimeForSchedule($schedule) ?? $schedule->departure_time;
    }

    /**
     * Obtener hora de llegada a una parada (cuando el colectivo llega ahí).
     * Centraliza la lógica para evitar duplicar consultas en vistas.
     */
    public function getArrivalTimeForStop(int $locationId, Schedule $schedule): ?Carbon
    {
        $routeStop = $this->stops()->where('location_id', $locationId)->first();

        if (!$routeStop) {
            return $schedule->arrival_time;
        }

        return $routeStop->getArrivalTimeForSchedule($schedule) ?? $schedule->arrival_time;
    }
}
