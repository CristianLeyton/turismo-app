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
                    ])
                    ->withCount('dateChanges');
            })
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('passenger.last_name')
                    ->label('Pasajero')
                    ->limit(24)
                    ->tooltip(fn ($state, $record) => $record->passenger?->last_name . ' ' . $record->passenger?->first_name)
                    ->formatStateUsing(
                        fn ($state, $record) =>
                        ($record->passenger?->last_name ?? '') . ' ' . ($record->passenger?->first_name ?? ''),
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
                    ->color(fn (Ticket $record): string => $record->trashed() ? 'danger' : 'warning'),

                TextColumn::make('last_change_at')
                    ->label('Última modificación')

                    ->state(function (Ticket $record): string {
                        if ($record->dateChanges->isNotEmpty()) {
                            return 'Reprogramado el ' . $record->dateChanges->max('created_at')->format('d/m/Y');
                        }

                        if ($record->trashed()) {
                            return 'Eliminado el ' . $record->deleted_at?->format('d/m/Y');
                        }

                        return '—';
                    })
                    ->badge()
                    ->color('info')
                    ->visibleFrom('md'),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([
                Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->button()
                    ->color('gray')
                    ->modalHeading(fn (Ticket $record): string => "Boleto N°{$record->id}")
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->schema(fn (Ticket $record): array => TicketAuditDetailSchema::make()),
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
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'deleted' => $query->whereNotNull('tickets.deleted_at'),
                            'rescheduled' => $query->whereHas('dateChanges'),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): array {
                        return match ($data['value'] ?? null) {
                            'deleted' => ['Estado: Eliminados'],
                            'rescheduled' => ['Estado: Reprogramados'],
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
                            fn (Builder $query, int $id): Builder => $query->where('tickets.id', $id),
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
                            fn (Builder $query, string $search): Builder => $query->whereHas('passenger', function (Builder $q) use ($search) {
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
            ->emptyStateHeading('No hay boletos eliminados ni reprogramados')
            ->emptyStateDescription('Cuando un boleto sea eliminado o reprogramado, aparecerá acá con su historial.');
    }
}
