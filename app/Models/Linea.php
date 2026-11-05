<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Linea extends Model
{
    /** @use HasFactory<\Database\Factories\LineaFactory> */
    use HasFactory;

    protected $table = 'lineas';

    protected $fillable = [
        'name',
    ];

    /**
     * Colectivos que pertenecen a la línea (regulares y de refuerzo).
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class, 'linea_id');
    }
}
