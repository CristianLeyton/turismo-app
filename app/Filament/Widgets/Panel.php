<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\Trips\TripResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Trip;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class Panel extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = '2';
    protected static bool $isLazy = false;

    protected function getColumns(): int | array
    {
        return [
            'default' => 1, // mobile
            'sm' => 2,      // tablets
            'md' => 2,      // pantallas intermedias
            'lg' => 4,      // desktop
        ];
    }

    
    protected function getStats(): array
    {
        return [
            Stat::make('Venta de boletos', 'Vender')
                ->icon('heroicon-o-ticket')
                ->url(TicketResource::getUrl('create'))
                ->description('Ir a vender boletos')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->extraAttributes(['class' => 'group [&_.fi-wi-stats-overview-stat-value]:text-2xl [&_.fi-wi-stats-overview-stat-value]:group-hover:text-primary-600 [&_.fi-wi-stats-overview-stat-value]:transition
                [&_.fi-icon:nth-child(2)]:group-hover:translate-x-0.5 [&_.fi-icon:nth-child(2)]:group-hover:-translate-y-0.5 [&_.fi-icon]:transition']),

            Stat::make('Boletos vendidos', 'Boletos')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(TicketResource::getUrl())
                ->description('Listado de boletos vendidos')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->extraAttributes(['class' => 'group [&_.fi-wi-stats-overview-stat-value]:text-2xl [&_.fi-wi-stats-overview-stat-value]:group-hover:text-primary-600 [&_.fi-wi-stats-overview-stat-value]:transition
                [&_.fi-icon:nth-child(2)]:group-hover:translate-x-0.5 [&_.fi-icon:nth-child(2)]:group-hover:-translate-y-0.5 [&_.fi-icon]:transition']),

            Stat::make('Detalle de viajes', 'Viajes')
                ->icon('heroicon-o-calendar-days')
                ->url(TripResource::getUrl())
                ->description('Ver detalle de viajes')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->extraAttributes(['class' => 'group [&_.fi-wi-stats-overview-stat-value]:text-2xl [&_.fi-wi-stats-overview-stat-value]:group-hover:text-primary-600 [&_.fi-wi-stats-overview-stat-value]:transition
                [&_.fi-icon:nth-child(2)]:group-hover:translate-x-0.5 [&_.fi-icon:nth-child(2)]:group-hover:-translate-y-0.5 [&_.fi-icon]:transition']),

            Stat::make('Administrar usuarios', 'Usuarios')
                ->icon('heroicon-o-user-group')
                ->url(UserResource::getUrl())
                ->description('Crear o editar usuarios')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->extraAttributes(['class' => 'group [&_.fi-wi-stats-overview-stat-value]:text-2xl [&_.fi-wi-stats-overview-stat-value]:group-hover:text-primary-600 [&_.fi-wi-stats-overview-stat-value]:transition
                [&_.fi-icon:nth-child(2)]:group-hover:translate-x-0.5 [&_.fi-icon:nth-child(2)]:group-hover:-translate-y-0.5 [&_.fi-icon]:transition'])
                ->visible(fn (): bool => Auth::user()->is_admin ?? false),
        ];
    }
}
