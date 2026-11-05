<?php

namespace App\Filament\Clusters\Locations\Resources\Routes\Pages;

use App\Filament\Clusters\Locations\Resources\Routes\RouteResource;
use App\Models\Route;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRoutes extends ManageRecords
{
    protected static string $resource = RouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Tras crear la ruta se pasa directo a su pantalla, donde se
            // cargan horarios y recorrido.
            CreateAction::make()
                ->successRedirectUrl(fn (Route $record): string => RouteResource::getUrl('edit', ['record' => $record])),
        ];
    }
}
