<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModule;

class LocationPolicy
{
    use AuthorizesModule;

    protected static function permissionModule(): string
    {
        return 'locations';
    }
}
