<?php

namespace App\Filament\Clusters\Tickets;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class TicketsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Ticket;

    protected static ?string $clusterBreadcrumb = 'Boletos';
    protected static ?string $navigationLabel = 'Boletos';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 0;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
