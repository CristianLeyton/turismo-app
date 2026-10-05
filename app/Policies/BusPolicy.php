<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModule;

class BusPolicy
{
    use AuthorizesModule;

    protected static function permissionModule(): string
    {
        return 'buses';
    }
}
