<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seat extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bus_id',
        'seat_number',
        'is_active',
        'floor',
        'row',
        'column',
        'position',
        'seat_type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'row' => 'integer',
        'column' => 'integer',
    ];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}