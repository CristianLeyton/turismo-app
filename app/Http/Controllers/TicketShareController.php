<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\TicketPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Links públicos de boletos: /boletos/{token}.
 *
 * Token compartible (App\Models\Concerns\HasShareToken): el portador del
 * link recibe directamente el PDF del boleto, sin login ni página
 * intermedia. El PDF se regenera al vuelo (refleja ediciones posteriores
 * del boleto) y vive mientras exista el registro: un boleto borrado da 404.
 * Solo se resuelven tokens de boleto ('t_'): los links de venta ('s_') ya
 * no se entregan y dan 404.
 */
class TicketShareController extends Controller
{
    /**
     * El link del boleto: su PDF en línea.
     */
    public function show(string $token): SymfonyResponse
    {
        return $this->ticketPdf($token, 'inline');
    }

    /**
     * El mismo PDF, en línea o como adjunto (descarga).
     */
    public function pdf(Request $request, string $token): SymfonyResponse
    {
        $disposition = $request->query('disposition', 'inline');
        abort_unless(in_array($disposition, ['inline', 'attachment'], true), 404);

        return $this->ticketPdf($token, $disposition);
    }

    /**
     * PDF de un boleto. Para un diferido: la vista del viaje redondo cuando
     * `is_round_trip`, la individual en el resto de los casos (mismo
     * criterio que el panel de clientes).
     */
    private function ticketPdf(string $token, string $disposition): SymfonyResponse
    {
        $ticket = Ticket::findByShareToken($token);

        abort_unless($ticket !== null, 404);

        $pdf = (new TicketPdfService)->forTicket($ticket);

        return Response::make($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'Content-Disposition' => $disposition.'; filename="'.rawurlencode($pdf['filename']).'"',
        ]);
    }
}
