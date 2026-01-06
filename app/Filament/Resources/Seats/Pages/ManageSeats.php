<?php

namespace App\Filament\Resources\Seats\Pages;

use App\Filament\Resources\Seats\SeatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSeats extends ManageRecords
{
    protected static string $resource = SeatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
