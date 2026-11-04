<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\TicketPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * PDF de un boleto para el personal del panel admin (vista previa y descarga
 * del "Ver boleto"). A diferencia del link público no usa token: exige sesión
 * del panel y el permiso de ver boletos, y acepta boletos eliminados.
 */
class AdminTicketPdfController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket): SymfonyResponse
    {
        $disposition = $request->query('disposition', 'inline');
        abort_unless(in_array($disposition, ['inline', 'attachment'], true), 404);

        Gate::authorize('view', $ticket);

        $pdf = (new TicketPdfService)->forTicket($ticket);

        return Response::make($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'Content-Disposition' => $disposition.'; filename="'.rawurlencode($pdf['filename']).'"',
        ]);
    }
}
