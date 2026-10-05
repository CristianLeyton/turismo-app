<?php

namespace App\Policies;

use App\Models\Sale;
use App\Policies\Concerns\AuthorizesModule;

/**
 * Policy nueva: Sale no tenía policy (cualquier usuario del panel accedía).
 * Para mantener paridad, el rol Vendedor recibe por defecto todos los
 * permisos del módulo sales.*.
 */
class SalePolicy
{
    use AuthorizesModule;

    protected static function permissionModule(): string
    {
        return 'sales';
    }
}
