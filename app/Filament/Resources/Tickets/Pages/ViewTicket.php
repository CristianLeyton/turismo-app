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
use Illuminate\Support\Facades\Auth;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //EditAction::make(),
            Action::make('reschedule')
                ->label('Reprogramar boleto')
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->visible(fn (Ticket $record) => (bool) Auth::user()?->is_admin)
                ->authorize('reschedule')
                ->url(function (Ticket $record) {
                    $ticketId = $record->id;

                    return TicketResource::getUrl('reschedule', ['record' => $record])
                        . '?ticket=' . $ticketId;
                }),
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
        ];
    }
}
