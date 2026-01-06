<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function routeStops(): HasMany
    {
        return $this->hasMany(RouteStop::class);
    }

    /**
     * Boletos que salen de esta ubicación
     */
    public function originTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'origin_location_id');
    }

    /**
     * Boletos que llegan a esta ubicación
     */
    public function destinationTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'destination_location_id');
    }
}