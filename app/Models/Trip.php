<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


use App\Models\Seat;

class Trip extends Model
{
    protected $fillable = [
        'bus_id',
        'trip_date',
        'departure_time',
        'arrival_time',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'departure_time' => 'datetime:H:i',
        'arrival_time' => 'datetime:H:i',
    ];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Tickets que ocupan asiento
     */
    public function seatedTickets()
    {
        return $this->tickets()->whereNotNull('seat_id');
    }

    /**
     * Saber si el viaje está completo
     */
    public function isFull(): bool
    {
        return $this->seatedTickets()->count() >= $this->bus->seat_count;
    }

    public function availableSeats()
    {
        return Seat::query()
            ->where('bus_id', $this->bus_id)
            ->where('is_active', true)
            ->whereNotIn('id', function ($query) {
                $query->select('seat_id')
                    ->from('tickets')
                    ->where('trip_id', $this->id)
                    ->whereNotNull('seat_id');
            });
    }

    public function canAssignSeat(?int $seatId): bool
    {
        if (is_null($seatId)) {
            return true;
        }

        return $this->availableSeats()
            ->where('id', $seatId)
            ->exists();
    }

    public function remainingSeats(): int
    {
        return max(
            0,
            $this->bus->seat_count - $this->occupiedSeatsCount()
        );
    }

    public function occupiedSeatsCount(): int
    {
        return $this->tickets()
            ->whereNotNull('seat_id')
            ->count();
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
