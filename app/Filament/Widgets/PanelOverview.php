<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Icons\Heroicon;
use App\Filament\Pages\VentaDePasajes;
use App\Filament\Pages\PlanillasViajes;

class PanelOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Venta de pasajes', 'Vender')
                ->icon(Heroicon::Ticket)
                ->color('success')
                ->url(VentaDePasajes::getUrl())
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
            Stat::make('Pasajes vendidos', 'Vendidos')
                ->icon(Heroicon::User)
                ->color('success')
                ->url(PlanillasViajes::getUrl())
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
            Stat::make('Proximo viaje', '31/12/2025')
                ->icon(Heroicon::Calendar)
                ->color('success')
                ->url(PlanillasViajes::getUrl())
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
            Stat::make('Estadísticas', 'Estadísticas')
                ->icon(Heroicon::ChartBar)
                ->color('success')
                ->url('#')
                ->extraAttributes([
                    'class' => 'cursor-pointer transform scale-110',
                ]),
        ];
    }
}
