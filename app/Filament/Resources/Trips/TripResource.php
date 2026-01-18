<?php

namespace App\Filament\Resources\Trips;

use App\Filament\Resources\Trips\Pages\ManageTrips;
use App\Models\Schedule;
use App\Models\Trip;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Services\TripPdfService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
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
                    ->required()
                    ->visibleOn('create'),
                Select::make('route_id')
                    ->label('Ruta')
                    ->relationship('route', 'name')
                    ->required()
                    ->visibleOn('create')
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
                    ->visibleOn('create')
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
                    ->visibleOn('create')
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
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('trip_date')
                    ->label('Fecha de salida')
                    ->date('d/m/Y')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->alignCenter(),
                TextColumn::make('schedule.name')
                    ->label('Horario')
                    ->searchable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('departure_time')
                    ->label('Hora salida')
                    ->dateTime('H:i')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('arrival_time')
                    ->label('Hora llegada')
                    ->dateTime('H:i')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('route.name')
                    ->label('Ruta')
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('occupiedSeatsCount')
                    ->label('Asientos vendidos')
                    ->getStateUsing(fn ($record) => $record->occupiedSeatsCount())
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('total_passengers')
                    ->label('Total pasajeros')
                    ->getStateUsing(fn ($record) => $record->total_passengers)
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),
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
            ->recordAction('view_details')
            ->defaultSort('trip_date', 'desc')
            ->filters([
                SelectFilter::make('route')
                    ->relationship('route', 'name')
                    ->label('Ruta')
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make('view_details')
                    ->label('Ver detalles')
                    ->modalHeading(fn ($record) => "Detalles del viaje - {$record->bus->name} ({$record->trip_date->format('d/m/Y')})")
                    ->modalContent(function ($record) {
                        $passengers = $record->getPassengersWithDetails();
                        
                        return view('filament.trips.trip-details', [
                            'trip' => $record,
                            'passengers' => $passengers
                        ]);
                    })
                    ->modalWidth('7xl')
                    ->extraModalFooterActions(function ($record) {
                        return [
                            Action::make('download_pdf_modal')
                                ->label('PDF')
                                ->icon('heroicon-m-arrow-down-tray')
                                ->color('primary')
                                ->url(route('trips.pdf.download', $record))
                                ->openUrlInNewTab()
                                ->disabled($record->tickets()->count() === 0)
                                ->extraAttributes([
                                    'title' => 'Descargar PDF'
                                ]),
                            Action::make('download_excel_modal')
                                ->label('Excel')
                                ->icon('heroicon-m-arrow-down-tray')
                                ->color('success')
                                ->url(route('trips.excel.download', $record))
                                ->openUrlInNewTab()
                                ->disabled($record->tickets()->count() === 0)
                                ->extraAttributes([
                                    'title' => 'Descargar Excel'
                                ]),
                            
                        ];
                    })
                    ->disabled(fn ($record) => $record->tickets()->count() === 0)
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes([
                        'title' => 'Ver detalles'
                    ]),
                Action::make('download_pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('primary')
                    ->url(fn ($record) => route('trips.pdf.download', $record))
                    ->openUrlInNewTab()
                    ->disabled(fn ($record) => $record->tickets()->count() === 0)
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes([
                        'title' => 'Descargar PDF'
                    ]),
                Action::make('download_excel')
                    ->label('Descargar Excel')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => route('trips.excel.download', $record))
                    ->openUrlInNewTab()
                    ->disabled(fn ($record) => $record->tickets()->count() === 0)
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes([
                        'title' => 'Descargar Excel'
                    ]),
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
