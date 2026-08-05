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
            ])
            ->defaultSort('apellido')
            ->paginated([10, 25, 50]);
    }
}
