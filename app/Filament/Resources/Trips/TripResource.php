<?php

namespace App\Filament\Resources\Trips;

use App\Filament\Resources\Trips\Pages\ManageTrips;
use App\Models\Schedule;
use App\Models\Trip;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static ?string $modelLabel = 'viaje';
    protected static ?string $pluralModelLabel = 'Viajes';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bus_id')
                    ->label('Colectivo')
                    ->relationship('bus', 'name')
                    ->required(),
                Select::make('route_id')
                    ->label('Ruta')
                    ->relationship('route', 'name')
                    ->required()
                    ->reactive(),
                Select::make('schedule_id')
                    ->label('Horario')
                    ->relationship(
                        'schedule',
                        'name',
                        fn($query, $get) => $query->where('is_active', true)
                            ->where('route_id', $get('route_id'))
                    )
                    ->getOptionLabelFromRecordUsing(function (Schedule $record) {
                        return $record->display_name;
                    })
                    ->required()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $schedule = Schedule::find($state);
                            if ($schedule) {
                                $set('departure_time', $schedule->departure_time);
                                $set('arrival_time', $schedule->arrival_time);
                            }
                        }
                    }),
                DatePicker::make('trip_date')
                    ->label('Fecha del viaje')
                    ->required()
                    ->displayFormat('d/m/Y')
                    ->native(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bus.name')
                    ->label('Colectivo')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('trip_date')
                    ->label('Fecha')
                    ->date()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('schedule.name')
                    ->label('Horario')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('route.name')
                    ->label('Ruta')
                    ->sortable()
                    ->searchable(),
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
                SelectFilter::make('route')
                    ->relationship('route', 'name')
                    ->label('Ruta')
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
/*                 EditAction::make()->button()->hiddenLabel()->extraAttributes([
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
                ]), */
            ])
            ->toolbarActions([
/*                 BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]), */
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTrips::route('/'),
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
