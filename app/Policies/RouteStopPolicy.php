<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesModule;

class RouteStopPolicy
{
    use AuthorizesModule;

    protected static function permissionModule(): string
    {
        return 'route_stops';
    }

    /** Arrastrar paradas cambia su orden: es una edición del recorrido. */
    public function reorder(User $user): bool
    {
        return $user->can('route_stops.update');
    }
}
