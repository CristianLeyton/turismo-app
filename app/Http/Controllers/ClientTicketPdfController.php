<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\ClientTicketAssignmentService;
use App\Services\TicketPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ClientTicketPdfController extends Controller
{
    /**
     * PDF del boleto del cliente. Generado al vuelo: sólo si el ticket
     * pertenece al cliente identificado en la sesión (scoping por DNI;
     * un ID ajeno da 404, nunca el PDF de otro).
     */
    public function download(Request $request, Ticket $ticket)
    {
        $client = \App\Support\ClientSession::current();

        abort_unless($client !== null, 403);

        abort_unless(
            app(ClientTicketAssignmentService::class)->clientOwnsTicket($ticket, $client),
            404
        );

        $pdfService = new TicketPdfService;

        $passengerTickets = $ticket->sale->tickets()
            ->where('passenger_id', $ticket->passenger_id)
            ->with(['trip', 'returnTrip', 'origin', 'destination', 'seat'])
            ->get();

        $pdfContent = $ticket->is_round_trip
            ? $pdfService->generateRoundTripTicket($ticket->sale, $passengerTickets)
            : $pdfService->generatePassengerTickets($ticket->sale, collect([$ticket]));

        $colectivo = str_replace(' ', '_', $ticket->trip?->bus?->name ?? 'Sin fecha');
        $filename = "Boleto_N°{$ticket->id}_{$colectivo}.pdf";

        return Response::make($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . rawurlencode($filename) . '"',
        ]);
    }
}
