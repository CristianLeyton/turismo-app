<?php

namespace App\Filament\Clusters\Sales;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Filament\Pages\Enums\SubNavigationPosition;
use UnitEnum;

class SalesCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::PresentationChartBar;

    protected static ?string $clusterBreadcrumb = 'Ventas';
    protected static ?string $navigationLabel = 'Ventas';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 6;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
