<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Ticket;
use App\Services\ClientTicketAssignmentService;
use App\Services\TicketPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ClientTicketPdfController extends Controller
{
    /**
     * "Ver boleto" del panel de clientes. Mismo scoping que el PDF (sesión
     * + boleto del DNI del cliente); el token compartible se genera recién
     * acá, al hacer click, no al listar los boletos.
     */
    public function show(Ticket $ticket): RedirectResponse
    {
        $client = \App\Support\ClientSession::current();

        abort_unless($client !== null, 403);

        abort_unless(
            app(ClientTicketAssignmentService::class)->clientOwnsTicket($ticket, $client),
            404
        );

        if (! Setting::descargaAutomaticaPdf()) {
            return redirect()->away($ticket->shareUrl());
        }

        return redirect()->route('clientes.boletos.pdf', ['ticket' => $ticket->id]);
    }

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

        $pdf = (new TicketPdfService)->forTicket($ticket);

        return Response::make($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.rawurlencode($pdf['filename']).'"',
        ]);
    }
}
