<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Passenger extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'dni',
        'birth_date',
        'phone_number',
        'email',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
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
}
