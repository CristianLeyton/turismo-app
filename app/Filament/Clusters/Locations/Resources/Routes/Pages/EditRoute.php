<?php

namespace App\Filament\Clusters\Locations\Resources\Routes\Pages;

use App\Filament\Clusters\Locations\Resources\Routes\RouteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Pantalla única de una ruta: los datos de la ruta son la pestaña "Datos" y
 * el recorrido y los horarios son pestañas al lado (relation managers), así
 * quien la edita no tiene que saltar entre recursos ni filtrar por ruta.
 */
class EditRoute extends EditRecord
{
    protected static string $resource = RouteResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Datos';
    }

    protected function getHeaderActions(): array
    {
        return [
            RouteResource::guardAgainstPendingTrips(DeleteAction::make()),
            RouteResource::guardAgainstPendingTrips(ForceDeleteAction::make()),
            RestoreAction::make(),
        ];
    }
}
