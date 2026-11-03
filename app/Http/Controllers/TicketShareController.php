<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Ticket;
use App\Services\TicketPdfService;
use App\Support\WhatsAppShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Links públicos de boletos: /boletos/{token}.
 *
 * Token compartible (App\Models\Concerns\HasShareToken): el portador del
 * link puede previsualizar el PDF y compartirlo, sin login. El PDF se
 * regenera al vuelo (refleja ediciones posteriores del boleto) y vive
 * mientras exista el registro: un boleto borrado da 404. Prefijo 't_' =
 * boleto individual; 's_' = PDF combinado de toda la venta.
 */
class TicketShareController extends Controller
{
    public function show(string $token)
    {
        $ticket = Ticket::findByShareToken($token);

        if ($ticket) {
            return $this->ticketShow($ticket);
        }

        $sale = Sale::findByShareToken($token);

        if ($sale) {
            return $this->saleShow($sale);
        }

        abort(404);
    }

    public function pdf(Request $request, string $token)
    {
        $disposition = $request->query('disposition', 'inline');
        abort_unless(in_array($disposition, ['inline', 'attachment'], true), 404);

        $ticket = Ticket::findByShareToken($token);

        if ($ticket) {
            return $this->ticketPdf($ticket, $disposition);
        }

        $sale = Sale::findByShareToken($token);

        if ($sale) {
            return $this->salePdf($sale, $disposition);
        }

        abort(404);
    }

    // ------------------------------------------------------------------
    // Boleto individual
    // ------------------------------------------------------------------

    private function ticketShow(Ticket $ticket)
    {
        $ticket->load(['passenger', 'trip.bus', 'trip.schedule', 'origin', 'destination', 'seat', 'returnTrip', 'sale.tickets.passenger']);

        return view('boletos.share', [
            'token' => $ticket->share_token,
            'ticket' => $ticket,
            'sale' => $ticket->sale,
            'isSaleLink' => false,
            'heading' => "Boleto N°{$ticket->id}",
            'subheading' => self::routeLabel($ticket),
            'filename' => self::ticketFilename($ticket),
            'pdfUrl' => $ticket->sharePdfUrl('inline'),
            'downloadUrl' => $ticket->sharePdfUrl('attachment'),
            'whatsappTargets' => WhatsAppShare::forSale($ticket->sale, $ticket->shareUrl()),
        ]);
    }

    /**
     * PDF de un boleto. Para un diferido: mismo criterio que el panel de
     * clientes — la vista del viaje redondo cuando `is_round_trip`, la
     * individual en el resto de los casos.
     */
    private function ticketPdf(Ticket $ticket, string $disposition)
    {
        $pdfService = new TicketPdfService;

        $passengerTickets = $ticket->sale->tickets()
            ->where('passenger_id', $ticket->passenger_id)
            ->with(['trip', 'returnTrip', 'origin', 'destination', 'seat'])
            ->get();

        $pdfContent = $ticket->is_round_trip
            ? $pdfService->generateRoundTripTicket($ticket->sale, $passengerTickets)
            : $pdfService->generatePassengerTickets($ticket->sale, collect([$ticket]));

        return $this->pdfResponse($pdfContent, self::ticketFilename($ticket), $disposition);
    }

    // ------------------------------------------------------------------
    // PDF combinado de la venta
    // ------------------------------------------------------------------

    private function saleShow(Sale $sale)
    {
        $sale->load([
            'tickets.passenger',
            'tickets.passenger.children',
            'tickets.trip.bus',
            'tickets.trip.schedule',
            'tickets.trip.route',
            'tickets.returnTrip',
            'tickets.origin',
            'tickets.destination',
            'tickets.seat',
        ]);

        // En pantalla, un bloque por boleto de ida: cada ida ya muestra su
        // vuelta. El PDF (vista previa, descarga y compartir) se arma aparte
        // en salePdf() con todos los boletos de la venta.
        $displayTickets = $sale->tickets
            ->sortBy('id')
            ->reject(fn (Ticket $ticket): bool => $ticket->isReturnLeg())
            ->values();

        return view('boletos.share', [
            'token' => $sale->share_token,
            'ticket' => null,
            'sale' => $sale,
            'displayTickets' => $displayTickets,
            'isSaleLink' => true,
            'heading' => "Boletos de la venta N°{$sale->id}",
            'subheading' => self::routeLabel($displayTickets->first() ?? $sale->tickets->first()),
            'filename' => self::saleFilename($sale),
            'pdfUrl' => $sale->sharePdfUrl('inline'),
            'downloadUrl' => $sale->sharePdfUrl('attachment'),
            'whatsappTargets' => WhatsAppShare::forSale($sale, $sale->shareUrl()),
        ]);
    }

    private function salePdf(Sale $sale, string $disposition)
    {
        $pdfContent = (new TicketPdfService)->generateSaleCombinedTickets($sale);

        return $this->pdfResponse($pdfContent, self::saleFilename($sale), $disposition);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function pdfResponse(string $pdfContent, string $filename, string $disposition)
    {
        $headers = [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ];

        if ($disposition === 'attachment') {
            $headers['Content-Disposition'] = 'attachment; filename="'.rawurlencode($filename).'"';
        } else {
            $headers['Content-Disposition'] = 'inline; filename="'.rawurlencode($filename).'"';
        }

        return Response::make($pdfContent, 200, $headers);
    }

    /**
     * "Origen → Destino" del boleto, para el subtítulo de la página.
     */
    private static function routeLabel(?Ticket $ticket): ?string
    {
        if (! $ticket || ! $ticket->origin || ! $ticket->destination) {
            return null;
        }

        return "{$ticket->origin->name} → {$ticket->destination->name}";
    }

    /**
     * "Boleto N°X — Apellido Nombre", subtítulo de cada bloque en el link
     * de una venta con varios pasajeros.
     */
    public static function ticketBlockLabel(Ticket $ticket): string
    {
        $passengerName = trim(($ticket->passenger?->last_name ?? '').' '.($ticket->passenger?->first_name ?? ''));

        return "Boleto N°{$ticket->id}".($passengerName !== '' ? " — {$passengerName}" : '');
    }

    public static function ticketFilename(Ticket $ticket): string
    {
        $colectivo = str_replace(' ', '_', $ticket->trip?->bus?->name ?? 'Sin fecha');

        return "Boleto_N°{$ticket->id}_{$colectivo}.pdf";
    }

    public static function saleFilename(Sale $sale): string
    {
        $ids = $sale->tickets->modelKeys();

        return 'Boletos_N°'.implode('_', $ids).'.pdf';
    }
}
