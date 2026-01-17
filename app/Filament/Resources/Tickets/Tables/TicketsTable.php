<?php

namespace App\Filament\Resources\Tickets\Tables;

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
use Filament\Tables\Table;
use App\Services\TicketPdfService;
use App\Models\Ticket;
use Livewire\Livewire;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                /*                 TextColumn::make('sale.id')
                    ->label('Venta')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable(), */

                TextColumn::make('id')
                    ->label('Pasaje')
                    ->badge()
                    ->color('gray')
                    ->prefix('#')
                    ->sortable()
                    ->searchable()
                    ->alignCenter(),

                TextColumn::make('passenger.last_name')
                    ->label('Pasajero')

                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->passenger->first_name . ' ' . $record->passenger->last_name,
                    )
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('passenger.dni')
                    ->label('DNI')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                /* 
                TextColumn::make('trip.trip_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(), */

                TextColumn::make('trip.trip_date')
                    ->label('Fecha')
                    ->formatStateUsing(function ($record) {
                        $date = $record->trip->trip_date?->format('d/m/Y');
                        $time = $record->trip->schedule->departure_time?->format('H:i');
                        return $date && $time ? "$date $time" : '—';
                    })
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('trip.route.name')
                    ->label('Ruta')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('origin.name')
                    ->label('Origen')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('destination.name')
                    ->label('Destino')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('seat.seat_number')
                    ->label('Asiento')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),

                /*                 TextColumn::make('price')
                    ->label('Precio')
                    ->money('ARS')
                    ->sortable(), */

                IconColumn::make('is_round_trip')
                    ->label('Diferido')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),

                IconColumn::make('travels_with_child')
                    ->label('Viaja con niño')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->filters([
                TrashedFilter::make(),
            ])
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
                    ->action(function (Ticket $record, $livewire) {
                        $pdfService = new TicketPdfService();
                        $sale = $record->sale;

                        if ($record->is_round_trip) {
                            // Para viajes de ida y vuelta, obtener todos los tickets del pasajero
                            $passengerTickets = $sale->tickets()
                                ->where('passenger_id', $record->passenger_id)
                                ->with(['trip', 'returnTrip', 'origin', 'destination', 'seat'])
                                ->get();

                            // Generar un solo PDF con ambos boletos
                            $pdfContent = $pdfService->generateRoundTripTicket($sale, $passengerTickets);

                            $passengerName = str_replace(' ', '_', $record->passenger->full_name);
                            $tripId = $record->trip->id;
                            $seatNumber = $record->seat ? $record->seat->seat_number : 'SinAsiento';

                            $filename = "{$passengerName}_IdaYVuelta_Viaje{$tripId}_Asiento{$seatNumber}.pdf";

                            // Guardar en sesión para descarga
                            session([
                                'ticket_pdf_content' => base64_encode($pdfContent),
                                'ticket_pdf_filename' => $filename
                            ]);

                            // Abrir en nueva pestaña
                            $url = route('tickets.download');
                            $livewire->js("window.open('{$url}', '_blank')");
                        } else {
                            // Para viaje solo de ida
                            $ticketsCollection = collect([$record]);
                            $pdfContent = $pdfService->generatePassengerTickets($sale, $ticketsCollection);

                            $passengerName = str_replace(' ', '_', $record->passenger->full_name);
                            $tripId = $record->trip->id;
                            $seatNumber = $record->seat ? $record->seat->seat_number : 'SinAsiento';

                            $filename = "{$passengerName}_Viaje{$tripId}_Asiento{$seatNumber}.pdf";

                            // Guardar en sesión para descarga
                            session([
                                'ticket_pdf_content' => base64_encode($pdfContent),
                                'ticket_pdf_filename' => $filename
                            ]);

                            // Abrir en nueva pestaña
                            $url = route('tickets.download');
                            $livewire->js("window.open('{$url}', '_blank')");
                        }
                    }),
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
