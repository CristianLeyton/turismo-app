<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;


class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sale_id',
        'trip_id',
        'return_trip_id',
        'passenger_id',
        'seat_id',
        'is_round_trip',
        'travels_with_child',
        'travels_with_pets',
        'pet_names',
        'pet_count',
        'price',
        'payment_method',
        'origin_location_id',
        'destination_location_id',
        'deleted_by',
    ];

    protected $casts = [
        'is_round_trip' => 'boolean',
        'travels_with_child' => 'boolean',
        'travels_with_pets' => 'boolean',
    ];

    public function getCompanionTypeAttribute()
    {
        if ($this->travels_with_child) {
            return 'Menor';
        } elseif ($this->travels_with_pets) {
            return 'Mascota';
        }
        return "No";
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($ticket) {
            if (Auth::check()) {
                $ticket->deleted_by = Auth::id();
                $ticket->save();
            }
        });
    }

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

    public function returnSeat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'return_seat_id');
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

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public static function validateTicketCreation(array $data): void
    {
        $trip = Trip::findOrFail($data['trip_id']);

        // Validar que origen y destino sean válidos para la ruta
        if (!$trip->route->isValidSegment($data['origin_location_id'], $data['destination_location_id'])) {
            throw ValidationException::withMessages([
                'origin_location_id' => 'El origen y destino no son válidos para esta ruta.',
            ]);
        }

        // Validar que el viaje no esté completo
        if ($trip->isFull()) {
            throw ValidationException::withMessages([
                'trip_id' => 'El viaje está completo.',
            ]);
        }

        // Validar asiento si se asigna
        if (isset($data['seat_id'])) {
            // Obtener asientos realmente disponibles (excluyendo ocupados y reservas de otros)
            $sessionId = session()->getId();

            $occupiedSeatIds = \App\Models\Ticket::where('trip_id', $data['trip_id'])
                ->whereNotNull('seat_id')
                ->whereNull('deleted_at')
                ->pluck('seat_id')
                ->toArray();

            $reservedByOthersSeatIds = \App\Models\SeatReservation::where('trip_id', $data['trip_id'])
                ->where('expires_at', '>', now())
                ->where('user_session_id', '!=', $sessionId)
                ->pluck('seat_id')
                ->toArray();

            $unavailableSeatIds = array_merge($occupiedSeatIds, $reservedByOthersSeatIds);
            $isSeatAvailable = !in_array($data['seat_id'], $unavailableSeatIds);

            if (!$isSeatAvailable) {
                throw ValidationException::withMessages([
                    'seat_id' => 'El asiento no está disponible.',
                ]);
            }
        }
    }
}
