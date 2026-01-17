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

    /**
     * Verificar y reservar asientos usando bloqueo pesimista para evitar condiciones de carrera
     * 
     * @param array $seatIds Array de IDs de asientos a reservar
     * @return array ['success' => bool, 'message' => string, 'failed_seats' => array]
     */
    public function reserveSeatsWithLock(array $seatIds): array
    {
        if (empty($seatIds)) {
            return ['success' => true, 'message' => 'No hay asientos que reservar', 'failed_seats' => []];
        }

        return \DB::transaction(function () use ($seatIds) {
            // Bloquear el viaje actual para evitar modificaciones concurrentes
            $lockedTrip = self::where('id', $this->id)->lockForUpdate()->first();

            if (!$lockedTrip) {
                return ['success' => false, 'message' => 'Viaje no encontrado', 'failed_seats' => $seatIds];
            }

            $failedSeats = [];
            $availableSeats = $lockedTrip->availableSeats()->pluck('id')->toArray();

            foreach ($seatIds as $seatId) {
                if (!in_array($seatId, $availableSeats)) {
                    $failedSeats[] = $seatId;
                }
            }

            if (!empty($failedSeats)) {
                return [
                    'success' => false, 
                    'message' => 'Algunos asientos ya no están disponibles', 
                    'failed_seats' => $failedSeats
                ];
            }

            return ['success' => true, 'message' => 'Asientos disponibles', 'failed_seats' => []];
        });
    }

    /**
     * Crear tickets con bloqueo para evitar duplicados
     * 
     * @param array $ticketsData Array de datos para crear tickets
     * @return array ['success' => bool, 'message' => string, 'tickets' => Collection]
     */
    public function createTicketsWithLock(array $ticketsData): array
    {
        return \DB::transaction(function () use ($ticketsData) {
            $createdTickets = collect();
            $failedTickets = [];

            foreach ($ticketsData as $index => $ticketData) {
                try {
                    // Verificar que el asiento esté disponible antes de crear
                    if (isset($ticketData['seat_id']) && !$this->canAssignSeat($ticketData['seat_id'])) {
                        $failedTickets[] = [
                            'index' => $index,
                            'seat_id' => $ticketData['seat_id'],
                            'error' => 'Asiento ya no disponible'
                        ];
                        continue;
                    }

                    $ticket = $this->tickets()->create($ticketData);
                    $createdTickets->push($ticket);

                } catch (\Illuminate\Database\QueryException $e) {
                    // Capturar error de constraint unique (duplicate entry)
                    if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'tickets_trip_seat_unique')) {
                        $failedTickets[] = [
                            'index' => $index,
                            'seat_id' => $ticketData['seat_id'] ?? null,
                            'error' => 'Asiento acaba de ser vendido por otro usuario'
                        ];
                    } else {
                        // Re-lanzar otros errores de base de datos
                        throw $e;
                    }
                }
            }

            if (!empty($failedTickets)) {
                return [
                    'success' => false,
                    'message' => 'Algunos asientos no pudieron ser vendidos',
                    'tickets' => $createdTickets,
                    'failed_tickets' => $failedTickets
                ];
            }

            return [
                'success' => true,
                'message' => 'Todos los tickets fueron creados exitosamente',
                'tickets' => $createdTickets,
                'failed_tickets' => []
            ];
        });
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

    /**
     * Obtener todos los asientos del bus organizados por piso para el layout visual
     * Si no tienen row/column configurados, los infiere automáticamente
     * 
     * @return array ['floor_1' => [...], 'floor_2' => [...], ...]
     */
    public function getSeatsLayout(): array
    {
        if (!$this->bus) {
            return [];
        }

        // Obtener IDs de asientos ocupados en este viaje
        $occupiedSeatIds = $this->tickets()
            ->whereNotNull('seat_id')
            ->pluck('seat_id')
            ->toArray();

        // Obtener todos los asientos del bus activos, ordenados por piso y número
        $allSeats = Seat::query()
            ->where('bus_id', $this->bus_id)
            ->where('is_active', true)
            ->orderBy('floor')
            ->orderBy('seat_number')
            ->get();

        // Agrupar por piso
        $layout = [];
        
        foreach ($allSeats as $seat) {
            $floor = $seat->floor ?? '1';
            $floorKey = 'floor_' . $floor;
            
            if (!isset($layout[$floorKey])) {
                $layout[$floorKey] = [];
            }

            // Si no tiene row/column configurados, inferirlos basándose en el número de asiento
            // Asumimos 4 columnas por fila (2 a cada lado del pasillo) como layout estándar
            $row = $seat->row;
            $column = $seat->column;
            
            if ($row === null || $column === null) {
                // Inferir posición: asumimos layout estándar de 4 columnas (2-2)
                // Primero piso: asientos 1-48, segundo piso: asientos 49-60
                $seatNum = $seat->seat_number;
                $seatsPerRow = 4; // 2 izquierda, pasillo, 2 derecha
                
                // Calcular fila y columna inferidas
                $inferredRow = (int) ceil(($seatNum - 1) / $seatsPerRow);
                $positionInRow = (($seatNum - 1) % $seatsPerRow) + 1;
                
                // Mapear posición a columna: 1,2 -> left (0,1), 3,4 -> right (2,3)
                if ($positionInRow <= 2) {
                    $inferredColumn = $positionInRow - 1; // 0, 1
                    $inferredPosition = 'left';
                } else {
                    $inferredColumn = $positionInRow - 1; // 2, 3
                    $inferredPosition = 'right';
                }
                
                $row = $row ?? $inferredRow;
                $column = $column ?? $inferredColumn;
                $position = $seat->position ?? $inferredPosition;
            } else {
                $position = $seat->position ?? 'normal';
            }

            $layout[$floorKey][] = [
                'id' => $seat->id,
                'seat_number' => $seat->seat_number,
                'row' => $row ?? 0,
                'column' => $column ?? 0,
                'position' => $position ?? 'normal',
                'seat_type' => $seat->seat_type ?? 'normal',
                'is_occupied' => in_array($seat->id, $occupiedSeatIds),
                'is_available' => !in_array($seat->id, $occupiedSeatIds),
            ];
        }

        return $layout;
    }

    /**
     * Obtener áreas especiales del layout del bus organizadas por piso
     * 
     * @return array ['floor_1' => [...], 'floor_2' => [...], ...]
     */
    public function getLayoutAreas(): array
    {
        if (!$this->bus) {
            return [];
        }

        $areas = $this->bus->layoutAreas()->get();
        $layoutAreas = [];

        foreach ($areas as $area) {
            $floor = $area->floor ?? '1';
            $floorKey = 'floor_' . $floor;
            
            if (!isset($layoutAreas[$floorKey])) {
                $layoutAreas[$floorKey] = [];
            }

            $layoutAreas[$floorKey][] = [
                'id' => $area->id,
                'area_type' => $area->area_type,
                'label' => $area->label ?? strtoupper($area->area_type),
                'row_start' => $area->row_start,
                'row_end' => $area->row_end,
                'column_start' => $area->column_start,
                'column_end' => $area->column_end,
                'span_rows' => $area->span_rows ?? 1,
                'span_columns' => $area->span_columns ?? 1,
            ];
        }

        return $layoutAreas;
    }

    /**
     * Obtener información completa del layout para el selector de asientos
     * 
     * @return array
     */
    public function getFullLayoutData(): array
    {
        return [
            'bus_id' => $this->bus_id,
            'bus_name' => $this->bus?->name ?? 'N/A',
            'floors' => $this->bus?->floors ?? 1,
            'seats' => $this->getSeatsLayout(),
            'areas' => $this->getLayoutAreas(),
            'total_seats' => $this->bus?->seat_count ?? 0,
            'available_seats' => $this->remainingSeats(),
            'occupied_seats' => $this->occupiedSeatsCount(),
        ];
    }
}
