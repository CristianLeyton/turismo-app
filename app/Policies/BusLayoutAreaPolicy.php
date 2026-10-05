<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModule;

class BusLayoutAreaPolicy
{
    use AuthorizesModule;

    protected static function permissionModule(): string
    {
        return 'bus_layout_areas';
    }
}
