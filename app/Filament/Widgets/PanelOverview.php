<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Icons\Heroicon;

class PanelOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            //
            Stat::make('Venta de pasajes', 'Vender')
                ->icon(Heroicon::Ticket)
                ->color('success')
                //->url(TicketResource::getUrl('index'))
                ->url("#")
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
            Stat::make('Pasajes vendidos', 'Vendidos')
                ->icon(Heroicon::User)
                ->color('success')
                //->url(TicketResource::getUrl('index'))
                ->url("#")
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
            Stat::make('Proximo viaje', '31/12/2025')
                ->icon(Heroicon::Calendar)
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ])
                //->url(TicketResource::getUrl('index'))
                ->url("#")
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
            Stat::make('Estadísticas', 'Estadísticas')
                ->icon(Heroicon::ChartBar)
                ->color('success')
                //->url(TicketResource::getUrl('index'))
                ->url("#")
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
        ];
    }
}
