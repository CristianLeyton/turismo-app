<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{

    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

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
}
