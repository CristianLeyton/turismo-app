<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * Reconoce las violaciones de unicidad de asientos (boletos vigentes y
 * reservas temporales) sin depender del motor: MySQL nombra el índice en el
 * mensaje y SQLite nombra las columnas.
 */
class SeatConflict
{
    public const INDEX = 'tickets_active_trip_seat_unique';

    public const RESERVATION_INDEX = 'unique_trip_seat_reservation';

    public static function isActiveSeatViolation(QueryException $exception): bool
    {
        if ((string) $exception->getCode() !== '23000') {
            return false;
        }

        $message = $exception->getMessage();

        if (str_contains($message, self::INDEX)) {
            return true;
        }

        return str_contains($message, 'UNIQUE constraint failed')
            && str_contains($message, 'tickets.trip_id')
            && str_contains($message, 'tickets.seat_id');
    }

    /**
     * Reconoce la violación de "una sola reserva por viaje y asiento"
     * (índice único unique_trip_seat_reservation de seat_reservations).
     */
    public static function isReservationViolation(QueryException $exception): bool
    {
        if ((string) $exception->getCode() !== '23000') {
            return false;
        }

        $message = $exception->getMessage();

        if (str_contains($message, self::RESERVATION_INDEX)) {
            return true;
        }

        return str_contains($message, 'UNIQUE constraint failed')
            && str_contains($message, 'seat_reservations.trip_id')
            && str_contains($message, 'seat_reservations.seat_id');
    }
}
