<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModule;

class SeatPolicy
{
    use AuthorizesModule;

    protected static function permissionModule(): string
    {
        return 'seats';
    }
}
