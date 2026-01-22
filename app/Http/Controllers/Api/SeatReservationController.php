<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeatReservation;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SeatReservationController extends Controller
{
    /**
     * Reservar asientos seleccionados
     */
    public function reserve(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => 'required|integer|exists:trips,id',
            'seat_ids' => 'required|array',
            'seat_ids.*' => 'integer|exists:seats,id',
            'session_id' => 'required|string',
        ]);

        $tripId = $request->input('trip_id');
        $seatIds = $request->input('seat_ids');
        $sessionId = $request->input('session_id');

        // Limpiar reservas expiradas primero
        SeatReservation::cleanupExpired();

        // Liberar reservas anteriores de esta sesión para este viaje
        SeatReservation::where('user_session_id', $sessionId)
            ->where('trip_id', $tripId)
            ->delete();

        // Verificar que el viaje exista y tenga asientos disponibles
        $trip = Trip::find($tripId);
        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Viaje no encontrado'
            ], 404);
        }

        // Verificar disponibilidad actual de asientos
        $availableSeats = $trip->availableSeats()->pluck('id')->toArray();
        $invalidSeats = array_diff($seatIds, $availableSeats);

        if (!empty($invalidSeats)) {
            return response()->json([
                'success' => false,
                'message' => 'Algunos asientos ya no están disponibles',
                'invalid_seats' => $invalidSeats
            ], 409);
        }

        // Intentar reservar los asientos
        $result = SeatReservation::reserveSeats($tripId, $seatIds, $sessionId, 10);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Asientos reservados exitosamente',
                'expires_at' => $result['expires_at']->toISOString(),
                'reserved_seats' => $seatIds
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 409);
        }
    }

    /**
     * Liberar todas las reservas de una sesión
     */
    public function release(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $sessionId = $request->input('session_id');
        SeatReservation::releaseBySession($sessionId);

        return response()->json([
            'success' => true,
            'message' => 'Reservas liberadas exitosamente'
        ]);
    }

    /**
     * Extender tiempo de reservas (keep alive)
     */
    public function keepAlive(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $sessionId = $request->input('session_id');
        $extendedCount = SeatReservation::extendReservationTime($sessionId, 5);

        return response()->json([
            'success' => true,
            'message' => 'Tiempo de reservas extendido',
            'extended_reservations' => $extendedCount
        ]);
    }

    /**
     * Obtener estado actual de reservas de una sesión
     */
    public function status(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'trip_id' => 'required|integer|exists:trips,id',
        ]);

        $sessionId = $request->input('session_id');
        $tripId = $request->input('trip_id');

        // Limpiar reservas expiradas
        SeatReservation::cleanupExpired();

        // Obtener reservas activas de esta sesión para este viaje
        $reservedSeats = SeatReservation::getReservedSeatsBySession($sessionId, $tripId);

        return response()->json([
            'success' => true,
            'reserved_seats' => $reservedSeats,
            'reservation_count' => count($reservedSeats)
        ]);
    }

    /**
     * Limpiar reservas expiradas (endpoint para mantenimiento)
     */
    public function cleanup(): JsonResponse
    {
        $deletedCount = SeatReservation::cleanupExpired();

        return response()->json([
            'success' => true,
            'message' => "Se eliminaron {$deletedCount} reservas expiradas",
            'deleted_count' => $deletedCount
        ]);
    }
}
