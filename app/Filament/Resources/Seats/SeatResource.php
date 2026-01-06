<?php

namespace App\Filament\Resources\Seats;

use App\Filament\Clusters\Buses\BusesCluster;
use App\Filament\Resources\Seats\Pages\ManageSeats;
use App\Models\Bus;
use App\Models\Seat;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SeatResource extends Resource
{
    protected static ?string $model = Seat::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::User;

    
      protected static ?string $cluster = BusesCluster::class;

    protected static ?string $modelLabel = 'asiento';
    protected static ?string $pluralModelLabel = 'Asientos';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 2;
    

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bus_id')
                    ->label('Colectivo')
                    ->relationship('bus', 'name')
                    ->live()
                    ->required(),
                TextInput::make('seat_number')
                    ->label('Número de asiento')
                    ->unique(column: 'seat_number', ignoreRecord: true)
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(function (Get $get) {
                        $busId = $get('bus_id');
                        if (! $busId) {
                            return null;
                        }
                        return Bus::find($busId)?->seat_count;
                    })
                    ->validationMessages([
                        'required' => 'El número de asiento es obligatorio.',
                        'unique' => 'El número de asiento ya está en uso para este colectivo.',
                        'numeric' => 'El número de asiento debe ser un número.',
                        'min' => 'El número de asiento debe ser al menos :min.',
                    ]),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->columns([
                TextColumn::make('bus.name')
                    ->label('Colectivo')
                    ->searchable(),
                TextColumn::make('seat_number')
                    ->label('Número de asiento')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                ToggleColumn::make('is_active')
                    ->label('Activo')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                 SelectFilter::make('bus')
                    ->relationship('bus', 'name')
                    ->label('Colectivo'),

                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activo')
                    ->falseLabel('Inactivo'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Editar',
                ]),
                DeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar',
                ]),
                ForceDeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente',
                ]),
                RestoreAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar',
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSeats::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
