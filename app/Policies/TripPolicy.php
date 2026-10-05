<?php

namespace App\Policies;

use App\Models\Trip;
use App\Policies\Concerns\AuthorizesModule;

/**
 * Policy nueva: Trip no tenía policy (cualquier usuario del panel accedía).
 * Para mantener paridad, el rol Vendedor recibe por defecto todos los
 * permisos del módulo trips.*.
 */
class TripPolicy
{
    use AuthorizesModule;

    protected static function permissionModule(): string
    {
        return 'trips';
    }
}
