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
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
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
                TextColumn::make('id')
                    ->label('Viaje Nº')
                    ->sortable()
                    ->badge()
                    ->alignCenter()
                    ->color('gray'),
                TextColumn::make('trip_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->url(fn ($record) => static::getUrl(parameters: [
                        'tableAction' => 'view_details',
                        'tableActionRecord' => $record->id,
                    ]))
                    ->alignCenter(),
/*                 TextColumn::make('schedule.name')
                    ->label('Horario')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true), */
                TextColumn::make('departure_time')
                    ->label('Horario')
/*                     ->sortable() */
->visibleFrom('md')
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->getStateUsing(
                        fn($record) =>

                        Carbon::parse($record->departure_time)->format('H:i') .
                            ' - ' .
                            Carbon::parse($record->arrival_time)->format('H:i')
                    ),
                    //->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('route.name')
                    ->label('Ruta')
                    ->sortable()
                    ->visibleFrom('md')
                    ->badge()
                    ->color('warning'),
                    //->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('occupiedSeatsCount')
                    ->label('Asientos vendidos')
                    ->visibleFrom('md')
                    ->getStateUsing(fn($record) => $record->occupiedSeatsCount())
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),
                    //->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('total_passengers')
                    ->label('Pasajeros')
                    ->getStateUsing(fn($record) => $record->total_passengers)
                    ->visibleFrom('md')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
                    //->toggleable(isToggledHiddenByDefault: false),
/*                 TextColumn::make('created_at')
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
                    ->toggleable(isToggledHiddenByDefault: true), */
            ])
            ->defaultSort('trip_date', 'desc')
/*             ->persistSortInSession() */
            ->filters([
                Filter::make('trip_id')
                    ->label('Viaje Nº')
                    ->form([
                        TextInput::make('id')
                            ->label('Viaje Nº')
                            ->placeholder('Número de viaje')
                            ->numeric()
                            ->minValue(1)
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['id'],
                                fn (Builder $query, int $id): Builder => $query->where('id', $id),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['id'] ?? null) {
                            $indicators[] = 'ID: ' . $data['id'];
                        }
                        return $indicators;
                    }),

                Filter::make('trip_date')
                    ->label('Fecha de salida')
                    ->form([
                        DatePicker::make('date')
                            ->label('Fecha')
                            ->native(true)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            /* ->default(now()->format('Y-m-d')) */,
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('trip_date', '=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date'] ?? null) {
                            $indicators[] = 'Fecha: ' . \Carbon\Carbon::parse($data['date'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),

                SelectFilter::make('schedule')
                    ->relationship('schedule', 'name')
                    ->label('Horario')
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (Schedule $record) {
                        return $record->display_name;
                    }), 

                SelectFilter::make('route')
                    ->relationship('route', 'name')
                    ->label('Ruta')
                    ->preload(), 
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->hiddenFilterIndicators()
            ->recordClasses(fn ($record) => $record->tickets()->count() === 0 ? 'bg-gray-100 opacity-70 dark:bg-gray-800' : '')
            ->recordActions([
                ViewAction::make('view_details')
                    ->label('Ver detalles')
                    ->modalHeading(fn($record) => "Detalles del viaje N° {$record->id } - {$record->bus->name} - ({$record->trip_date->format('d/m/Y')})")
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
                                ->action(function ($record) {
                                    $service = app(\App\Services\TripPdfService::class);
                                    $pdf = $service->generateTripDetailsPdf($record);

                                    $filename = 'Viaje_N°' . $record->id . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $record->bus->name) . '_' . $record->trip_date->format('d-m-Y') . '.pdf';

                                    return response()->streamDownload(function () use ($pdf) {
                                        echo $pdf->output();
                                    }, $filename, [
                                        'Content-Type' => 'application/pdf',
                                        'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                                    ]);
                                })
                                ->disabled($record->tickets()->count() === 0)
                                ->extraAttributes([
                                    'title' => 'Descargar PDF'
                                ]),
                            Action::make('download_excel_modal')
                                ->label('Excel')
                                ->icon('heroicon-m-arrow-down-tray')
                                ->color('success')
                                ->action(function ($record) {
                                    $service = app(\App\Services\TripExcelService::class);
                                    $export = $service->generateTripDetailsExcel($record);

                                    $tripId = $record->id;
                                    $colectivo = str_replace(' ', '_', $record->bus->name);
                                    $date = $record->trip_date->format('d-m-Y');

                                    return \Maatwebsite\Excel\Facades\Excel::download(
                                        $export,
                                        "Viaje_N°{$tripId}_{$colectivo}_{$date}.xlsx"
                                    );
                                })
                                ->disabled($record->tickets()->count() === 0)
                                ->extraAttributes([
                                    'title' => 'Descargar Excel'
                                ]),

                        ];
                    })
                    ->disabled(fn($record) => $record->tickets()->count() === 0)
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes([
                        'title' => 'Ver detalles'
                    ]),
                Action::make('download_pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('primary')
                    ->action(function ($record) {
                        $service = app(\App\Services\TripPdfService::class);
                        $pdf = $service->generateTripDetailsPdf($record);

                        $filename = 'Viaje_N°' . $record->id . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $record->bus->name) . '_' . $record->trip_date->format('d-m-Y') . '.pdf';

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, $filename, [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                        ]);
                    })
                    ->disabled(fn($record) => $record->tickets()->count() === 0)
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes([
                        'title' => 'Descargar PDF'
                    ]),
                Action::make('download_excel')
                    ->label('Descargar Excel')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->action(function ($record) {
                        $service = app(\App\Services\TripExcelService::class);
                        $export = $service->generateTripDetailsExcel($record);

                        $tripId = $record->id;
                        $colectivo = str_replace(' ', '_', $record->bus->name);
                        $date = $record->trip_date->format('d-m-Y');

                        return \Maatwebsite\Excel\Facades\Excel::download(
                            $export,
                            "Viaje_N°{$tripId}_{$colectivo}_{$date}.xlsx"
                        );
                    })
                    ->disabled(fn($record) => $record->tickets()->count() === 0)
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
                ]), */]);
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
