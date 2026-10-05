<?php

namespace App\Filament\Clusters\Users;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * Cluster de administración de cuentas: Usuarios y Roles y permisos.
 */
class UsersCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected static ?string $clusterBreadcrumb = 'Usuarios';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 7;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
