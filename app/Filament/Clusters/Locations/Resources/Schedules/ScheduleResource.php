<?php

namespace App\Filament\Clusters\Locations\Resources\Schedules;

use App\Filament\Clusters\Locations\LocationsCluster;
use App\Filament\Clusters\Locations\Resources\Schedules\Pages\ManageSchedules;
use App\Models\Schedule;
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
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use WeakMap;

class ScheduleResource extends Resource
{
    /** @var WeakMap<Schedule, int>|null */
    private static ?WeakMap $upcomingTicketsCache = null;

    protected static ?string $model = Schedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Clock;

    protected static ?string $cluster = LocationsCluster::class;

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?string $modelLabel = 'horario';

    protected static ?string $pluralModelLabel = 'Horarios';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 2;

    /** Los horarios se editan desde la pestaña "Horarios" de cada ruta. */
    protected static bool $shouldRegisterNavigation = false;

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
                    ->preload()
                    ->validationMessages([
                        'required' => 'Debe seleccionar una ruta.',
                    ]),
                TextInput::make('name')
                    ->label('Nombre del horario')
                    ->placeholder('Ej: Mañana, Tarde, Noche')
                    ->maxLength(255)
                    ->helperText('Opcional: nombre descriptivo del horario'),
                TimePicker::make('departure_time')
                    ->label('Hora de salida')
                    ->required()
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->helperText('Las horas de las paradas se corren junto con esta hora.')
                    ->validationMessages([
                        'required' => 'La hora de salida es obligatoria.',
                    ]),
                TimePicker::make('arrival_time')
                    ->label('Hora de llegada')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->helperText('Hora estimada de llegada al destino final'),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Dentro de una ruta los horarios son siempre de esa ruta.
        $isInsideRoute = $table->getLivewire() instanceof RelationManager;

        return $table
            ->recordTitleAttribute('display_name')
            ->recordUrl(null)
            ->recordAction(null)
            ->columns([
                TextColumn::make('route.name')
                    ->label('Ruta')
                    ->searchable()
                    ->sortable()
                    ->hidden($isInsideRoute),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('departure_time')
                    ->label('Salida')
                    ->time('H:i')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('arrival_time')
                    ->label('Llegada')
                    ->time('H:i')
                    ->placeholder('-')
                    ->alignCenter(),
                TextColumn::make('display_name')
                    ->label('Horario completo')
                    ->getStateUsing(fn (Schedule $record) => $record->display_name)
                    ->searchable(false),
                ToggleColumn::make('is_active')
                    ->label('Activo')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('trips_count')
                    ->label('Viajes')
                    ->counts('trips')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('route')
                    ->relationship('route', 'name')
                    ->label('Ruta')
                    ->preload()
                    ->hidden($isInsideRoute),
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activo')
                    ->falseLabel('Inactivo'),
                TrashedFilter::make(),
            ])
            ->defaultSort('route_id')
            ->defaultSort('departure_time')
            ->recordActions([
                // Menú de tres puntitos (sólo celular): los ítems conservan
                // su label para que el menú abierto muestre el texto.
                ActionGroup::make([
                    self::warnAboutAffectedTickets(EditAction::make()),
                    self::deactivateInsteadOfDeleteAction(),
                    self::blockWhenItHasUpcomingTickets(DeleteAction::make()),
                    self::blockWhenItHasUpcomingTickets(ForceDeleteAction::make()),
                    RestoreAction::make(),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                self::warnAboutAffectedTickets(EditAction::make())->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Editar', 'class' => 'hidden md:inline-flex',
                ]),
                self::deactivateInsteadOfDeleteAction()->button()->extraAttributes([
                    'class' => 'hidden md:inline-flex',
                ]),
                self::blockWhenItHasUpcomingTickets(DeleteAction::make())->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar', 'class' => 'hidden md:inline-flex',
                ]),
                self::blockWhenItHasUpcomingTickets(ForceDeleteAction::make())->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente', 'class' => 'hidden md:inline-flex',
                ]),
                RestoreAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar', 'class' => 'hidden md:inline-flex',
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::blockBulkWhenAnyHasUpcomingTickets(DeleteBulkAction::make()),
                    self::blockBulkWhenAnyHasUpcomingTickets(ForceDeleteBulkAction::make()),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Boletos vendidos en viajes próximos del horario. Se memoriza por
     * registro porque varias acciones de la misma fila lo consultan.
     */
    public static function upcomingTicketsCount(Schedule $schedule): int
    {
        self::$upcomingTicketsCache ??= new WeakMap;

        return self::$upcomingTicketsCache[$schedule] ??= AffectedTickets::forSchedule($schedule);
    }

    /** Explicación cuando un horario no se puede eliminar por tener boletos. */
    public static function cannotDeleteMessage(int $count): string
    {
        return ($count === 1 ? 'Este horario tiene 1 boleto vendido' : "Este horario tiene {$count} boletos vendidos")
            .' en viajes próximos, por eso no se puede eliminar. Podés desactivarlo para que no se use en nuevas ventas.';
    }

    /**
     * Antes de editar un horario, avisa cuántos boletos vendidos de viajes
     * próximos van a ver la hora nueva.
     *
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    protected static function warnAboutAffectedTickets(Action $action): Action
    {
        return $action->modalDescription(fn (Schedule $record): ?string => AffectedTickets::warning(self::upcomingTicketsCount($record)));
    }

    /**
     * Un horario con boletos próximos no se elimina: la acción se oculta (en
     * su lugar aparece "Desactivar") y, por las dudas, se corta antes de
     * borrar.
     *
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    protected static function blockWhenItHasUpcomingTickets(Action $action): Action
    {
        return $action
            ->hidden(fn (Schedule $record): bool => self::upcomingTicketsCount($record) > 0)
            ->before(function (Action $action, Schedule $record): void {
                $count = AffectedTickets::forSchedule($record);

                if ($count === 0) {
                    return;
                }

                Notification::make()
                    ->title('No se puede eliminar el horario')
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
    protected static function blockBulkWhenAnyHasUpcomingTickets(Action $action): Action
    {
        return $action->before(function (Action $action, Collection $records): void {
            $blocked = $records->filter(fn (Schedule $schedule): bool => AffectedTickets::forSchedule($schedule) > 0);

            if ($blocked->isEmpty()) {
                return;
            }

            Notification::make()
                ->title('No se eliminó ningún horario')
                ->body('Estos horarios tienen boletos vendidos en viajes próximos: '
                    .$blocked->map(fn (Schedule $schedule): string => $schedule->display_name)->join(', ')
                    .'. Podés desactivarlos para que no se usen en nuevas ventas.')
                ->danger()
                ->send();

            $action->cancel();
        });
    }

    protected static function deactivateInsteadOfDeleteAction(): Action
    {
        return Action::make('deactivate')
            ->label('Desactivar')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('warning')
            ->visible(fn (Schedule $record): bool => $record->is_active
                && ! $record->trashed()
                && self::upcomingTicketsCount($record) > 0
                && (bool) Auth::user()?->can('update', $record))
            ->requiresConfirmation()
            ->modalHeading('Desactivar horario')
            ->modalDescription(fn (Schedule $record): string => self::cannotDeleteMessage(self::upcomingTicketsCount($record))
                .' Los boletos ya vendidos siguen siendo válidos.')
            ->modalSubmitActionLabel('Desactivar')
            ->action(function (Schedule $record): void {
                $record->update(['is_active' => false]);

                Notification::make()
                    ->title('Horario desactivado')
                    ->body('Ya no se ofrece en nuevas ventas.')
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSchedules::route('/'),
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
