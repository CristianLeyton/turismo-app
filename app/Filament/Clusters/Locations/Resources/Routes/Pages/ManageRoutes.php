<?php

namespace App\Filament\Clusters\Locations\Resources\Routes\Pages;

use App\Filament\Clusters\Locations\Resources\Routes\RouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRoutes extends ManageRecords
{
    protected static string $resource = RouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
