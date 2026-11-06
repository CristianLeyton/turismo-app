<?php

namespace App\Models;

use App\Models\Concerns\HasShareToken;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Ticket extends Model
{
    use HasShareToken;
    use SoftDeletes;

    protected $fillable = [
        'sale_id',
        'trip_id',
        'return_trip_id',
        'passenger_id',
        'seat_id',
        'bus_id',
        'is_round_trip',
        'is_return_leg',
        'travels_with_child',
        'travels_with_pets',
        'pet_names',
        'pet_count',
        'observation',
        'price',
        'payment_method',
        'origin_location_id',
        'destination_location_id',
        'deleted_by',
        'deletion_reason',
        'share_token',
    ];

    protected $casts = [
        'is_round_trip' => 'boolean',
        'is_return_leg' => 'boolean',
        'travels_with_child' => 'boolean',
        'travels_with_pets' => 'boolean',
    ];

    /** Largo máximo de la observación interna del boleto (como un tweet). */
    public const MAX_OBSERVATION_LENGTH = 280;

    /**
     * Normaliza una observación: recorta espacios y convierte el vacío en null,
     * para que la venta, la edición y el historial comparen igual.
     */
    public static function normalizeObservation(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    /**
     * Observación interna: nunca se muestra al pasajero (PDF, link público ni
     * panel de clientes).
     */
    protected function observation(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): ?string => self::normalizeObservation($value),
        );
    }

    public function getCompanionTypeAttribute()
    {
        if ($this->travels_with_child) {
            return 'Menor';
        } elseif ($this->travels_with_pets) {
            return 'Mascota';
        }

        return 'No';
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

    protected static function booted(): void
    {
        // `is_return_leg` es la única fuente de verdad del tramo de vuelta.
        // Si quien crea el boleto no la indicó, se completa con la convención
        // histórica (diferido + sin viaje de vuelta + precio 0). Una marca
        // explícita (también `false`) nunca se pisa: una ida a precio 0 sigue
        // siendo ida.
        static::creating(function (Ticket $ticket): void {
            if (
                ! array_key_exists('is_return_leg', $ticket->getAttributes())
                && (bool) $ticket->is_round_trip
                && is_null($ticket->return_trip_id)
                && (float) ($ticket->price ?? 0) === 0.0
            ) {
                $ticket->is_return_leg = true;
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
     * ¿El boleto fue vendido sin fecha/horario (viaje pendiente)?
     * El permiso `tickets.vender_sin_fecha` permite crearlos; la fecha y el
     * asiento se asignan después vía reprogramación (tickets.reschedule).
     */
    public function isPendingDate(): bool
    {
        return $this->trip_id === null;
    }

    /**
     * ¿Este boleto representa el tramo de vuelta de un diferido?
     * Solo la marca explícita lo decide: el precio no interviene (una ida a
     * precio 0 sigue siendo ida).
     */
    public function isReturnLeg(): bool
    {
        return (bool) $this->is_return_leg;
    }

    /**
     * ¿Este boleto pertenece a una venta diferida (ida y vuelta)?
     * La venta solo de ida guarda `is_round_trip = false`.
     */
    public function isPartOfRoundTrip(): bool
    {
        return (bool) $this->is_round_trip || (bool) $this->is_return_leg;
    }

    /**
     * ¿Ocupa asiento?
     */
    public function occupiesSeat(): bool
    {
        return ! is_null($this->seat_id);
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

    /**
     * Historial de reprogramaciones de este boleto (una fila por tramo movido).
     */
    public function dateChanges(): HasMany
    {
        return $this->hasMany(TicketDateChange::class);
    }

    /**
     * Historial de ediciones de datos de este boleto (correcciones de venta
     * vía permiso tickets.edit, una fila por campo cambiado).
     */
    public function edits(): HasMany
    {
        return $this->hasMany(TicketEdit::class)->orderBy('created_at');
    }

    /**
     * Fecha de la última actividad registrada del boleto.
     */
    public function getUltimaActividadAttribute(): ?\Illuminate\Support\Carbon
    {
        $dates = collect([
            $this->deleted_at,
            $this->dateChanges->max('created_at'),
            $this->edits->max('created_at'),
        ])->filter();

        return $dates->isEmpty() ? null : $dates->max();
    }

    /**
     * ¿El boleto fue reprogramado alguna vez?
     */
    public function wasRescheduled(): bool
    {
        return $this->dateChanges()->exists();
    }

    /**
     * ¿El tramo de vuelta de este boleto fue reprogramado alguna vez?
     * Las filas leg=return viven bajo el ticket de vuelta (registro hermano de la
     * misma venta y pasajero), así que se buscan allí.
     */
    public function wasReturnLegRescheduled(): bool
    {
        return TicketDateChange::query()
            ->where('leg', TicketDateChange::LEG_RETURN)
            ->whereHas('ticket', fn ($q) => $q
                ->where('sale_id', $this->sale_id)
                ->where('passenger_id', $this->passenger_id))
            ->exists();
    }

    /**
     * Boleto de ida hermano de este tramo de vuelta (misma venta y pasajero).
     * El precio del diferido vive en la ida: la vuelta lista "—" con una nota
     * que referencia ese boleto. Null si no hay hermano (dato reparado/borro).
     */
    public function roundTripOutboundTicket(): ?self
    {
        return self::query()
            ->where('sale_id', $this->sale_id)
            ->where('passenger_id', $this->passenger_id)
            ->where('is_round_trip', true)
            ->where('is_return_leg', false)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Boleto de vuelta hermano de este boleto de ida (misma venta y pasajero).
     * Es la fuente de verdad de la vuelta: refleja su viaje y asiento actuales
     * aunque se haya reprogramado después de la venta. Null si este boleto ya
     * es la vuelta, no es diferido o la vuelta fue borrada.
     */
    public function roundTripReturnTicket(): ?self
    {
        if ($this->isReturnLeg()) {
            return null;
        }

        return self::query()
            ->where('sale_id', $this->sale_id)
            ->where('passenger_id', $this->passenger_id)
            ->where('is_return_leg', true)
            ->whereKeyNot($this->getKey())
            ->first();
    }

    /**
     * Precio para mostrar en PDF y panel.
     * El tramo de vuelta muestra el precio del boleto de ida asociado (mismo
     * pasajero, misma venta), porque el precio del diferido vive en la ida.
     */
    public function getDisplayPriceAttribute(): float
    {
        if (! $this->isReturnLeg()) {
            return (float) $this->price;
        }

        $outbound = $this->outboundLeg();

        return $outbound ? (float) $outbound->price : 0;
    }

    /**
     * Boleto de ida vigente de este tramo de vuelta (mismo pasajero, misma
     * venta), que es el que lleva el precio de la ida y vuelta.
     */
    public function outboundLeg(): ?self
    {
        return self::where('sale_id', $this->sale_id)
            ->where('passenger_id', $this->passenger_id)
            ->where('is_round_trip', true)
            ->where('is_return_leg', false)
            ->first();
    }

    /**
     * Obtener la hora de salida de la parada donde sube el pasajero.
     * Para viaje de ida: usa origin_location_id y trip.
     * Para viaje de vuelta: usa destination_location_id y returnTrip.
     *
     * @param  Trip|null  $tripOverride  Viaje a usar (por defecto: trip del ticket)
     * @param  bool  $forReturnTrip  Si true, usa destination como parada de subida y returnTrip como viaje
     */
    public function getBoardingDepartureTime(?Trip $tripOverride = null, bool $forReturnTrip = false): ?Carbon
    {
        $trip = $tripOverride ?? ($forReturnTrip ? $this->returnTrip : $this->trip);
        if (! $trip?->route || ! $trip->schedule) {
            return null;
        }

        $boardingLocationId = $forReturnTrip ? $this->destination_location_id : $this->origin_location_id;
        if (! $boardingLocationId) {
            return $trip->schedule->departure_time;
        }

        return $trip->route->getDepartureTimeForStop($boardingLocationId, $trip->schedule);
    }

    /**
     * Obtener la hora de llegada a la parada donde baja el pasajero.
     * Para viaje de ida: usa destination_location_id y trip.
     * Para viaje de vuelta: usa origin_location_id y returnTrip.
     *
     * @param  Trip|null  $tripOverride  Viaje a usar
     * @param  bool  $forReturnTrip  Si true, usa origin como parada de bajada (destino del vuelta)
     */
    public function getBoardingArrivalTime(?Trip $tripOverride = null, bool $forReturnTrip = false): ?Carbon
    {
        $trip = $tripOverride ?? ($forReturnTrip ? $this->returnTrip : $this->trip);
        if (! $trip?->route || ! $trip->schedule) {
            return null;
        }

        $alightingLocationId = $forReturnTrip ? $this->origin_location_id : $this->destination_location_id;
        if (! $alightingLocationId) {
            return $trip->schedule->arrival_time;
        }

        return $trip->route->getArrivalTimeForStop($alightingLocationId, $trip->schedule);
    }

    public static function validateTicketCreation(array $data): void
    {
        $trip = Trip::findOrFail($data['trip_id']);

        // Validar que origen y destino sean válidos para la ruta
        if (! $trip->route->isValidSegment($data['origin_location_id'], $data['destination_location_id'])) {
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
            $isSeatAvailable = ! in_array($data['seat_id'], $unavailableSeatIds);

            if (! $isSeatAvailable) {
                throw ValidationException::withMessages([
                    'seat_id' => 'El asiento no está disponible.',
                ]);
            }
        }
    }
}
