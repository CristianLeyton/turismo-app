<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'route_id',
        'departure_time',
        'arrival_time',
        'name',
        'is_active',
    ];

    protected $casts = [
        'departure_time' => 'datetime:H:i',
        'arrival_time' => 'datetime:H:i',
        'is_active' => 'boolean',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Formato de hora para mostrar
     */
    public function getFormattedDepartureTimeAttribute(): string
    {
        return $this->departure_time->format('H:i');
    }

    public function getFormattedArrivalTimeAttribute(): string
    {
        return $this->arrival_time ? $this->arrival_time->format('H:i') : '-';
    }

    /**
     * Nombre completo del horario (ej: "08:00 - 12:00" o "Mañana (08:00)")
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->name) {
            return "{$this->name} ({$this->formatted_departure_time} - {$this->formatted_arrival_time})";
        }
        return "{$this->formatted_departure_time} - {$this->formatted_arrival_time}";
    }
}