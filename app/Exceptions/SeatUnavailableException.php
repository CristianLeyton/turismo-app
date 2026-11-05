<?php

namespace App\Exceptions;

use App\Models\Seat;
use Exception;

/**
 * Uno o más asientos dejaron de estar disponibles al crear los boletos.
 * Se lanza dentro de la transacción de la emisión para revertirla completa.
 */
class SeatUnavailableException extends Exception
{
    /**
     * @param  list<string>  $seatNumbers
     */
    public function __construct(public readonly array $seatNumbers)
    {
        parent::__construct(
            'Los siguientes asientos fueron vendidos en el último momento: '
            .implode(', ', $seatNumbers)
            .'. No se emitió ningún boleto; elegí otros asientos e intentá de nuevo.'
        );
    }

    /**
     * @param  array<int, int|string|null>  $seatIds
     */
    public static function forSeatIds(array $seatIds): self
    {
        $seatNumbers = Seat::query()
            ->whereIn('id', array_filter($seatIds))
            ->orderBy('seat_number')
            ->pluck('seat_number')
            ->map(fn ($number): string => (string) $number)
            ->all();

        return new self($seatNumbers);
    }
}
