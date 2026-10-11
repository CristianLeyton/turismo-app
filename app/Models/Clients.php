<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Clients extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['dni', 'nombre', 'apellido', 'telefono', 'can_buy', 'comments'];

    /**
     * Pasajeros históricos con el DNI de este cliente. El DNI es la clave de
     * unión entre el padron de clientes y las ventas históricas (los boletos
     * referencian un pasajero, no un cliente).
     */
    public function passengers(): HasMany
    {
        return $this->hasMany(Passenger::class, 'dni', 'dni');
    }

    /**
     * Boletos históricos del cliente (via pasajeros con su DNI).
     * Incluye ida, vuelta y boletos pendientes sin fecha.
     */
    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(
            Ticket::class,
            Passenger::class,
            'dni',          // passengers.dni
            'passenger_id', // tickets.passenger_id
            'dni',          // clients.dni
            'id',           // passengers.id
        );
    }
}
