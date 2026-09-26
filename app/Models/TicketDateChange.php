<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketDateChange extends Model
{
    public const LEG_OUTBOUND = 'outbound';
    public const LEG_RETURN = 'return';

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
}
