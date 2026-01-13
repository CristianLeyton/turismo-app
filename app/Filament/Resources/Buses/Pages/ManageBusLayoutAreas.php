<?php

namespace App\Filament\Resources\Buses\Pages;

use App\Filament\Resources\Buses\BusLayoutAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBusLayoutAreas extends ManageRecords
{
    protected static string $resource = BusLayoutAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
