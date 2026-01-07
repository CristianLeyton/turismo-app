<?php

namespace App\Filament\Resources\Tickets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sale_id')
                    ->relationship('sale', 'id')
                    ->required(),
                Select::make('trip_id')
                    ->relationship('trip', 'id')
                    ->required(),
                TextInput::make('origin_location_id')
                    ->required()
                    ->numeric(),
                TextInput::make('destination_location_id')
                    ->required()
                    ->numeric(),
                Select::make('return_trip_id')
                    ->relationship('returnTrip', 'id'),
                Select::make('passenger_id')
                    ->relationship('passenger', 'id')
                    ->required(),
                Select::make('seat_id')
                    ->relationship('seat', 'id'),
                Toggle::make('is_round_trip')
                    ->required(),
                Toggle::make('travels_with_child')
                    ->required(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
            ]);
    }
}
