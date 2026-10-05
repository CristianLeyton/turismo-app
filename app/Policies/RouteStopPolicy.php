<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModule;

class RouteStopPolicy
{
    use AuthorizesModule;

    protected static function permissionModule(): string
    {
        return 'route_stops';
    }
}
