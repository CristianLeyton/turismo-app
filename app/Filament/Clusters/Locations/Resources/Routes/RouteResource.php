<?php

namespace App\Filament\Clusters\Locations\Resources\Routes;

use App\Filament\Clusters\Locations\LocationsCluster;
use App\Filament\Clusters\Locations\Resources\Routes\Pages\EditRoute;
use App\Filament\Clusters\Locations\Resources\Routes\Pages\ManageRoutes;
use App\Filament\Clusters\Locations\Resources\Routes\RelationManagers\SchedulesRelationManager;
use App\Filament\Clusters\Locations\Resources\Routes\RelationManagers\StopsRelationManager;
use App\Models\Route;
use App\Support\AffectedTickets;
use BackedEnum;
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
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use WeakMap;

class RouteResource extends Resource
{
    /** @var WeakMap<Route, int>|null */
    private static ?WeakMap $pendingTicketsCache = null;

    protected static ?string $model = Route::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $cluster = LocationsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'ruta';

    protected static ?string $pluralModelLabel = 'Rutas';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bus_id')
                    ->label('Colectivo')
                    ->relationship('bus', 'name')
                    ->preload()
                    ->placeholder('Sin asignar'),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true)
                    ->helperText('Las rutas inactivas no aparecen en nuevas ventas, pero siguen funcionando para viajes y boletos ya vendidos.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query
                ->with(['bus', 'stops.location'])
                ->withCount(['stops', 'activeSchedules']))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn($state, $record) => $record->name),
                TextColumn::make('summary')
                    ->label('Recorrido')
                    ->badge()
                    ->color('warning')
                    ->state(fn(Route $record): ?string => self::routeSummary($record))
                    ->visibleFrom('md')
                    ->placeholder('—'),
                TextColumn::make('stops_count')
                    ->label('Paradas')
                    ->visibleFrom('md')
                    ->alignCenter(),
                TextColumn::make('active_schedules_count')
                    ->label('Horarios')
                    ->visibleFrom('md')
                    ->alignCenter(),
                TextColumn::make('missing')
                    ->label('Falta')
                    ->state(fn(Route $record): array => self::missingParts($record))
                    ->badge()
                    ->visibleFrom('md')
                    ->color('danger')
                    ->placeholder('—'),
                TextColumn::make('bus.name')
                    ->label('Colectivo')
                    ->sortable()
                    ->visibleFrom('md')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),
                ToggleColumn::make('is_active')
                    ->label('Activa')
                    ->sortable(),
            ])
            ->recordAction(null)
            ->recordUrl(null)
            ->filters([
                // Por defecto sólo se ven las rutas activas: el filtro arranca
                // en "Sí" y el usuario puede quitarlo para ver inactivas.
                TernaryFilter::make('is_active')
                    ->label('Activa')
                    ->default(true),
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Menú de tres puntitos (sólo celular): los ítems conservan
                // su label para que el menú abierto muestre el texto.
                ActionGroup::make([
                    EditAction::make()->button()
                        ->url(fn(Route $record): string => self::getUrl('edit', ['record' => $record])),
                    self::guardAgainstPendingTrips(DeleteAction::make()->button()),
                    self::guardAgainstPendingTrips(ForceDeleteAction::make()->button()),
                    RestoreAction::make()->button(),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                EditAction::make()->button()->hiddenLabel()
                    ->url(fn(Route $record): string => self::getUrl('edit', ['record' => $record]))
                    ->extraAttributes([
                        'title' => 'Editar',
                        'class' => 'hidden md:inline-flex',
                    ]),
                self::guardAgainstPendingTrips(DeleteAction::make())->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar',
                    'class' => 'hidden md:inline-flex',
                ]),
                self::guardAgainstPendingTrips(ForceDeleteAction::make())->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente',
                    'class' => 'hidden md:inline-flex',
                ]),
                RestoreAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar',
                    'class' => 'hidden md:inline-flex',
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::guardBulkAgainstPendingTrips(DeleteBulkAction::make()),
                    self::guardBulkAgainstPendingTrips(ForceDeleteBulkAction::make()),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Boletos vendidos en viajes de la ruta que todavía no partieron. Se
     * memoriza por registro porque el modal lo consulta varias veces.
     */
    public static function pendingTicketsCount(Route $route): int
    {
        self::$pendingTicketsCache ??= new WeakMap;

        return self::$pendingTicketsCache[$route] ??= AffectedTickets::forRoute($route);
    }

    /** Explicación cuando una ruta no se puede eliminar por tener viajes pendientes. */
    public static function cannotDeleteMessage(int $count): string
    {
        return ($count === 1
            ? 'Esta ruta tiene 1 boleto vendido en un viaje que todavía no partió'
            : "Esta ruta tiene {$count} boletos vendidos en viajes que todavía no partieron")
            . ', por eso no se puede eliminar. Podés desactivarla para que no se use en nuevas ventas.';
    }

    /**
     * Una ruta con viajes pendientes no se elimina: el modal explica el
     * motivo, no deja confirmar y ofrece desactivarla. Además se corta antes
     * de borrar, por si el estado cambió con el modal abierto.
     *
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    public static function guardAgainstPendingTrips(Action $action): Action
    {
        $isBlocked = fn(Route $record): bool => self::pendingTicketsCount($record) > 0;

        return $action
            ->modalHeading(fn(Route $record): ?string => $isBlocked($record) ? 'No se puede eliminar la ruta' : null)
            ->modalDescription(fn(Route $record): ?string => $isBlocked($record)
                ? self::cannotDeleteMessage(self::pendingTicketsCount($record))
                : null)
            ->modalSubmitAction(fn(Route $record): ?bool => $isBlocked($record) ? false : null)
            ->modalCancelActionLabel(fn(Route $record): ?string => $isBlocked($record) ? 'Cerrar' : null)
            ->extraModalFooterActions(fn(Route $record): array => $isBlocked($record) && $record->is_active && ! $record->trashed()
                ? [self::deactivateRouteAction($record)]
                : [])
            ->before(function (Action $action, Route $record): void {
                $count = AffectedTickets::forRoute($record);

                if ($count === 0) {
                    return;
                }

                Notification::make()
                    ->title('No se puede eliminar la ruta')
                    ->body(self::cannotDeleteMessage($count))
                    ->danger()
                    ->send();

                $action->cancel();
            });
    }

    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    protected static function guardBulkAgainstPendingTrips(Action $action): Action
    {
        return $action->before(function (Action $action, Collection $records): void {
            $blocked = $records->filter(fn(Route $route): bool => AffectedTickets::forRoute($route) > 0);

            if ($blocked->isEmpty()) {
                return;
            }

            Notification::make()
                ->title('No se eliminó ninguna ruta')
                ->body('Estas rutas tienen boletos vendidos en viajes que todavía no partieron: '
                    . $blocked->pluck('name')->join(', ')
                    . '. Podés desactivarlas para que no se usen en nuevas ventas.')
                ->danger()
                ->send();

            $action->cancel();
        });
    }

    protected static function deactivateRouteAction(Route $route): Action
    {
        return Action::make('deactivateRoute')
            ->label('Desactivar ruta')
            ->color('warning')
            ->visible(fn(): bool => (bool) Auth::user()?->can('update', $route))
            ->cancelParentActions()
            ->action(function () use ($route): void {
                $route->update(['is_active' => false]);

                Notification::make()
                    ->title('Ruta desactivada')
                    ->body('Ya no se ofrece en nuevas ventas. Los boletos ya vendidos siguen siendo válidos.')
                    ->success()
                    ->send();
            });
    }

    /** "Origen → destino" según la primera y la última parada del recorrido. */
    public static function routeSummary(Route $route): ?string
    {
        $stops = $route->stops;

        if ($stops->isEmpty()) {
            return null;
        }

        $origin = $stops->first()->location?->name;
        $destination = $stops->last()->location?->name;

        return $stops->count() === 1 ? $origin : "{$origin} → {$destination}";
    }

    /**
     * Lo que le falta a una ruta activa para poder venderse.
     *
     * @return list<string>
     */
    public static function missingParts(Route $route): array
    {
        if (! $route->is_active) {
            return [];
        }

        return array_values(array_filter([
            $route->stops_count === 0 ? 'Sin paradas' : null,
            $route->active_schedules_count === 0 ? 'Sin horarios' : null,
        ]));
    }

    public static function getRelations(): array
    {
        return [
            StopsRelationManager::class,
            SchedulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoutes::route('/'),
            'edit' => EditRoute::route('/{record}/edit'),
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
