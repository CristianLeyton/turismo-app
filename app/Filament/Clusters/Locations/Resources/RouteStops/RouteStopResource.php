<?php

namespace App\Filament\Clusters\Locations\Resources\RouteStops;

use App\Filament\Clusters\Locations\LocationsCluster;
use App\Filament\Clusters\Locations\Resources\RouteStops\Pages\ManageRouteStops;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Support\AffectedTickets;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

/**
 * Paradas de una ruta. Se usa en dos lugares:
 *  - La pestaña "Recorrido" de la pantalla de cada ruta (relation manager),
 *    donde la ruta es fija y el recorrido se ve completo y arrastrable.
 *  - El listado de paradas (oculto de la navegación), filtrado por ruta.
 *
 * Las horas se guardan como minutos desde la salida del horario y valen
 * para todos los horarios de la ruta; en pantalla se cargan y se muestran
 * como hora del reloj del primer horario activo (el de salida más temprana).
 */
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

    /** Las paradas se editan desde la pestaña "Recorrido" de cada ruta. */
    protected static bool $shouldRegisterNavigation = false;

    /** Máximo de minutos entre dos horas seguidas del recorrido (12 h). */
    public const MAX_GAP_MINUTES = 720;

    public const NO_SCHEDULES_MESSAGE = 'Primero cargá al menos un horario para poder indicar a qué hora pasa por cada parada.';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('route_id')
                    ->label('Ruta')
                    ->relationship('route', 'name')
                    ->required()
                    // Dentro de una ruta, la ruta la fija la relación.
                    ->hidden(fn (mixed $livewire): bool => $livewire instanceof RelationManager)
                    ->afterStateUpdated(function (Set $set, ?RouteStop $record, $state): void {
                        if ($record !== null) {
                            return;
                        }

                        $schedule = RouteStopResource::referenceSchedule(routeId: filled($state) ? (int) $state : null);

                        $set('departure_offset_minutes', $schedule?->departure_time->format('H:i'));
                        $set('arrival_offset_minutes', null);
                    }),

                Select::make('location_id')
                    ->label('Destino')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionAction(fn (Action $action): Action => $action
                        ->modalHeading('Nuevo destino')
                        ->visible(fn (): bool => (bool) Auth::user()?->can('create', Location::class)))
                    ->rules([
                        fn (Get $get, ?RouteStop $record, mixed $livewire): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $livewire): void {
                            $message = self::duplicateLocationMessage(
                                routeId: self::contextRouteId($get, $record, $livewire),
                                locationId: filled($value) ? (int) $value : null,
                                ignoreStop: $record,
                            );

                            if ($message !== null) {
                                $fail($message);
                            }
                        },
                    ])
                    ->validationMessages([
                        'required' => 'Elegí el destino de la parada.',
                    ]),

                TimePicker::make('arrival_offset_minutes')
                    ->label('Hora de llegada')
                    ->seconds(false)
                    ->nullable()
                    ->helperText('Opcional. Si se deja vacía, se usa la hora de salida (o la llegada final del horario en la última parada).')
                    ->afterStateHydrated(function (TimePicker $component, ?RouteStop $record): void {
                        self::hydrateClock($component, $record, 'arrival_offset_minutes');
                    })
                    ->dehydrateStateUsing(fn ($state, Get $get, ?RouteStop $record, mixed $livewire): ?int => self::dehydrateClock($state, self::contextRouteId($get, $record, $livewire)))
                    ->rules([
                        fn (Get $get, ?RouteStop $record, mixed $livewire): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $livewire): void {
                            $schedule = self::referenceSchedule(routeId: self::contextRouteId($get, $record, $livewire));
                            $departure = $get('departure_offset_minutes');

                            if ($schedule === null || blank($value) || blank($departure)) {
                                return;
                            }

                            $arrivalOffset = self::clockToOffset((string) $value, $schedule);
                            $departureOffset = self::clockToOffset((string) $departure, $schedule);

                            if ($arrivalOffset !== $departureOffset && ! self::isEarlierOffset($arrivalOffset, $departureOffset)) {
                                $fail('La hora de llegada no puede ser posterior a la hora de salida.');
                            }
                        },
                    ])
                    ->placeholder('Opcional'),

                TimePicker::make('departure_offset_minutes')
                    ->label('Hora de salida')
                    ->seconds(false)
                    ->required()
                    ->helperText(fn (Get $get, ?RouteStop $record, mixed $livewire): ?string => self::referenceScheduleHint(
                        self::contextRouteId($get, $record, $livewire),
                    ))
                    ->afterStateHydrated(function (TimePicker $component, ?RouteStop $record): void {
                        self::hydrateClock($component, $record, 'departure_offset_minutes');
                    })
                    ->dehydrateStateUsing(fn ($state, Get $get, ?RouteStop $record, mixed $livewire): ?int => self::dehydrateClock($state, self::contextRouteId($get, $record, $livewire)))
                    ->rules([
                        fn (Get $get, ?RouteStop $record, mixed $livewire): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $livewire): void {
                            $message = self::earlierThanPreviousStopMessage(
                                routeId: self::contextRouteId($get, $record, $livewire),
                                clock: filled($value) ? (string) $value : null,
                                stop: $record,
                            );

                            if ($message !== null) {
                                $fail($message);
                            }
                        },
                    ])
                    ->validationMessages([
                        'required' => 'Indicá a qué hora sale el colectivo de esta parada.',
                    ])
                    ->placeholder('Salida del colectivo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Dentro de una ruta el recorrido es siempre de esa ruta: sin filtro
        // de ruta, sin paginar y siempre arrastrable.
        $isInsideRoute = $table->getLivewire() instanceof RelationManager;

        $hasRouteFilter = fn (Table $table): bool => filled(data_get(
            $table->getLivewire()->getTableFilterState('route'),
            'value',
        ));

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['location', 'route.activeSchedules']))
            ->recordUrl(null)
            ->recordAction(null)
            // Con ruta elegida el recorrido completo va en una sola pantalla
            // (necesario para arrastrar); sin ruta, se pagina como antes.
            ->paginated($isInsideRoute ? false : fn (Table $table): bool => ! $hasRouteFilter($table))
            ->defaultPaginationPageOption(25)
            ->defaultSort('stop_order')
            ->recordTitle(fn (RouteStop $record): ?string => $record->location?->name)
            ->description(function (Table $table) use ($isInsideRoute): ?string {
                if ($table->getLivewire()->isTableReordering()) {
                    return self::REORDERING_HINT;
                }

                // Sin horarios el aviso ya está en el estado vacío de la tabla.
                if (! $isInsideRoute || ! self::routeHasActiveSchedules($table)) {
                    return null;
                }

                return self::referenceScheduleHint((int) $table->getLivewire()->getOwnerRecord()->getKey());
            })
            ->emptyStateHeading(fn (Table $table): string => $isInsideRoute && ! self::routeHasActiveSchedules($table)
                ? 'Esta ruta todavía no tiene horarios'
                : 'Todavía no hay paradas')
            ->emptyStateDescription(fn (Table $table): ?string => $isInsideRoute && ! self::routeHasActiveSchedules($table)
                ? self::NO_SCHEDULES_MESSAGE
                : null)
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->rowIndex()
                    ->alignCenter(),
                TextColumn::make('location.name')
                    ->label('Parada')
                    ->searchable(! $isInsideRoute)
                    ->sortable(! $isInsideRoute),
                TextColumn::make('arrival_offset_minutes')
                    ->label('Llega')
                    ->alignCenter()
                    ->sortable(false)
                    ->formatStateUsing(fn (?int $state, RouteStop $record): ?string => self::clockForStop($record, $state))
                    ->placeholder('—'),
                TextColumn::make('departure_offset_minutes')
                    ->label('Sale')
                    ->alignCenter()
                    ->sortable(false)
                    ->formatStateUsing(fn (?int $state, RouteStop $record): ?string => self::clockForStop($record, $state))
                    ->placeholder('—'),
                TextColumn::make('other_schedules')
                    ->label('En otros horarios')
                    ->state(fn (RouteStop $record): array => self::clocksInOtherSchedules($record))
                    ->listWithLineBreaks()
                    ->placeholder('—'),
            ])
            ->filters($isInsideRoute ? [
                TrashedFilter::make(),
            ] : [
                // El eje del listado: siempre hay una ruta elegida (por defecto
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
                    self::warnAboutAffectedTickets(EditAction::make()),
                    self::warnAboutAffectedTickets(DeleteAction::make(), isDeletion: true),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                self::warnAboutAffectedTickets(EditAction::make())->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Editar', 'class' => 'hidden md:inline-flex',
                ]),
                self::warnAboutAffectedTickets(DeleteAction::make(), isDeletion: true)->button()->hiddenLabel()->extraAttributes([
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
                    DeleteBulkAction::make()
                        ->modalDescription(fn (Collection $records): string => AffectedTickets::warning(
                            $records->pluck('route_id')->unique()->sum(fn (int $routeId): int => AffectedTickets::forRoute($routeId)),
                        ) ?? '¿Seguro que querés eliminar las paradas elegidas?'),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->reorderable(
                'stop_order',
                // Fuera de una ruta, el drag & drop sólo se permite con una ruta
                // elegida: cruzar filas de rutas distintas corrompería el orden
                // y los horarios.
                condition: $isInsideRoute ? true : $hasRouteFilter,
            )
            // El nuevo orden se guarda recién al confirmar (ver el trait
            // ReordersRouteStopsKeepingTimes).
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering): Action => self::configureReorderTrigger($action, $isReordering))
            // Filament oculta encabezado y acciones mientras se reordena: los
            // botones de confirmar y descartar van en un encabezado propio.
            ->header(fn (Table $table): ?View => $table->getLivewire()->isTableReordering()
                ? view('filament.resources.route-stops.reordering-header', [
                    'hint' => self::REORDERING_HINT,
                    'hasPendingChanges' => $table->getLivewire()->hasPendingStopOrder(),
                ])
                : null)
            ->headerActions([
                // No se ve en pantalla: lo abre requestStopOrderConfirmation()
                // cuando hay boletos vendidos, para confirmar antes de guardar.
                self::saveStopOrderAction(),
            ]);
    }

    public const REORDERING_HINT = 'Arrastrá las paradas al orden nuevo. Los cambios se guardan recién al tocar "Confirmar cambios".';

    /**
     * El botón propio de Filament sólo entra al modo reordenar; para salir
     * están "Confirmar cambios" y "Descartar cambios".
     */
    protected static function configureReorderTrigger(Action $action, bool $isReordering): Action
    {
        // Filament dibuja este botón sin mirar si está oculto: se esconde por
        // CSS mientras se reordena.
        return $action
            ->button()
            ->label('Cambiar orden')
            ->icon(Heroicon::ArrowsUpDown)
            ->extraAttributes($isReordering ? ['class' => 'hidden'] : [], merge: true);
    }

    protected static function saveStopOrderAction(): Action
    {
        return Action::make('saveStopOrder')
            ->label('Confirmar cambios')
            ->icon(Heroicon::Check)
            ->color('primary')
            ->visible(fn (mixed $livewire): bool => $livewire->isTableReordering())
            // Con boletos vendidos, se avisa antes de guardar.
            ->requiresConfirmation(fn (mixed $livewire): bool => $livewire->pendingStopOrderAffectedTickets() > 0)
            ->modalHeading('Guardar el nuevo orden')
            ->modalDescription(fn (mixed $livewire): ?string => ($warning = AffectedTickets::warning($livewire->pendingStopOrderAffectedTickets()))
                ? $warning.' ¿Querés guardar el nuevo orden igual?'
                : null)
            ->modalSubmitActionLabel('Guardar orden')
            ->action(fn (mixed $livewire) => $livewire->confirmStopOrder());
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
     * Antes de editar o eliminar una parada, avisa cuántos boletos vendidos
     * de viajes próximos de la ruta van a ver el cambio de horas.
     *
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    protected static function warnAboutAffectedTickets(Action $action, bool $isDeletion = false): Action
    {
        return $action->modalDescription(function (RouteStop $record) use ($isDeletion): ?string {
            $warning = AffectedTickets::warning(AffectedTickets::forRoute($record->route_id));

            if ($isDeletion) {
                return $warning !== null
                    ? $warning.' ¿Querés eliminar la parada igual?'
                    : '¿Seguro que querés eliminar esta parada del recorrido?';
            }

            return $warning;
        });
    }

    /**
     * Ruta del formulario: la de la pantalla de ruta (relation manager), la
     * elegida en el select o la de la parada que se edita.
     */
    public static function contextRouteId(Get $get, ?RouteStop $record, mixed $livewire): ?int
    {
        if ($livewire instanceof RelationManager) {
            return (int) $livewire->getOwnerRecord()->getKey();
        }

        if (filled($get('route_id'))) {
            return (int) $get('route_id');
        }

        return $record?->route_id;
    }

    /**
     * Horario activo contra el que se miden las horas de las paradas: el de
     * salida más temprana. Si se pide uno puntual y está activo, se respeta.
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

    /** Texto que indica a qué horario corresponden las horas del recorrido. */
    public static function referenceScheduleHint(?int $routeId): ?string
    {
        $schedule = self::referenceSchedule(routeId: $routeId);

        if ($schedule === null) {
            return $routeId ? self::NO_SCHEDULES_MESSAGE : null;
        }

        return 'Horas del horario de las '.$schedule->departure_time->format('H:i').' (el primero del día). En los demás horarios se corren igual.';
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

        $schedule = self::activeSchedulesOf($record)->first();

        return $schedule ? self::offsetToClock($offset, $schedule) : null;
    }

    /**
     * Hora de salida de la parada en cada horario activo que no es el de
     * referencia, ej. ["14:00 → sale 14:45"].
     *
     * @return list<string>
     */
    public static function clocksInOtherSchedules(RouteStop $record): array
    {
        if ($record->departure_offset_minutes === null) {
            return [];
        }

        return self::activeSchedulesOf($record)
            ->skip(1)
            ->map(fn (Schedule $schedule): string => 'Horario '.$schedule->departure_time->format('H:i')
                .': sale '.self::offsetToClock($record->departure_offset_minutes, $schedule))
            ->values()
            ->all();
    }

    /**
     * Mensaje si el destino ya está en el recorrido de la ruta (contando las
     * paradas eliminadas, que la base sigue considerando para la unicidad).
     */
    public static function duplicateLocationMessage(?int $routeId, ?int $locationId, ?RouteStop $ignoreStop = null): ?string
    {
        if (! $routeId || ! $locationId) {
            return null;
        }

        $duplicate = RouteStop::withTrashed()
            ->where('route_id', $routeId)
            ->where('location_id', $locationId)
            ->when($ignoreStop?->exists, fn (Builder $query) => $query->whereKeyNot($ignoreStop->getKey()))
            ->first();

        if ($duplicate === null) {
            return null;
        }

        return $duplicate->trashed()
            ? 'Esta localidad estaba en el recorrido y fue eliminada: podés restaurarla desde el filtro de eliminados.'
            : 'Esta localidad ya está en el recorrido.';
    }

    /**
     * Mensaje si la parada sale antes que la parada anterior del recorrido.
     * Para una parada nueva, la anterior es la última (se agrega al final).
     */
    public static function earlierThanPreviousStopMessage(?int $routeId, ?string $clock, ?RouteStop $stop = null): ?string
    {
        if (! $routeId || blank($clock)) {
            return null;
        }

        $schedule = self::referenceSchedule(routeId: $routeId);

        if ($schedule === null) {
            return null;
        }

        $previous = RouteStop::query()
            ->with('location')
            ->where('route_id', $routeId)
            ->whereNotNull('departure_offset_minutes')
            ->when($stop?->exists, fn (Builder $query) => $query
                ->whereKeyNot($stop->getKey())
                ->where('stop_order', '<', $stop->stop_order))
            ->orderByDesc('stop_order')
            ->first();

        $offset = self::clockToOffset($clock, $schedule);

        if ($previous === null) {
            return self::isEarlierOffset($offset, 0)
                ? sprintf('Esta parada no puede salir antes de la salida del horario (%s).', $schedule->departure_time->format('H:i'))
                : null;
        }

        if (! self::isEarlierOffset($offset, $previous->departure_offset_minutes)) {
            return null;
        }

        return sprintf(
            'Esta parada no puede salir antes que la parada anterior (%s, %s).',
            $previous->location?->name,
            self::offsetToClock($previous->departure_offset_minutes, $schedule),
        );
    }

    /**
     * ¿El offset queda antes que el de referencia?
     *
     * Las horas se pasan a minutos "dando la vuelta" por la medianoche, así
     * que una hora apenas anterior (07:30 sobre una salida 08:00) se vuelve
     * casi 24 h después. Entre dos paradas seguidas no puede haber más de
     * MAX_GAP_MINUTES: un salto mayor es en realidad una hora anterior.
     */
    public static function isEarlierOffset(int $offset, int $reference): bool
    {
        return $offset < $reference || ($offset - $reference) > self::MAX_GAP_MINUTES;
    }

    /** @return Collection<int, Schedule> */
    protected static function activeSchedulesOf(RouteStop $record): Collection
    {
        $schedules = $record->relationLoaded('route') && $record->route?->relationLoaded('activeSchedules')
            ? $record->route->activeSchedules
            : ($record->route?->activeSchedules()->get() ?? new Collection);

        return $schedules->sortBy(fn (Schedule $schedule): string => $schedule->departure_time->format('H:i'))->values();
    }

    protected static function routeHasActiveSchedules(Table $table): bool
    {
        return self::referenceSchedule(routeId: (int) $table->getLivewire()->getOwnerRecord()->getKey()) !== null;
    }

    /**
     * Muestra el offset guardado como hora del reloj del horario de referencia.
     *
     * Se lee el atributo del modelo y no el estado del campo: el TimePicker ya
     * hidrató los minutos como si fueran un timestamp (45 → 21:00:45 en la
     * zona horaria de Argentina) y tomar ese estado mostraba una hora errónea.
     */
    protected static function hydrateClock(TimePicker $component, ?RouteStop $record, string $attribute): void
    {
        $offset = $record?->getAttribute($attribute);

        if ($offset === null) {
            return;
        }

        $schedule = self::referenceSchedule(routeId: $record->route_id);

        if ($schedule !== null) {
            $component->state(self::offsetToClock((int) $offset, $schedule));
        }
    }

    /** Convierte la hora elegida en minutos desde la salida del horario de referencia. */
    protected static function dehydrateClock(mixed $state, ?int $routeId): ?int
    {
        if (blank($state)) {
            return null;
        }

        $schedule = self::referenceSchedule(routeId: $routeId);

        return $schedule ? self::clockToOffset((string) $state, $schedule) : null;
    }
}
