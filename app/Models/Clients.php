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

    protected $fillable = ['dni', 'nombre', 'apellido', 'telefono', 'can_buy', 'comments', 'password', 'must_change_password'];

    /** Nunca se serializa en JSON/arrays de la UI. */
    protected $hidden = ['password'];

    protected $casts = [
        // Se asigna texto plano y se guarda ya hasheado (bcrypt).
        'password' => 'hashed',
        'can_buy' => 'boolean',
        'must_change_password' => 'boolean',
    ];

    /** ¿El cliente entró por primera vez y todavía eligió su propia contraseña? */
    public function needsPasswordSetup(): bool
    {
        return (bool) $this->must_change_password;
    }

    /**
     * ¿El ingreso exige una contraseña aparte del DNI? Sólo los que ya
     * eligieron la suya (los nuevos arrancan con la inicial = DNI, así que
     * pedir el ingreso del DNI dos veces no agregaría nada).
     */
    public function hasPersonalPassword(): bool
    {
        return ! $this->needsPasswordSetup();
    }

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
