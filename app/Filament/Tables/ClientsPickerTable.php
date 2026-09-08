<?php

namespace App\Filament\Tables;

use App\Models\Clients;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientsPickerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(Clients::query())
            ->columns([
                TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable(),
                TextColumn::make('apellido')
                    ->label('Apellido')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('estado_compra')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Clients $record): string => $record->can_buy ? 'Habilitado' : 'Baneado')
                    ->color(fn (Clients $record): string => $record->can_buy ? 'success' : 'danger'),
            ])
            ->recordClasses(fn (Clients $record): array => $record->can_buy ? [] : [
                '!bg-red-100',
                'hover:!bg-red-200',
            ])
            ->checkIfRecordIsSelectableUsing(fn (Clients $record): bool => (bool) $record->can_buy)
            ->defaultSort('apellido')
            ->paginated([10, 25, 50]);
    }
}
