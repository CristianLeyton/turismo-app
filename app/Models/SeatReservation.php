<?php

namespace App\Models;

use App\Support\SeatConflict;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class SeatReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'seat_id',
        'user_session_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Reservar múltiples asientos para un viaje
     */
    public static function reserveSeats(int $tripId, array $seatIds, string $sessionId, int $minutes = 5): array
    {
        $expiresAt = now()->addMinutes($minutes);
        $reservations = [];

        foreach ($seatIds as $seatId) {
            $reservations[] = [
                'trip_id' => $tripId,
                'seat_id' => $seatId,
                'user_session_id' => $sessionId,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Una reserva vencida no ocupa el asiento: se elimina para que el índice
        // único (viaje, asiento) no bloquee la nueva, haya corrido o no la limpieza.
        static::query()
            ->where('trip_id', $tripId)
            ->whereIn('seat_id', $seatIds)
            ->where('expires_at', '<=', now())
            ->delete();

        try {
            static::insert($reservations);

            return ['success' => true, 'expires_at' => $expiresAt];
        } catch (QueryException $e) {
            if (SeatConflict::isReservationViolation($e)) {
                return ['success' => false, 'message' => 'Algunos asientos ya están reservados por otro usuario'];
            }
            throw $e;
        }
    }

    /**
     * Liberar las reservas de una sesión: todas, o solo las de los viajes indicados.
     *
     * @param  array<int>|null  $tripIds
     */
    public static function releaseBySession(string $sessionId, ?array $tripIds = null): void
    {
        static::query()
            ->where('user_session_id', $sessionId)
            ->when($tripIds !== null, fn ($query) => $query->whereIn('trip_id', $tripIds))
            ->delete();
    }

    /**
     * Limpiar reservas expiradas
     */
    public static function cleanupExpired(): int
    {
        $nowWithOffset = now();
        $deletedCount = static::where('expires_at', '<', $nowWithOffset)->delete();

        if ($deletedCount > 0) {
            \Log::info("CleanupExpired: Eliminadas {$deletedCount} reservas expiradas. Hora UTC: ".now()->format('Y-m-d H:i:s'));
        }

        return $deletedCount;
    }

    /**
     * Extender las reservas vigentes de una sesión: todas, o solo las de los
     * viajes indicados. Las ya vencidas no se renuevan.
     *
     * @param  array<int>|null  $tripIds
     */
    public static function extendReservationTime(string $sessionId, int $minutes = 5, ?array $tripIds = null): int
    {
        return static::query()
            ->where('user_session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->when($tripIds !== null, fn ($query) => $query->whereIn('trip_id', $tripIds))
            ->update(['expires_at' => now()->addMinutes($minutes)]);
    }

    /**
     * Obtener asientos reservados por sesión
     */
    public static function getReservedSeatsBySession(string $sessionId, int $tripId): array
    {
        return static::where('user_session_id', $sessionId)
            ->where('trip_id', $tripId)
            ->where('expires_at', '>', now())
            ->pluck('seat_id')
            ->toArray();
    }

    /**
     * Verificar si un asiento está reservado por la sesión actual
     */
    public static function isSeatReservedBySession(int $tripId, int $seatId, string $sessionId): bool
    {
        return static::where('trip_id', $tripId)
            ->where('seat_id', $seatId)
            ->where('user_session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Verificar si un asiento está reservado
     */
    public static function isSeatReserved(int $tripId, int $seatId): bool
    {
        return static::where('trip_id', $tripId)
            ->where('seat_id', $seatId)
            ->where('expires_at', '>', now())
            ->exists();
    }
}
