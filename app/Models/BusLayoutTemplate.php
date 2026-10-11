<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plantilla de layout reutilizable: guarda el diseño completo (asientos +
 * áreas) para aplicarlo a nuevos colectivos en un clic.
 */
class BusLayoutTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'floors',
        'layout',
    ];

    protected $casts = [
        'floors' => 'integer',
        'layout' => 'array',
    ];
}
