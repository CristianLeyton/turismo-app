<?php

namespace App\Exceptions;

use App\Models\Ticket;
use App\Models\Trip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Una persona (por DNI) ya tiene boleto activo en el viaje de la operación.
 *
 * Hereda de ValidationException para que los llamadores que ya muestran
 * errores de validación (panel de clientes) vean el mensaje genérico. Las
 * páginas de administración la capturan antes y usan los conflictos para
 * armar la alerta con "Ver boleto existente".
 */
class DuplicatePassengerOnTripException extends ValidationException
{
    public const MESSAGE = 'Ya tenés un boleto en ese viaje. Elegí otra fecha u horario.';

    /**
     * @param  Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>  $conflicts
     */
    public function __construct(private readonly Collection $conflicts, string $field = 'trip')
    {
        $validator = Validator::make([], []);
        $validator->errors()->add($field, self::MESSAGE);

        parent::__construct($validator);
    }

    /**
     * @return Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>
     */
    public function conflicts(): Collection
    {
        return $this->conflicts;
    }
}
