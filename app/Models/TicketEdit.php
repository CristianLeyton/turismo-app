<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

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

    /**
     * Campos que viven en el pasajero, no en el boleto: la ida y la vuelta de
     * una venta comparten pasajero, así que editarlos desde un boleto cambia
     * también el otro. `travels_with_child` queda fuera: es una marca de cada
     * tramo.
     *
     * @var list<string>
     */
    public const PASSENGER_FIELDS = [
        self::FIELD_FIRST_NAME,
        self::FIELD_LAST_NAME,
        self::FIELD_DNI,
        self::FIELD_PHONE,
        self::FIELD_CHILD_NAME,
        self::FIELD_CHILD_DNI,
    ];

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

    /**
     * Ediciones que se muestran en el historial de un boleto: las propias y
     * los cambios de datos del pasajero hechos desde otro boleto del mismo
     * pasajero en la misma venta (su ida o su vuelta), aunque esté eliminado.
     *
     * @return Collection<int, TicketEdit>
     */
    public static function visibleFor(Ticket $ticket): Collection
    {
        return static::query()
            ->with('user')
            ->where(fn (Builder $query) => $query
                ->where('ticket_id', $ticket->id)
                ->orWhere(fn (Builder $shared) => $shared
                    ->whereIn('field', self::PASSENGER_FIELDS)
                    ->whereIn('ticket_id', Ticket::withTrashed()
                        ->select('id')
                        ->where('passenger_id', $ticket->passenger_id)
                        ->where('sale_id', $ticket->sale_id))))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Misma regla que visibleFor(), como subconsulta correlacionada con la
     * tabla `tickets` de la consulta exterior: para listas y filtros de la
     * Auditoría sin cargar ediciones por fila.
     */
    public static function visibleForOuterTicket(): QueryBuilder
    {
        return static::query()
            ->from('ticket_edits as visible_edits')
            ->join('tickets as edited_tickets', 'edited_tickets.id', '=', 'visible_edits.ticket_id')
            ->where(fn (Builder $query) => $query
                ->whereColumn('visible_edits.ticket_id', 'tickets.id')
                ->orWhere(fn (Builder $shared) => $shared
                    ->whereIn('visible_edits.field', self::PASSENGER_FIELDS)
                    ->whereColumn('edited_tickets.passenger_id', 'tickets.passenger_id')
                    ->whereColumn('edited_tickets.sale_id', 'tickets.sale_id')))
            ->toBase();
    }
}
