<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
    public static function reserveSeats(int $tripId, array $seatIds, string $sessionId, int $minutes = 10): array
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
        
        try {
            static::insert($reservations);
            return ['success' => true, 'expires_at' => $expiresAt];
        } catch (\Illuminate\Database\QueryException $e) {
            // Error de constraint unique - asiento ya reservado
            if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'unique_trip_seat_reservation')) {
                return ['success' => false, 'message' => 'Algunos asientos ya están reservados por otro usuario'];
            }
            throw $e;
        }
    }

    /**
     * Liberar todas las reservas de una sesión
     */
    public static function releaseBySession(string $sessionId): void
    {
        static::where('user_session_id', $sessionId)->delete();
    }

    /**
     * Limpiar reservas expiradas
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }

    /**
     * Extender tiempo de reservas de una sesión
     */
    public static function extendReservationTime(string $sessionId, int $minutes = 5): int
    {
        return static::where('user_session_id', $sessionId)
            ->where('expires_at', '>', now()) // Solo extender las que aún no expiraron
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
