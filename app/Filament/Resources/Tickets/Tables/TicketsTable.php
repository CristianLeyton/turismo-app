<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use App\Services\TicketPdfService;
use App\Models\Ticket;
use Livewire\Livewire;
use Illuminate\Database\Eloquent\Builder;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['origin', 'destination', 'sale', 'trip.schedule', 'passenger', 'seat']);
            })
            ->columns([
                TextColumn::make('id')
                    ->label('Boleto N°')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('sale.sale_date')
                    ->label('Emisión')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->date('d/m/Y')
                    ->url(fn(Ticket $record) => TicketResource::getUrl('view', ['record' => $record])),

                TextColumn::make('trip.trip_date')
                    ->label('Salida')
                    ->formatStateUsing(function ($record) {
                        $date = $record->trip->trip_date?->format('d/m/Y');
                        $time = $record->trip->schedule->departure_time?->format('H:i');
                        return $date && $time ? "$date $time" : '—';
                    })
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->url(fn(Ticket $record) => TicketResource::getUrl('view', ['record' => $record])),

                TextColumn::make('passenger.last_name')
                    ->label('Nombre')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->passenger->first_name . ' ' . $record->passenger->last_name,
                    )
                    ->sortable(),

                TextColumn::make('passenger.dni')
                    ->label('DNI'),

                TextColumn::make('origin_location_id')
                    ->label('Ruta')
                    ->formatStateUsing(function ($record) {
                        if ($record->origin && $record->destination) {
                            return $record->origin->name . ' → ' . $record->destination->name;
                        }
                        return 'Ruta no disponible';
                    })
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('seat.seat_number')
                    ->label('Asiento')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                IconColumn::make('is_round_trip')
                    ->label('Diferido')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('travels_with_child')
                    ->label('Viaja con menor')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('sale.sale_date', 'desc')
            ->recordUrl(null)
            /* ->persistSortInSession() */
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
                            ->minValue(1)
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['id'],
                                fn(Builder $query, int $id): Builder => $query->where('tickets.id', $id),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['id'] ?? null) {
                            $indicators[] = 'Boleto: ' . $data['id'];
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
                                fn(Builder $query, $date): Builder => $query->whereHas('sale', function (Builder $q) use ($date) {
                                    $q->whereDate('sale_date', '=', $date);
                                }),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date'] ?? null) {
                            $indicators[] = 'Venta: ' . \Carbon\Carbon::parse($data['date'])->format('d/m/Y');
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
                                fn(Builder $query, $date): Builder => $query->whereHas('trip', function (Builder $q) use ($date) {
                                    $q->whereDate('trip_date', '=', $date);
                                }),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date'] ?? null) {
                            $indicators[] = 'Salida: ' . \Carbon\Carbon::parse($data['date'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),

                Filter::make('passenger_search')
                    ->label('Pasajero')
                    ->form([
                        TextInput::make('query')
                            ->label('Pasajero')
                            ->placeholder('Nombre o DNI del pasajero')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['query'],
                                fn(Builder $query, string $search): Builder => $query->whereHas('passenger', function (Builder $q) use ($search) {
                                    $q->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%")
                                        ->orWhere('dni', 'like', "%{$search}%");
                                }),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['query'] ?? null) {
                            $indicators[] = 'Pasajero: ' . $data['query'];
                        }
                        return $indicators;
                    }),

                Filter::make('route_search')
                    ->label('Ruta')
                    ->form([
                        TextInput::make('query')
                            ->label('Ruta')
                            ->placeholder('Origen o destino')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['query'],
                                fn(Builder $query, string $search): Builder => $query->where(function (Builder $q) use ($search) {
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
                            $indicators[] = 'Ruta: ' . $data['query'];
                        }
                        return $indicators;
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->hiddenFilterIndicators()
            ->recordActions([
                ViewAction::make()->button()->hiddenLabel()->extraAttributes(
                    ['title' => 'Ver boleto']
                ),
                //EditAction::make(),
                Action::make('download_pdf')
                    ->label('Descargar')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('primary')
                    ->button()->hiddenLabel()->extraAttributes(
                        ['title' => 'Descargar boleto']
                    )
                    ->action(function (Ticket $record) {

                        $pdfService = new TicketPdfService();
                        $sale = $record->sale;

                        if ($record->is_round_trip) {
                            $passengerTickets = $sale->tickets()
                                ->where('passenger_id', $record->passenger_id)
                                ->with(['trip', 'returnTrip', 'origin', 'destination', 'seat'])
                                ->get();

                            $pdfContent = $pdfService->generateRoundTripTicket($sale, $passengerTickets);
                        } else {
                            $pdfContent = $pdfService->generatePassengerTickets($sale, collect([$record]));
                        }

                        $ticketId = $record->id;
                        $colectivo = str_replace(' ', '_', $record->trip->bus->name);
                        $filename = "Boleto_N°{$ticketId}_{$colectivo}.pdf";

                        return response()->streamDownload(
                            fn() => print($pdfContent),
                            $filename,
                            ['Content-Type' => 'application/pdf']
                        );
                    })
            ])
            /* ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]) */;
    }
}
