<?php

namespace App\Filament\Clusters\Locations;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Filament\Pages\Enums\SubNavigationPosition;
use UnitEnum;

class LocationsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::MapPin;
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static string | UnitEnum | null $navigationGroup = 'Configuración';
    
    protected static ?string $clusterBreadcrumb = 'Destinos';
    protected static ?string $navigationLabel = 'Destinos';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 1;
}
