<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Passenger extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'dni',
        'phone_number',
        'email',
        'parent_passenger_id',
        'passenger_type',
    ];

    protected $casts = [
        'passenger_type' => 'string',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Passenger::class, 'parent_passenger_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Passenger::class, 'parent_passenger_id');
    }

    /**
     * Edad calculada
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date
            ? $this->birth_date->age
            : null;
    }

    /**
     * Scope para obtener solo adultos
     */
    public function scopeAdults($query)
    {
        return $query->where('passenger_type', 'adult');
    }

    /**
     * Scope para obtener solo menores
     */
    public function scopeChildren($query)
    {
        return $query->where('passenger_type', 'child');
    }

    /**
     * Verificar si es un pasajero adulto
     */
    public function isAdult(): bool
    {
        return $this->passenger_type === 'adult';
    }

    /**
     * Verificar si es un pasajero menor de edad
     */
    public function isChild(): bool
    {
        return $this->passenger_type === 'child';
    }

    /**
     * Obtener nombre completo
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
