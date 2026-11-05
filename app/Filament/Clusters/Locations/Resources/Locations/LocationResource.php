<?php

namespace App\Filament\Clusters\Locations\Resources\Locations;

use App\Filament\Clusters\Locations\LocationsCluster;
use App\Filament\Clusters\Locations\Resources\Locations\Pages\ManageLocations;
use App\Models\Location;
use App\Models\Route;
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
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use WeakMap;

class LocationResource extends Resource
{
    /** @var WeakMap<Location, EloquentCollection<int, Route>>|null */
    private static ?WeakMap $routesUsingCache = null;

    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $cluster = LocationsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'destino';

    protected static ?string $pluralModelLabel = 'Destinos';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Cuenta paradas activas de rutas no eliminadas: cada ruta tiene el
            // destino como mucho una vez (UNIQUE route_id, location_id).
            ->modifyQueryUsing(fn(Builder $query) => $query->withCount([
                'routeStops as routes_count' => fn(Builder $stops) => $stops->whereHas('route'),
            ]))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('routes_count')
                    ->label('Rutas')
                    ->alignCenter()
                    ->tooltip('En cuántas rutas aparece este destino como parada.'),
            ])
            ->recordAction(null)
            ->recordUrl(null)
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Menú de tres puntitos (sólo celular): los ítems conservan
                // su label para que el menú abierto muestre el texto.
                ActionGroup::make([
                    self::warnAboutRoutesUsingIt(EditAction::make()->button()),
                    self::guardAgainstRoutesUsingIt(DeleteAction::make()->button()),
                    self::guardAgainstRoutesUsingIt(ForceDeleteAction::make()->button()),
                    RestoreAction::make()->button(),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                self::warnAboutRoutesUsingIt(EditAction::make())->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Editar',
                    'class' => 'hidden md:inline-flex',
                ]),
                self::guardAgainstRoutesUsingIt(DeleteAction::make())->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar',
                    'class' => 'hidden md:inline-flex',
                ]),
                self::guardAgainstRoutesUsingIt(ForceDeleteAction::make())->button()->hiddenLabel()->extraAttributes([
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
                    self::guardBulkAgainstRoutesUsingIt(DeleteBulkAction::make()),
                    self::guardBulkAgainstRoutesUsingIt(ForceDeleteBulkAction::make()),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Rutas no eliminadas que tienen el destino en su recorrido. Se memoriza
     * por registro porque el modal lo consulta varias veces.
     *
     * @return EloquentCollection<int, Route>
     */
    public static function routesUsing(Location $location): EloquentCollection
    {
        self::$routesUsingCache ??= new WeakMap;

        return self::$routesUsingCache[$location] ??= Route::query()
            ->whereHas('stops', fn(Builder $stops) => $stops->where('location_id', $location->getKey()))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** "Oran - Salta, Salta - Oran y 3 más". */
    public static function routeNames(EloquentCollection $routes, int $limit = 5): string
    {
        $names = $routes->pluck('name');

        if ($names->count() <= $limit) {
            return $names->join(', ', ' y ');
        }

        return $names->take($limit)->join(', ') . ' y ' . ($names->count() - $limit) . ' más';
    }

    public static function cannotDeleteMessage(EloquentCollection $routes): string
    {
        $count = $routes->count();

        return ($count === 1 ? 'Este destino está en el recorrido de la ruta ' : "Este destino está en el recorrido de {$count} rutas: ")
            . self::routeNames($routes)
            . '. Para eliminarlo, primero sacalo de ese recorrido o eliminá la ruta.';
    }

    public static function editWarningMessage(EloquentCollection $routes): ?string
    {
        if ($routes->isEmpty()) {
            return null;
        }

        $count = $routes->count();

        return ($count === 1 ? 'Este destino se usa en la ruta ' : "Este destino se usa en {$count} rutas: ")
            . self::routeNames($routes)
            . '. Si cambiás el nombre, se va a ver así en esos recorridos y en los boletos que lo tienen.';
    }

    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    protected static function warnAboutRoutesUsingIt(Action $action): Action
    {
        return $action->modalDescription(fn(Location $record): ?string => self::editWarningMessage(self::routesUsing($record)));
    }

    /**
     * Un destino que está en el recorrido de una ruta no se elimina: el modal
     * explica el motivo y no deja confirmar. También se corta antes de
     * borrar, porque la base borra en cascada las paradas al eliminarlo
     * definitivamente.
     *
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    protected static function guardAgainstRoutesUsingIt(Action $action): Action
    {
        $isBlocked = fn(Location $record): bool => self::routesUsing($record)->isNotEmpty();

        return $action
            ->modalHeading(fn(Location $record): ?string => $isBlocked($record) ? 'No se puede eliminar el destino' : null)
            ->modalDescription(fn(Location $record): ?string => $isBlocked($record)
                ? self::cannotDeleteMessage(self::routesUsing($record))
                : null)
            ->modalSubmitAction(fn(Location $record): ?bool => $isBlocked($record) ? false : null)
            ->modalCancelActionLabel(fn(Location $record): ?string => $isBlocked($record) ? 'Cerrar' : null)
            ->before(function (Action $action, Location $record): void {
                $routes = Route::query()
                    ->whereHas('stops', fn(Builder $stops) => $stops->where('location_id', $record->getKey()))
                    ->orderBy('name')
                    ->get(['id', 'name']);

                if ($routes->isEmpty()) {
                    return;
                }

                Notification::make()
                    ->title('No se puede eliminar el destino')
                    ->body(self::cannotDeleteMessage($routes))
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
    protected static function guardBulkAgainstRoutesUsingIt(Action $action): Action
    {
        return $action->before(function (Action $action, EloquentCollection $records): void {
            $blocked = $records->filter(fn(Location $location): bool => Route::query()
                ->whereHas('stops', fn(Builder $stops) => $stops->where('location_id', $location->getKey()))
                ->exists());

            if ($blocked->isEmpty()) {
                return;
            }

            Notification::make()
                ->title('No se eliminó ningún destino')
                ->body('Estos destinos están en el recorrido de alguna ruta: '
                    . $blocked->pluck('name')->join(', ')
                    . '. Para eliminarlos, primero sacalos de esos recorridos.')
                ->danger()
                ->send();

            $action->cancel();
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLocations::route('/'),
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
