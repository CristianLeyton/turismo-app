<?php

namespace App\Filament\Resources\Lineas\Pages;

use App\Filament\Resources\Lineas\LineaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLineas extends ManageRecords
{
    protected static string $resource = LineaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva línea'),
        ];
    }
}
