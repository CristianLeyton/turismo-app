<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Services\TicketPdfService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //EditAction::make(),
            DeleteAction::make()->icon('heroicon-m-trash'),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            Action::make('download_pdf')
                    ->label('Descargar')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('primary')
                    ->button()->extraAttributes(
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
        ];
    }
}
