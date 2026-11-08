<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketPdfService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['origin', 'destination', 'sale.user', 'trip.schedule', 'passenger', 'seat']);
            })
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('md'),

                TextColumn::make('passenger.last_name')
                    ->label('Nombre')
                    ->limit(22)
                    ->tooltip(fn ($state, $record) => $record->passenger->last_name.' '.$record->passenger->first_name)
                    ->formatStateUsing(
                        fn ($state, $record) => $record->passenger->last_name.' '.$record->passenger->first_name,
                    ),

                TextColumn::make('passenger.dni')
                    ->label('DNI')
                    ->visibleFrom('md')
                    ->placeholder('—'),

                TextColumn::make('sale.sale_date')
                    ->label('Emisión')
                    ->badge()
                    ->color('success')
                    ->date('d/m/Y')
                    ->url(fn (Ticket $record) => TicketResource::getUrl('view', ['record' => $record])),

                TextColumn::make('sale.user.name')
                    ->label('Vendedor')
                    ->badge()
                    ->color('warning')
                    ->visibleFrom('md')
                    ->placeholder('—'),

                TextColumn::make('trip.trip_date')
                    ->label('Salida')
                    ->placeholder(fn (Ticket $record) => $record->isPendingDate() ? 'Sin fecha' : '—')
                    ->formatStateUsing(function ($record) {
                        if ($record->isPendingDate()) {
                            return 'Sin fecha';
                        }

                        $date = $record->trip?->trip_date?->format('d/m/Y');
                        $time = $record->trip?->schedule?->departure_time?->format('H:i');

                        if ($date && $time) {
                            return "$date $time";
                        }

                        return $date ?: '—';
                    })
                    ->badge()
                    ->visibleFrom('md')
                    ->color(fn (Ticket $record) => $record->isPendingDate() ? 'warning' : 'info')
                    ->url(fn (Ticket $record) => TicketResource::getUrl('view', ['record' => $record])),

                TextColumn::make('origin_location_id')
                    ->label('Ruta')
                    ->formatStateUsing(function ($record) {
                        if ($record->origin && $record->destination) {
                            return $record->origin->name.' → '.$record->destination->name;
                        }

                        return 'Ruta no disponible';
                    })
                    ->alignCenter()
                    ->badge()
                    ->visibleFrom('md')
                    ->color('warning'),

                TextColumn::make('seat.seat_number')
                    ->label('Asiento')
                    ->badge()
                    ->color('primary')
                    ->visibleFrom('md')
                    ->placeholder(fn (Ticket $record) => $record->isPendingDate() ? 'X' : '—')
                    ->alignCenter(),

                IconColumn::make('is_round_trip')
                    ->label('Diferido')
                    ->visibleFrom('md')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(null)
            ->recordAction(null)
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->filters([

                Filter::make('ticket_id')
                    ->label('Número de boleto')
                    ->form([
                        TextInput::make('id')
                            ->label('Boleto N°')
                            ->placeholder('Número de boleto')
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['id'],
                                fn (Builder $query, int $id): Builder => $query->where('tickets.id', $id),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['id'] ?? null) {
                            $indicators[] = 'Boleto: '.$data['id'];
                        }

                        return $indicators;
                    }),

                Filter::make('passenger_search')
                    ->label('Pasajero')
                    ->form([
                        TextInput::make('query')
                            ->label('Pasajero')
                            ->placeholder('Nombre o DNI del pasajero'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['query'],
                                fn (Builder $query, string $search): Builder => $query->whereHas('passenger', function (Builder $q) use ($search) {
                                    $q->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%")
                                        ->orWhere('dni', 'like', "%{$search}%");
                                }),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['query'] ?? null) {
                            $indicators[] = 'Pasajero: '.$data['query'];
                        }

                        return $indicators;
                    }),

                Filter::make('sale_date')
                    ->label('Fecha de venta')
                    ->form([
                        DatePicker::make('date')
                            ->label('Fecha de emisión')
                            ->native(true)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date'],
                                fn (Builder $query, $date): Builder => $query->whereHas('sale', function (Builder $q) use ($date) {
                                    $q->whereDate('sale_date', '=', $date);
                                }),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date'] ?? null) {
                            $indicators[] = 'Venta: '.\Carbon\Carbon::parse($data['date'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                Filter::make('vendedor')
                    ->label('Vendedor')
                    ->form([
                        Select::make('user_id')
                            ->label('Vendedor')
                            ->placeholder('Todos')
                            ->options(fn () => User::query()->where('id', '!=', 1)->orderBy('name')->pluck('name', 'id')->toArray()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['user_id'] ?? null,
                            fn (Builder $query, $userId): Builder => $query->whereHas('sale', fn (Builder $q) => $q->where('user_id', $userId)),
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['user_id'])) {
                            $user = User::find($data['user_id']);
                            if ($user) {
                                $indicators[] = 'Vendedor: '.$user->name;
                            }
                        }

                        return $indicators;
                    }),

                Filter::make('trip_date')
                    ->label('Fecha de salida')
                    ->form([
                        DatePicker::make('date')
                            ->label('Fecha de salida')
                            ->native(true)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date'],
                                fn (Builder $query, $date): Builder => $query->whereHas('trip', function (Builder $q) use ($date) {
                                    $q->whereDate('trip_date', '=', $date);
                                }),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date'] ?? null) {
                            $indicators[] = 'Salida: '.\Carbon\Carbon::parse($data['date'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                Filter::make('route_search')
                    ->label('Ruta')
                    ->form([
                        TextInput::make('query')
                            ->label('Ruta')
                            ->placeholder('Origen o destino'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['query'],
                                fn (Builder $query, string $search): Builder => $query->where(function (Builder $q) use ($search) {
                                    $q->whereHas('origin', function (Builder $subQ) use ($search) {
                                        $subQ->where('name', 'like', "%{$search}%");
                                    })
                                        ->orWhereHas('destination', function (Builder $subQ) use ($search) {
                                            $subQ->where('name', 'like', "%{$search}%");
                                        });
                                }),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['query'] ?? null) {
                            $indicators[] = 'Ruta: '.$data['query'];
                        }

                        return $indicators;
                    }),

                // Filtrar boletos que no tienen fecha de salida
                Filter::make('trip_id')
                    ->label('Ver únicamente boletos sin fecha')
                    ->toggle()
                    ->columnSpan(2)
                    ->query(function (Builder $query): Builder {
                        return $query->whereNull('trip_id');
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(6)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->hiddenFilterIndicators()
            ->recordActions([
                // Menú de tres puntitos (sólo celular): los ítems conservan su label.
                ActionGroup::make([
                    self::viewAction()->button()->size('sm'),
                    self::downloadAction()->button()->size('sm'),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                self::viewAction()->button()->hiddenLabel()->extraAttributes(
                    ['title' => 'Ver boleto', 'class' => 'hidden md:inline-flex']
                ),
                self::downloadAction()->button()->hiddenLabel()->extraAttributes(
                    ['title' => 'Descargar boleto', 'class' => 'hidden md:inline-flex']
                ),
            ]);
    }

    /** "Ver boleto": se declara una vez y se usa en el menú del celular y en los botones de escritorio. */
    private static function viewAction(): ViewAction
    {
        return ViewAction::make()->label('Ver boleto');
    }

    /** "Descargar": el PDF del boleto, igual en las dos variantes de la fila. */
    private static function downloadAction(): Action
    {
        return Action::make('download_pdf')
            ->label('Descargar')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('primary')
            ->action(function (Ticket $record) {
                $pdf = (new TicketPdfService)->forTicket($record);

                return response()->streamDownload(
                    fn () => print ($pdf['content']),
                    $pdf['filename'],
                    ['Content-Type' => 'application/pdf']
                );
            });
    }
}
