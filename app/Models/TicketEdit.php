<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila por cada campo editado de un boleto (auditoría de correcciones
 * de venta). Semanticamente distinto de TicketDateChange (reprogramación).
 */
class TicketEdit extends Model
{
    public const FIELD_FIRST_NAME = 'first_name';
    public const FIELD_LAST_NAME = 'last_name';
    public const FIELD_DNI = 'dni';
    public const FIELD_PHONE = 'phone_number';
    public const FIELD_TRAVELS_WITH_CHILD = 'travels_with_child';
    public const FIELD_CHILD_NAME = 'child_name';
    public const FIELD_CHILD_DNI = 'child_dni';
    public const FIELD_TRAVELS_WITH_PETS = 'travels_with_pets';
    public const FIELD_PET_NAMES = 'pet_names';
    public const FIELD_PET_COUNT = 'pet_count';
    public const FIELD_PAYMENT_METHOD = 'payment_method';
    public const FIELD_PRICE = 'price';

    protected $fillable = [
        'ticket_id',
        'field',
        'field_label',
        'old_value',
        'new_value',
        'reason',
        'user_id',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
