<?php

namespace App\Filament\Clusters\Tickets;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

use function Filament\Support\original_request;

class TicketsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Ticket;

    protected static ?string $clusterBreadcrumb = 'Boletos';

    protected static ?string $navigationLabel = 'Boletos';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 0;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    /**
     * El item "Boletos" se ilumina con el patrón por defecto del cluster
     * (cualquier ruta dentro de admin/tickets/...). Como "Vender" vive en
     * ese mismo prefijo, le sobrescribimos la condición de activo para que
     * el cluster NO se marque en la página de venta: ahí ya se ilumina su
     * propio item "Vender" de la barra de navegación.
     */
    public static function getNavigationItems(): array
    {
        $clusterRouteName = static::getRouteName();

        return array_map(
            static fn (NavigationItem $item): NavigationItem => $item->isActiveWhen(
                static fn (): bool => original_request()->routeIs($clusterRouteName.'.*')
                    && ! original_request()->routeIs($clusterRouteName.'.resources.tickets.create'),
            ),
            parent::getNavigationItems(),
        );
    }
}
