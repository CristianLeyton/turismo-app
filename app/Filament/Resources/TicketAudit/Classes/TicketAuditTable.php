<?php

namespace App\Filament\Resources\TicketAudit\Classes;

use App\Filament\Resources\TicketAudit\Schemas\TicketAuditDetailSchema;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;

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
                        'edits.user',
                    ])
                    ->withCount(['dateChanges', 'edits']);
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
                    ->tooltip(fn($state, $record) => $record->passenger?->last_name . ' ' . $record->passenger?->first_name)
                    ->formatStateUsing(
                        fn($state, $record) => ($record->passenger?->last_name ?? '') . ' ' . ($record->passenger?->first_name ?? ''),
                    )
                    ->placeholder('—'),

                TextColumn::make('passenger.dni')
                    ->label('DNI')
                    ->visibleFrom('md')
                    ->placeholder('—'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn(Ticket $record): string => TicketAuditDetailSchema::estado($record))
                    ->color(fn(Ticket $record): string => match (true) {
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
                            $editado = "COALESCE((SELECT MAX(ticket_edits.created_at) FROM ticket_edits WHERE ticket_edits.ticket_id = tickets.id), '1970-01-01 00:00:00')";

                            return $query->orderByRaw("
                                CASE
                                    WHEN {$eliminado} >= {$reprogramado} AND {$eliminado} >= {$editado} THEN {$eliminado}
                                    WHEN {$reprogramado} >= {$editado} THEN {$reprogramado}
                                    ELSE {$editado}
                                END {$direction}
                            ");
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
                Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->button()
                    ->color('gray')
                    ->modalHeading(fn(Ticket $record): string => "Boleto N°{$record->id}")
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->schema(fn(Ticket $record): array => TicketAuditDetailSchema::make()),
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
                            'edited' => $query->whereHas('edits'),
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
                            fn(Builder $query, int $id): Builder => $query->where('tickets.id', $id),
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        return ($data['id'] ?? null)
                            ? ['Boleto: ' . $data['id']]
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
                            fn(Builder $query, string $search): Builder => $query->whereHas('passenger', function (Builder $q) use ($search) {
                                $q->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('dni', 'like', "%{$search}%");
                            }),
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        return ($data['query'] ?? null)
                            ? ['Pasajero: ' . $data['query']]
                            : [];
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->emptyStateHeading('No hay boletos eliminados, reprogramados ni editados')
            ->emptyStateDescription('Cuando un boleto sea eliminado, reprogramado o se corrijan sus datos, aparecerá acá con su historial.');
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
            $record->edits->max('created_at'),
        ])
            ->filter()
            ->map(fn ($value) => $value instanceof CarbonInterface ? $value : Carbon::parse($value))
            ->max();
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
            ->sortByDesc(fn(array $evento): int => $evento['at']?->getTimestamp() ?? 0)
            ->first();

            return $ultimo['label'];

/*         return $ultimo
            ? $ultimo['label'] . ' el ' . $ultimo['at']->format('d/m/Y')
            : '—'; */
    }
}
