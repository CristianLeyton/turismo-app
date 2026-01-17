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
                TextColumn::make('sale.id')
                    ->label('Venta')
                    ->searchable(),
                TextColumn::make('trip.id')
                    ->label('Viaje')
                    ->searchable(),
                TextColumn::make('origin_location_id')
                    ->label('Origen')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('destination_location_id')
                    ->label('Destino')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('returnTrip.id')
                    ->label('Viaje de regreso')
                    ->searchable(),
                TextColumn::make('passenger.id')
                    ->label('Pasajero')
                    ->searchable(),
                TextColumn::make('seat.id')
                    ->label('Asiento')
                    ->searchable(),
                IconColumn::make('is_round_trip')
                    ->label('Ida y vuelta')
                    ->boolean(),
                IconColumn::make('travels_with_child')
                    ->label('Viaja con niño')    
                    ->boolean(),
                TextColumn::make('price')
                    ->label('Precio')
                    ->money()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                //EditAction::make(),
                Action::make('download_pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
