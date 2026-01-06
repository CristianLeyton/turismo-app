<?php

namespace App\Filament\Clusters\Buses;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Filament\Pages\Enums\SubNavigationPosition;
use UnitEnum;

class BusesCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Truck;

    protected static ?string $clusterBreadcrumb = 'Colectivos';
    protected static ?string $navigationLabel = 'Colectivos';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 0;

    protected static string | UnitEnum | null $navigationGroup = 'Configuración';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
