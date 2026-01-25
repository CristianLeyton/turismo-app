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
     * Reservar asientos
     */
    public function reserve(Request $request): JsonResponse
    {
        try {
            \Log::info('Reserve endpoint called', [
                'trip_id' => $request->input('trip_id'),
                'seat_ids' => $request->input('seat_ids'),
                'session_id' => $request->input('session_id'),
                'seat_ids_type' => gettype($request->input('seat_ids')),
                'seat_ids_empty' => empty($request->input('seat_ids'))
            ]);

            $request->validate([
                'trip_id' => 'required|integer|exists:trips,id',
                'seat_ids' => 'present|array', // Cambiado de 'required' a 'present'
                'session_id' => 'required|string',
            ]);

            $tripId = $request->input('trip_id');
            $seatIds = $request->input('seat_ids');
            $sessionId = $request->input('session_id');

            // No limpiar reservas expiradas aquí para evitar eliminar reservas recién creadas
            // SeatReservation::cleanupExpired();

            // Si no hay asientos seleccionados, liberar todos los de esta sesión para este viaje
            if (empty($seatIds)) {
                \Log::info('Empty seat_ids detected, deleting reservations');
                
                SeatReservation::where('user_session_id', $sessionId)
                    ->where('trip_id', $tripId)
                    ->delete();

                $response = [
                    'success' => true,
                    'message' => 'Todas las reservas liberadas',
                    'reserved_seats' => []
                ];
                
                \Log::info('Returning empty response', $response);
                
                return response()->json($response);
            }

            // Validar que los seat_ids sean enteros y existan (solo si no está vacío)
            $request->validate([
                'seat_ids.*' => 'integer|exists:seats,id',
            ]);

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

            // Verificar disponibilidad actual de cada asiento individualmente
            $occupiedSeatIds = \App\Models\Ticket::where('trip_id', $tripId)
                ->whereNotNull('seat_id')
                ->pluck('seat_id')
                ->toArray();
                
            $reservedByOthersSeatIds = \App\Models\SeatReservation::where('trip_id', $tripId)
                ->where('expires_at', '>', now())
                ->where('user_session_id', '!=', $sessionId)
                ->pluck('seat_id')
                ->toArray();
            
            $unavailableSeatIds = array_merge($occupiedSeatIds, $reservedByOthersSeatIds);
            $availableSeats = \App\Models\Seat::where('bus_id', $trip->bus_id)
                ->where('is_active', true)
                ->whereNotIn('id', $unavailableSeatIds)
                ->pluck('id')
                ->toArray();
            
            // Verificar cada asiento individualmente para dar mensajes específicos
            $invalidSeats = array_diff($seatIds, $availableSeats);
            $occupiedSeats = array_intersect($seatIds, $occupiedSeatIds);
            $reservedSeats = array_intersect($seatIds, $reservedByOthersSeatIds);
            
            if (!empty($invalidSeats)) {
                $message = 'Algunos asientos ya no están disponibles. ';
                
                if (!empty($occupiedSeats)) {
                    $occupiedSeatNumbers = \App\Models\Seat::whereIn('id', $occupiedSeats)
                        ->pluck('seat_number')
                        ->toArray();
                    $message .= 'Asientos vendidos: ' . implode(', ', $occupiedSeatNumbers) . '. ';
                }
                
                if (!empty($reservedSeats)) {
                    $reservedSeatNumbers = \App\Models\Seat::whereIn('id', $reservedSeats)
                        ->pluck('seat_number')
                        ->toArray();
                    $message .= 'Los siguientes asientos están reservados por otros usuarios: ' . implode(', ', $reservedSeatNumbers);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => trim($message),
                    'invalid_seats' => $invalidSeats,
                    'occupied_seats' => $occupiedSeats,
                    'reserved_by_others' => $reservedSeats
                ], 409);
            }

            // Intentar reservar los asientos
            $result = SeatReservation::reserveSeats($tripId, $seatIds, $sessionId, 5);

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
        } catch (\Exception $e) {
            \Log::error('Exception in reserve endpoint', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
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
        
        // Obtener la fecha de expiración más lejana antes de extender
        $latestReservation = SeatReservation::where('user_session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->orderBy('expires_at', 'desc')
            ->first();
        
        $extendedCount = SeatReservation::extendReservationTime($sessionId, 5);
        
        // Obtener la nueva fecha de expiración
        $newExpiresAt = null;
        if ($extendedCount > 0) {
            $newReservation = SeatReservation::where('user_session_id', $sessionId)
                ->where('expires_at', '>', now())
                ->orderBy('expires_at', 'desc')
                ->first();
            
            if ($newReservation) {
                $newExpiresAt = $newReservation->expires_at;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Tiempo de reservas extendido',
            'extended_reservations' => $extendedCount,
            'expires_at' => $newExpiresAt ? $newExpiresAt->toISOString() : null
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

        // No limpiar reservas expiradas aquí para evitar eliminar reservas recién creadas
        // SeatReservation::cleanupExpired();

        // Obtener reservas activas de esta sesión para este viaje
        $reservedSeats = SeatReservation::getReservedSeatsBySession($sessionId, $tripId);

        // Obtener fecha de expiración más lejana
        $expiresAt = null;
        if (!empty($reservedSeats)) {
            $latestReservation = SeatReservation::where('user_session_id', $sessionId)
                ->where('trip_id', $tripId)
                ->orderBy('expires_at', 'desc')
                ->first();
            
            if ($latestReservation) {
                $expiresAt = $latestReservation->expires_at;
            }
        }

        return response()->json([
            'success' => true,
            'reserved_seats' => $reservedSeats,
            'reservation_count' => count($reservedSeats),
            'expires_at' => $expiresAt
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
