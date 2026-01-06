<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'sale_id',
        'trip_id',
        'return_trip_id',
        'passenger_id',
        'seat_id',
        'is_round_trip',
        'travels_with_child',
        'price',
    ];

    protected $casts = [
        'is_round_trip' => 'boolean',
        'travels_with_child' => 'boolean',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function returnTrip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'return_trip_id');
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /**
     * ¿Ocupa asiento?
     */
    public function occupiesSeat(): bool
    {
        return !is_null($this->seat_id);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }
}
