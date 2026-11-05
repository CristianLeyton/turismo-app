<?php

namespace App\Filament\Clusters\Locations\Resources\Routes\RelationManagers;

use App\Filament\Clusters\Locations\Resources\RouteStops\Concerns\ReordersRouteStopsKeepingTimes;
use App\Filament\Clusters\Locations\Resources\RouteStops\RouteStopResource;
use App\Models\Route;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Pestaña "Recorrido" de la ruta: sus paradas en orden de paso, arrastrables,
 * con formulario y tabla compartidos con RouteStopResource.
 */
class StopsRelationManager extends RelationManager
{
    use ReordersRouteStopsKeepingTimes;

    protected static string $relationship = 'stops';

    protected static ?string $relatedResource = RouteStopResource::class;

    protected static ?string $title = 'Recorrido';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Recorrido';
    }

    public function table(Table $table): Table
    {
        return $table
            ->pushHeaderActions([
                CreateAction::make()
                    ->label('Agregar parada')
                    // Sin horarios no hay contra qué medir las horas; mientras
                    // se reordena no se mezclan altas con el orden pendiente.
                    ->visible(fn (): bool => $this->routeHasActiveSchedules() && ! $this->isTableReordering()),
            ]);
    }

    protected function routeHasActiveSchedules(): bool
    {
        /** @var Route $route */
        $route = $this->getOwnerRecord();

        return $route->activeSchedules()->exists();
    }
}
