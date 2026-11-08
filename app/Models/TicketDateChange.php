<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketDateChange extends Model
{
    public const LEG_OUTBOUND = 'outbound';

    public const LEG_RETURN = 'return';

    /** Reprogramación hecha por un usuario del panel de administración. */
    public const ACTOR_USER = 'user';

    /** Reprogramación hecha por un cliente desde su panel. */
    public const ACTOR_CLIENT = 'client';

    protected $fillable = [
        'ticket_id',
        'leg',
        'from_trip_id',
        'to_trip_id',
        'from_schedule_id',
        'to_schedule_id',
        'from_seat_id',
        'to_seat_id',
        'user_id',
        'actor_type',
        'client_id',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function fromTrip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'from_trip_id');
    }

    public function toTrip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'to_trip_id');
    }

    public function fromSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'from_schedule_id');
    }

    public function toSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'to_schedule_id');
    }

    public function fromSeat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'from_seat_id');
    }

    public function toSeat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'to_seat_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Texto de "Realizado por" del detalle del Historial: el usuario con su
     * acceso, "Cliente" (con su nombre si se lo conoce), o "Usuario
     * eliminado del sistema" solo cuando lo hizo un usuario que ya no existe.
     */
    public function actorLabel(): string
    {
        if ($this->actor_type === self::ACTOR_CLIENT) {
            $name = $this->client?->getFilamentName();

            return filled($name) ? "Cliente {$name}" : 'Cliente';
        }

        if ($this->user === null) {
            return 'Usuario eliminado del sistema';
        }

        return $this->user->name.($this->user->username ? " ({$this->user->username})" : '');
    }

    /**
     * Cliente que hizo el cambio (si se lo conoce). Incluye clientes dados de
     * baja: el historial sigue mostrando su nombre.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Clients::class)->withTrashed();
    }
}
