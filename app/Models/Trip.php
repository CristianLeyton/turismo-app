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

    /**
     * Buscar o crear un viaje para el schedule y fecha dados
     * También valida que el viaje sea válido para el segmento origen-destino
     * 
     * @param int $scheduleId
     * @param string $tripDate Fecha en formato Y-m-d
     * @param int $originLocationId
     * @param int $destinationLocationId
     * @return array ['trip' => Trip|null, 'available_seats' => int, 'has_enough_seats' => bool, 'message' => string]
     */
    public static function findOrCreateForBooking(
        int $scheduleId,
        string $tripDate,
        int $originLocationId,
        int $destinationLocationId
    ): array {
        $schedule = Schedule::findOrFail($scheduleId);
        $route = $schedule->route;

        // Validar que el segmento origen-destino sea válido para esta ruta
        if (!$route->isValidSegment($originLocationId, $destinationLocationId)) {
            return [
                'trip' => null,
                'available_seats' => 0,
                'has_enough_seats' => false,
                'message' => 'El segmento origen-destino no es válido para esta ruta.',
            ];
        }

        // Buscar viaje existente
        $trip = self::query()
            ->whereDate('trip_date', $tripDate)
            ->where('schedule_id', $scheduleId)
            ->first();

        // Si no existe, crearlo
        if (!$trip) {
            // Obtener bus_id: primero intentar de un viaje existente de la ruta
            $busId = $route->trips()
                ->whereNotNull('bus_id')
                ->value('bus_id');

            // Si no hay viajes previos, obtener el primer bus disponible
            if (!$busId) {
                $bus = \App\Models\Bus::query()
                    ->where('seat_count', '>', 0)
                    ->first();

                if (!$bus) {
                    return [
                        'trip' => null,
                        'available_seats' => 0,
                        'has_enough_seats' => false,
                        'message' => 'No hay colectivos disponibles en el sistema.',
                    ];
                }

                $busId = $bus->id;
            }

            $trip = self::create([
                'route_id' => $route->id,
                'schedule_id' => $scheduleId,
                'bus_id' => $busId,
                'trip_date' => $tripDate,
            ]);
        }

        $availableSeats = $trip->remainingSeats();

        return [
            'trip' => $trip,
            'available_seats' => $availableSeats,
            'has_enough_seats' => true, // Se validará después según pasajeros requeridos
            'message' => $availableSeats > 0
                ? "Viaje encontrado. Asientos disponibles: {$availableSeats}"
                : "Viaje encontrado pero no hay asientos disponibles.",
        ];
    }
}
