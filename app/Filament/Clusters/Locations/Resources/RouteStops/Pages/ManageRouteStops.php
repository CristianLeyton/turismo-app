<?php

namespace App\Filament\Clusters\Locations\Resources\RouteStops\Pages;

use App\Filament\Clusters\Locations\Resources\RouteStops\RouteStopResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRouteStops extends ManageRecords
{
    protected static string $resource = RouteStopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
