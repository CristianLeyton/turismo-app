<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Seat;

class Trip extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'route_id',        // Agregar esto
        'schedule_id',     // Nuevo
        'bus_id',
        'trip_date',
    ];

    protected $casts = [
        'trip_date' => 'date',
    ];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
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

    public function getDepartureTimeAttribute($value)
    {
        if ($this->schedule && $this->schedule->departure_time) {
            return $this->schedule->departure_time;
        }
        return $value;
    }

    /**
     * Obtener hora de llegada (del schedule si existe, sino del campo)
     */
    public function getArrivalTimeAttribute($value)
    {
        if ($this->schedule && $this->schedule->arrival_time) {
            return $this->schedule->arrival_time;
        }
        return $value;
    }
}
