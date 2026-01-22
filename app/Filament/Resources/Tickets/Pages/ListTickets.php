<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected static ?string $title = '';
    protected ?string $heading = 'Boletos';

    protected function getHeaderActions(): array
    {
        return [
            //CreateAction::make()
                //->label('Vender boleto')
                //->createAnother(false),
        ];
    }
}
