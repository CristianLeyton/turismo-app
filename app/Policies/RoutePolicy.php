<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModule;

class RoutePolicy
{
    use AuthorizesModule;

    protected static function permissionModule(): string
    {
        return 'routes';
    }
}
