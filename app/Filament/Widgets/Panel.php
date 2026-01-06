<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class Panel extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static bool $isLazy = false;
    protected ?string $heading = 'Bienvenido al panel de administración';
    protected ?string $description = 'Tambien puedes navegar entre las diferentes secciones usando el menú superior';

    protected function getStats(): array
    {
        return [
            Stat::make('Venta de pasajes', 'Vender')
                ->icon('heroicon-o-ticket')
                ->url('#')
                ->description('Ir a vender pasajes')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->extraAttributes(['class' => 'group [&_.fi-wi-stats-overview-stat-value]:text-2xl [&_.fi-wi-stats-overview-stat-value]:group-hover:text-primary-600 [&_.fi-wi-stats-overview-stat-value]:transition']),
            Stat::make('Pasajes vendidos', 'Ver vendidos')
                ->icon('heroicon-o-clipboard-document-list')
                ->url('#')
                ->description('Listado de pasajes vendidos')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->extraAttributes(['class' => 'group [&_.fi-wi-stats-overview-stat-value]:text-2xl [&_.fi-wi-stats-overview-stat-value]:group-hover:text-primary-600 [&_.fi-wi-stats-overview-stat-value]:transition']),
            Stat::make('Proximos viajes', 'Ver viajes')
                ->icon('heroicon-o-calendar-days')
                ->url('#')
                ->description('Ver detalle de próximos viajes')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->extraAttributes(['class' => 'group [&_.fi-wi-stats-overview-stat-value]:text-2xl [&_.fi-wi-stats-overview-stat-value]:group-hover:text-primary-600 [&_.fi-wi-stats-overview-stat-value]:transition']),
            Stat::make('Administrar usuarios', 'Usuarios')
                ->icon('heroicon-o-user-group')
                ->url(UserResource::getUrl())
                ->description('Crear o editar usuarios')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->extraAttributes(['class' => 'group [&_.fi-wi-stats-overview-stat-value]:text-2xl [&_.fi-wi-stats-overview-stat-value]:group-hover:text-primary-600 [&_.fi-wi-stats-overview-stat-value]:transition'])
                ->visible(fn (): bool => Auth::user()->is_admin ?? false),
        ];
    }
}
