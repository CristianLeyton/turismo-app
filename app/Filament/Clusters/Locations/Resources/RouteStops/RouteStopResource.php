<?php

namespace App\Filament\Clusters\Locations\Resources\RouteStops;

use App\Filament\Clusters\Locations\LocationsCluster;
use App\Filament\Clusters\Locations\Resources\RouteStops\Pages\ManageRouteStops;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use BackedEnum;
use Filament\Actions\ActionGroup;
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
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RouteStopResource extends Resource
{
    protected static ?string $model = RouteStop::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $cluster = LocationsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'parada';
    protected static ?string $pluralModelLabel = 'Paradas';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('route_id')
                    ->relationship('route', 'name')
                    ->required()
                    // Cambiar de ruta reinicia el horario de referencia y las
                    // horas elegidas: el frame de los horarios es otro.
                    ->afterStateUpdated(function (Set $set, ?RouteStop $record, $state): void {
                        if ($record !== null) {
                            return;
                        }

                        $schedule = RouteStopResource::referenceSchedule(routeId: $state);

                        $set('reference_schedule_id', $schedule?->id);
                        $set('departure_offset_minutes', $schedule?->departure_time->format('H:i'));
                        $set('arrival_offset_minutes', null);
                    }),
                Select::make('location_id')
                    ->relationship('location', 'name')
                    ->required(),
                TextInput::make('stop_order')
                    ->required()
                    ->numeric()
                    ->helperText('Orden de la parada dentro de su ruta. En la tabla se reordena arrastrando y soltando las filas.'),

                // El horario de referencia define con qué salida del colectivo
                // se comparan las horas elegidas. Si la ruta tiene un solo
                // horario activo el campo queda oculto (se usa ese horario).
                Select::make('reference_schedule_id')
                    ->label('Horario de referencia')
                    ->options(fn (Get $get, ?RouteStop $record): array => self::scheduleOptions(
                        routeId: $get('route_id') ?? $record?->route_id,
                    ))
                    ->afterStateHydrated(function (Select $component, ?RouteStop $record, $state, Get $get): void {
                        if (filled($state)) {
                            return;
                        }

                        $first = self::referenceSchedule(routeId: $get('route_id') ?? $record?->route_id);

                        if ($first !== null) {
                            $component->state($first->id);
                        }
                    })
                    ->live()
                    ->visible(fn (Get $get, ?RouteStop $record): bool => count(self::scheduleOptions(
                        routeId: $get('route_id') ?? $record?->route_id,
                    )) > 1)
                    ->helperText('Las horas de salida y llegada se guardan como minutos desde la salida de este horario.')
                    ->dehydrated(false),

                // Los offsets reales (minutos desde la salida del horario de
                // referencia) viajan como hora del día: el administrador elige
                // la hora tal cual marca el panel; el sistema convierte.
                TimePicker::make('departure_offset_minutes')
                    ->label('Hora de salida')
                    ->seconds(false)
                    ->required()
                    ->afterStateHydrated(function (TimePicker $component, ?RouteStop $record, $state, Get $get): void {
                        if ($record === null) {
                            return;
                        }

                        $schedule = self::referenceSchedule(
                            routeId: $record->route_id,
                            scheduleId: filled($get('reference_schedule_id')) ? (int) $get('reference_schedule_id') : null,
                        );

                        if ($schedule !== null && $state !== null) {
                            $component->state(self::offsetToClock((int) $state, $schedule));
                        }
                    })
                    ->dehydrateStateUsing(function ($state, Get $get, ?RouteStop $record): ?int {
                        if (blank($state)) {
                            return null; // la validación "required" ya lo rechaza
                        }

                        $schedule = self::referenceSchedule(
                            routeId: $get('route_id') ?? $record?->route_id,
                            scheduleId: filled($get('reference_schedule_id')) ? (int) $get('reference_schedule_id') : null,
                        );

                        if ($schedule === null) {
                            return null;
                        }

                        return self::clockToOffset($state, $schedule);
                    })
                    ->placeholder('Salida del colectivo'),

                TimePicker::make('arrival_offset_minutes')
                    ->label('Hora de llegada')
                    ->seconds(false)
                    ->nullable()
                    ->helperText('Si se deja vacía, se usa la hora de salida (o la llegada final del horario en la última parada).')
                    ->afterStateHydrated(function (TimePicker $component, ?RouteStop $record, $state, Get $get): void {
                        if ($record === null) {
                            return;
                        }

                        $schedule = self::referenceSchedule(
                            routeId: $record->route_id,
                            scheduleId: filled($get('reference_schedule_id')) ? (int) $get('reference_schedule_id') : null,
                        );

                        if ($schedule !== null && $state !== null) {
                            $component->state(self::offsetToClock((int) $state, $schedule));
                        }
                    })
                    ->dehydrateStateUsing(function ($state, Get $get, ?RouteStop $record): ?int {
                        if (blank($state)) {
                            return null;
                        }

                        $schedule = self::referenceSchedule(
                            routeId: $get('route_id') ?? $record?->route_id,
                            scheduleId: filled($get('reference_schedule_id')) ? (int) $get('reference_schedule_id') : null,
                        );

                        if ($schedule === null) {
                            return null;
                        }

                        return self::clockToOffset($state, $schedule);
                    })
                    ->placeholder('Opcional'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['route.schedules']))
            // Con ruta elegida el recorrido completo va en una sola pantalla
            // (necesario para arrastrar); sin ruta, se pagina como antes.
            ->paginated(fn (Table $table) => blank(data_get(
                $table->getLivewire()->getTableFilterState('route'),
                'value',
            )))
            ->defaultPaginationPageOption(25)
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('location.name')
                    ->label('Parada')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stop_order')
                    ->label('Orden')
                    ->alignCenter()
                    ->sortable(false),
                TextColumn::make('departure_offset_minutes')
                    ->label('Hora de salida')
                    ->alignCenter()
                    ->sortable(false)
                    ->formatStateUsing(fn (?int $state, RouteStop $record): ?string => self::clockForStop($record, $state))
                    ->placeholder('—'),
                TextColumn::make('arrival_offset_minutes')
                    ->label('Hora de llegada')
                    ->alignCenter()
                    ->sortable(false)
                    ->formatStateUsing(fn (?int $state, RouteStop $record): ?string => self::clockForStop($record, $state))
                    ->placeholder('—'),
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
                // El eje del recurso: siempre hay una ruta elegida (por defecto
                // la primera activa); el listado muestra su recorrido completo.
                SelectFilter::make('route')
                    ->label('Ruta')
                    ->relationship('route', 'name')
                    ->default(fn (): ?int => Route::query()->active()->orderBy('id')->value('id')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Menú de tres puntitos (sólo celular): los ítems conservan
                // su label para que el menú abierto muestre el texto.
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                EditAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Editar', 'class' => 'hidden md:inline-flex',
                ]),
                DeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar', 'class' => 'hidden md:inline-flex',
                ]),
                ForceDeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente', 'class' => 'hidden md:inline-flex',
                ]),
                RestoreAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar', 'class' => 'hidden md:inline-flex',
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->reorderable(
                'stop_order',
                // El drag & drop sólo se permite con una ruta elegida: cruzar
                // filas de rutas distintas corrompería stop_order y horarios.
                condition: fn (Table $table) => filled(data_get(
                    $table->getLivewire()->getTableFilterState('route'),
                    'value',
                )),
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRouteStops::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Horario activo contra el que se miden las horas de las paradas.
     * Si la ruta tiene varios y se eligió uno en el formulario, se respeta;
     * si no, se usa el primero activo del día (ordenados por salida).
     */
    public static function referenceSchedule(?int $routeId = null, ?int $scheduleId = null, ?Model $record = null): ?Schedule
    {
        $routeId ??= $record instanceof RouteStop ? $record->route_id : null;

        if (! $routeId) {
            return null;
        }

        $schedules = Route::find($routeId)?->activeSchedules()->orderBy('departure_time')->get();

        if (! $schedules || $schedules->isEmpty()) {
            return null;
        }

        return $schedules->firstWhere('id', $scheduleId) ?? $schedules->first();
    }

    /** @return array<int, string> id => etiqueta legible del horario */
    protected static function scheduleOptions(?int $routeId): array
    {
        if (! $routeId) {
            return [];
        }

        $schedules = Route::find($routeId)?->activeSchedules()->orderBy('departure_time')->get();

        return $schedules?->mapWithKeys(fn (Schedule $schedule): array => [
            $schedule->id => ($schedule->name ? "{$schedule->name} — " : '')
                .$schedule->departure_time->format('H:i')
                .(filled($schedule->arrival_time) ? ' - '.$schedule->arrival_time->format('H:i') : ''),
        ])->all() ?? [];
    }

    /** Hora del día (H:i) para mostrar, a partir del offset en minutos. */
    public static function offsetToClock(int $offset, Schedule $schedule): string
    {
        $base = (int) $schedule->departure_time->format('G') * 60 + (int) $schedule->departure_time->format('i');
        $minutes = ($base + $offset) % 1440;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** Offset en minutos desde la salida del horario, a partir de la hora elegida. */
    public static function clockToOffset(string $clock, Schedule $schedule): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $clock));

        $picked = $hours * 60 + $minutes;
        $base = (int) $schedule->departure_time->format('G') * 60 + (int) $schedule->departure_time->format('i');

        // Un horario posterior a la medianoche "da la vuelta": 03:30 sobre una
        // salida 13:00 son 870 minutos (14:30 h tras la medianoche), no -570.
        return ($picked - $base + 1440) % 1440;
    }

    /** Hora del día que muestra la tabla para el offset de una parada. */
    public static function clockForStop(RouteStop $record, ?int $offset): ?string
    {
        if ($offset === null) {
            return null;
        }

        $schedule = self::referenceSchedule(routeId: $record->route_id);

        return $schedule ? self::offsetToClock($offset, $schedule) : null;
    }
}
