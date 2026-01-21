<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\TripResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTrips extends ManageRecords
{
    protected static string $resource = TripResource::class;
    protected static ?string $title = '';
    protected ?string $heading = 'Viajes';
    protected function getHeaderActions(): array
    {
        return [
            //CreateAction::make(),
        ];
    }
}
