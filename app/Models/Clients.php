<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clients extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['dni', 'nombre', 'apellido', 'telefono', 'can_buy', 'comments'];

    }
