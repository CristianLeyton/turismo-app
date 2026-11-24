<?php

namespace App\Filament\Resources\TicketAudit\Classes;

use App\Filament\Resources\TicketAudit\Schemas\TicketAuditDetailSchema;
use App\Filament\Resources\Tickets\Pages\EditTicket\EditTicketForm;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDateChange;
use App\Models\TicketEdit;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Tabla de auditoría compacta: boletos eliminados y reprogramados.
 *
 * 100% de solo lectura: sin acciones de escritura, sin bulk actions y sin
 * recordUrl. El detalle completo (estado, eliminación, historial de
 * reprogramaciones con autor y antes/después) se ve con el botón "Ver",
 * que abre un modal.
 */
class TicketAuditTable
{
    /**
     * Acción "Ver": abre el detalle de auditoría en un modal de solo lectura.
     * Se usa en el menú del celular y en el botón de escritorio.
     */
    private static function viewAction(): Action
    {
        return Action::make('ver')
            ->label('Ver detalles')
            ->icon(Heroicon::Eye)
            ->color('gray')
            ->modalHeading(fn (Ticket $record): string => "Boleto N°{$record->id}")
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->schema(fn (Ticket $record): array => TicketAuditDetailSchema::make());
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query
                    ->with([
                        'origin',
                        'destination',
                        'sale.user',
                        'trip.schedule',
                        'passenger',
                        'seat',
                        'deletedBy',
                        'dateChanges.user',
                        'dateChanges.client',
                        'edits.user',
                    ])
                    ->withCount(['dateChanges', 'edits'])
                    // Incluye las ediciones del pasajero hechas desde su ida
                    // o su vuelta, para el estado y la última actividad.
                    ->withLastVisibleEditAt();
            })
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('passenger.last_name')
                    ->label('Pasajero')
                    ->limit(24)
                    ->visibleFrom('md')
                    ->tooltip(fn ($state, $record) => $record->passenger?->last_name.' '.$record->passenger?->first_name)
                    ->formatStateUsing(
                        fn ($state, $record) => ($record->passenger?->last_name ?? '').' '.($record->passenger?->first_name ?? ''),
                    )
                    ->placeholder('—'),

                TextColumn::make('passenger.dni')
                    ->label('DNI')
                    ->visibleFrom('md')
                    ->placeholder('—'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Ticket $record): string => TicketAuditDetailSchema::estado($record))
                    ->color(fn (Ticket $record): string => match (true) {
                        $record->trashed() => 'danger',
                        $record->dateChanges->isNotEmpty() => 'warning',
                        default => 'info',
                    }),

                TextColumn::make('ultima_actividad')
                    ->label('Última actividad')
                    ->state(fn (Ticket $record) => self::ultimaActividad($record))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin actividad')
                    ->badge()
                    ->color('info')
                    ->sortable(
                        query: function (Builder $query, string $direction): Builder {
                            // "Mayor de tres fechas" en SQL portable: ni
                            // GREATEST (existe en MySQL, no en SQLite viejo)
                            // ni MAX multi-arg (funcionaba en MySQL < 8.0.13,
                            // hoy da error 1064) sirven en ambos motores. El
                            // CASE WHEN funciona igual en MySQL y SQLite.
                            $eliminado = "COALESCE(tickets.deleted_at, '1970-01-01 00:00:00')";
                            $reprogramado = "COALESCE((SELECT MAX(ticket_date_changes.created_at) FROM ticket_date_changes WHERE ticket_date_changes.ticket_id = tickets.id), '1970-01-01 00:00:00')";
                            // Última edición visible (propias y del pasajero desde
                            // su ida o su vuelta): misma regla que el historial.
                            $lastEdit = TicketEdit::visibleForOuterTicket()->selectRaw('MAX(visible_edits.created_at)');
                            $editado = "COALESCE(({$lastEdit->toSql()}), '1970-01-01 00:00:00')";

                            // $editado aparece tres veces en el CASE: sus bindings también.
                            $bindings = [...$lastEdit->getBindings(), ...$lastEdit->getBindings(), ...$lastEdit->getBindings()];

                            return $query->orderByRaw("
                                CASE
                                    WHEN {$eliminado} >= {$reprogramado} AND {$eliminado} >= {$editado} THEN {$eliminado}
                                    WHEN {$reprogramado} >= {$editado} THEN {$reprogramado}
                                    ELSE {$editado}
                                END {$direction}
                            ", $bindings);
                        }
                    )
                    ->visibleFrom('md'),

                /*                TextColumn::make('last_change_at')
                    ->label('Última modificación')
                    ->state(fn(Ticket $record): string => self::ultimaModificacion($record))
                    ->badge()
                    ->color('info')
                    ->visibleFrom('md') */
            ])
            ->defaultSort('ultima_actividad', 'desc')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([
                // Menú de tres puntitos (sólo celular): el ítem conserva su label.
                ActionGroup::make([
                    self::viewAction()->button(),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                self::viewAction()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Ver detalles',
                    'class' => 'hidden md:inline-flex',
                ]),
            ])
            ->toolbarActions([])
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->filters([
                SelectFilter::make('audit_state')
                    ->label('Estado')
                    ->options([
                        'deleted' => 'Eliminados',
                        'rescheduled' => 'Reprogramados',
                        'edited' => 'Editados',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'deleted' => $query->whereNotNull('tickets.deleted_at'),
                            'rescheduled' => $query->whereHas('dateChanges'),
                            'edited' => $query->withVisibleEdits(),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): array {
                        return match ($data['value'] ?? null) {
                            'deleted' => ['Estado: Eliminados'],
                            'rescheduled' => ['Estado: Reprogramados'],
                            'edited' => ['Estado: Editados'],
                            default => [],
                        };
                    }),

                self::actorFilter(),

                Filter::make('ticket_id')
                    ->label('Número de boleto')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('id')
                            ->label('Boleto N°')
                            ->placeholder('Número de boleto')
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['id'],
                            fn (Builder $query, int $id): Builder => $query->where('tickets.id', $id),
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        return ($data['id'] ?? null)
                            ? ['Boleto: '.$data['id']]
                            : [];
                    }),

                Filter::make('passenger_search')
                    ->label('Pasajero')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('query')
                            ->label('Pasajero')
                            ->placeholder('Nombre o DNI del pasajero'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['query'],
                            fn (Builder $query, string $search): Builder => $query->whereHas('passenger', function (Builder $q) use ($search) {
                                $q->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('dni', 'like', "%{$search}%");
                            }),
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        return ($data['query'] ?? null)
                            ? ['Pasajero: '.$data['query']]
                            : [];
                    }),

                self::editSearchFilter(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->emptyStateHeading('No hay boletos eliminados, reprogramados ni editados')
            ->emptyStateDescription('Cuando un boleto sea eliminado, reprogramado o se corrijan sus datos, aparecerá acá con su historial.');
    }

    /**
     * Quién hizo el cambio: "Clientes" (asignaron la fecha desde su panel) o
     * "Vendedores" (cualquier usuario del panel, sin importar su rol).
     *
     * Se decide por el tipo de autor guardado en cada reprogramación, no por
     * `user_id`: un usuario borrado del todo sigue siendo "Vendedores". Las
     * ediciones y eliminaciones sólo las hace personal. Sin elegir nada se ve
     * todo el historial.
     *
     * Sólo existe con "Panel de clientes activo" encendido. Como los filtros se
     * guardan en la sesión, la consulta y el indicador también lo comprueban:
     * apagado el panel, un valor guardado no filtra ni se anuncia.
     */
    private static function actorFilter(): SelectFilter
    {
        return SelectFilter::make('actor')
            ->label('Realizado por')
            ->options([
                'clients' => 'Clientes',
                'sellers' => 'Vendedores',
            ])
            ->placeholder('Todos')
            ->visible(fn (): bool => Setting::panelClientesActivo())
            ->query(function (Builder $query, array $data): Builder {
                if (! Setting::panelClientesActivo()) {
                    return $query;
                }

                return match ($data['value'] ?? null) {
                    'clients' => $query->whereHas('dateChanges', fn (Builder $changes) => $changes->where('actor_type', TicketDateChange::ACTOR_CLIENT)),
                    'sellers' => $query->where(fn (Builder $sellers) => $sellers
                        ->whereHas('dateChanges', fn (Builder $changes) => $changes->where('actor_type', TicketDateChange::ACTOR_USER))
                        ->orWhereNotNull('tickets.deleted_at')
                        ->orWhere(fn (Builder $edited): Builder => $edited->withVisibleEdits())),
                    default => $query,
                };
            })
            ->indicateUsing(function (array $data): array {
                if (! Setting::panelClientesActivo()) {
                    return [];
                }

                return match ($data['value'] ?? null) {
                    'clients' => ['Realizado por: Clientes'],
                    'sellers' => ['Realizado por: Vendedores'],
                    default => [],
                };
            });
    }

    /**
     * Fecha de la última acción sobre el boleto (eliminación, reprogramación
     * o edición de datos). null si no hubo ninguna.
     */
    public static function ultimaActividad(Ticket $record): ?Carbon
    {
        return collect([
            $record->deleted_at,
            $record->dateChanges->max('created_at'),
            self::lastVisibleEditAt($record),
        ])
            ->filter()
            ->map(fn ($value) => $value instanceof CarbonInterface ? $value : Carbon::parse($value))
            ->max();
    }

    /**
     * Campos que registra el historial de ediciones, en el orden del
     * formulario de edición.
     *
     * @var list<string>
     */
    private const EDITABLE_FIELDS = [
        TicketEdit::FIELD_DNI,
        TicketEdit::FIELD_LAST_NAME,
        TicketEdit::FIELD_FIRST_NAME,
        TicketEdit::FIELD_PHONE,
        TicketEdit::FIELD_TRAVELS_WITH_CHILD,
        TicketEdit::FIELD_CHILD_NAME,
        TicketEdit::FIELD_CHILD_DNI,
        TicketEdit::FIELD_TRAVELS_WITH_PETS,
        TicketEdit::FIELD_PET_NAMES,
        TicketEdit::FIELD_PET_COUNT,
        TicketEdit::FIELD_PRICE,
        TicketEdit::FIELD_PAYMENT_METHOD,
        'observation',
    ];

    /**
     * Búsqueda de ediciones: qué campo se cambió, quién lo cambió y cuándo.
     * Las condiciones elegidas se aplican sobre LA MISMA edición (una sola
     * subconsulta), así "DNI + Julian + 04/11" no mezcla una edición de DNI
     * de otro usuario con una de precio de Julian. Cuentan también las
     * ediciones del pasajero hechas desde su ida o su vuelta.
     */
    private static function editSearchFilter(): Filter
    {
        return Filter::make('edit_search')
            ->schema([
                Select::make('fields')
                    ->label('Campo cambiado')
                    ->multiple()
                    ->options(collect(self::EDITABLE_FIELDS)
                        ->mapWithKeys(fn (string $field): array => [$field => EditTicketForm::fieldLabel($field)])
                        ->all())
                    ->placeholder('Cualquiera'),
                Select::make('user_id')
                    ->label('Editado por')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('Cualquiera'),
                DatePicker::make('from')
                    ->label('Editado desde')
                    ->displayFormat('d/m/Y'),
                DatePicker::make('to')
                    ->label('Editado hasta')
                    ->displayFormat('d/m/Y'),
            ])
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->columnSpanFull()
            ->query(function (Builder $query, array $data): Builder {
                $fields = array_values(array_filter((array) ($data['fields'] ?? [])));
                $userId = $data['user_id'] ?? null;
                $from = $data['from'] ?? null;
                $to = $data['to'] ?? null;

                if ($fields === [] && blank($userId) && blank($from) && blank($to)) {
                    return $query;
                }

                return $query->withVisibleEdits(fn (QueryBuilder $edits) => $edits
                    ->when($fields !== [], fn (QueryBuilder $q) => $q->whereIn('visible_edits.field', $fields))
                    ->when(filled($userId), fn (QueryBuilder $q) => $q->where('visible_edits.user_id', $userId))
                    ->when(filled($from), fn (QueryBuilder $q) => $q->whereDate('visible_edits.created_at', '>=', $from))
                    ->when(filled($to), fn (QueryBuilder $q) => $q->whereDate('visible_edits.created_at', '<=', $to)));
            })
            ->indicateUsing(function (array $data): array {
                $indicators = [];
                $fields = array_values(array_filter((array) ($data['fields'] ?? [])));

                if ($fields !== []) {
                    $indicators[] = 'Campo: '.implode(', ', array_map(fn (string $field): string => EditTicketForm::fieldLabel($field), $fields));
                }

                if (filled($data['user_id'] ?? null)) {
                    $indicators[] = 'Editado por: '.(User::query()->whereKey($data['user_id'])->value('name') ?? '—');
                }

                $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'])->format('d/m/Y') : null;
                $to = filled($data['to'] ?? null) ? Carbon::parse($data['to'])->format('d/m/Y') : null;

                if ($from || $to) {
                    $indicators[] = match (true) {
                        $from && $to => "Editado: {$from} – {$to}",
                        (bool) $from => "Editado desde {$from}",
                        default => "Editado hasta {$to}",
                    };
                }

                return $indicators;
            });
    }

    /**
     * Fecha de la última edición visible del boleto (propias y del pasajero
     * desde su ida o su vuelta). La tabla la trae calculada en su consulta
     * (`last_visible_edit_at`); si el registro llegó por otro camino, se
     * calcula acá.
     */
    public static function lastVisibleEditAt(Ticket $record): mixed
    {
        if (array_key_exists('last_visible_edit_at', $record->getAttributes())) {
            return $record->getAttribute('last_visible_edit_at');
        }

        return $record->visibleEdits()->max('created_at');
    }

    /**
     * Acción más reciente sobre el boleto (eliminación, reprogramación o
     * edición de datos), con su fecha. Si no hay ninguna, devuelve '—'.
     */
    public static function ultimaModificacion(Ticket $record): string
    {
        $eventos = collect();

        if ($record->dateChanges->isNotEmpty()) {
            $at = $record->dateChanges->max('created_at');
            $eventos->push(['at' => $at, 'label' => 'Reprogramado']);
        }

        if ($record->edits->isNotEmpty()) {
            $at = $record->edits->max('created_at');
            $eventos->push(['at' => $at, 'label' => 'Editado']);
        }

        if ($record->trashed() && $record->deleted_at) {
            $eventos->push(['at' => $record->deleted_at, 'label' => 'Eliminado']);
        }

        $ultimo = $eventos
            ->sortByDesc(fn (array $evento): int => $evento['at']?->getTimestamp() ?? 0)
            ->first();

        return $ultimo['label'];

        /*         return $ultimo
                    ? $ultimo['label'] . ' el ' . $ultimo['at']->format('d/m/Y')
                    : '—'; */
    }
}
