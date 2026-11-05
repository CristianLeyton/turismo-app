<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModule;

class LineaPolicy
{
    use AuthorizesModule;

    protected static function permissionModule(): string
    {
        return 'lineas';
    }
}
