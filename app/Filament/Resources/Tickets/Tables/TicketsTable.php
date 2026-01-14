<?php

namespace App\Filament\Resources\Tickets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sale.id')
                    ->label('Venta')
                    ->searchable(),
                TextColumn::make('trip.id')
                    ->label('Viaje')
                    ->searchable(),
                TextColumn::make('origin_location_id')
                    ->label('Origen')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('destination_location_id')
                    ->label('Destino')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('returnTrip.id')
                    ->label('Viaje de regreso')
                    ->searchable(),
                TextColumn::make('passenger.id')
                    ->label('Pasajero')
                    ->searchable(),
                TextColumn::make('seat.id')
                    ->label('Asiento')
                    ->searchable(),
                IconColumn::make('is_round_trip')
                    ->label('Ida y vuelta')
                    ->boolean(),
                IconColumn::make('travels_with_child')
                    ->label('Viaja con niño')    
                    ->boolean(),
                TextColumn::make('price')
                    ->label('Precio')
                    ->money()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                //EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
