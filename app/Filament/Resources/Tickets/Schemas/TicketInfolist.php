<?php

namespace App\Filament\Resources\Tickets\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sale.id'),
                TextEntry::make('trip.id'),
                TextEntry::make('origin_location_id')
                    ->numeric(),
                TextEntry::make('destination_location_id')
                    ->numeric(),
                TextEntry::make('returnTrip.id'),
                TextEntry::make('passenger.id'),
                TextEntry::make('seat.id'),
                IconEntry::make('is_round_trip')
                    ->boolean(),
                IconEntry::make('travels_with_child')
                    ->boolean(),
                TextEntry::make('price')
                    ->money(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('deleted_at')
                    ->dateTime(),
            ]);
    }
}
