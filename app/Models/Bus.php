<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bus extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'plate',
        'seat_count',
    ];

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Asientos activos del colectivo
     */
    public function activeSeats()
    {
        return $this->seats()->where('is_active', true);
    }
}
