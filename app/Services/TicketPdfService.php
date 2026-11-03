<?php

namespace App\Services;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class TicketPdfService
{
    /**
     * Hojas del PDF (2 boletos por hoja A4). Cada ida de un diferido va en la
     * misma hoja que su vuelta (misma venta y pasajero), ida primero, en orden
     * de emisión de las idas. Los boletos sin pareja (sólo ida, o un tramo
     * cuyo hermano no está) van después, de a dos por hoja.
     *
     * @param  Collection<int, \App\Models\Ticket>  $tickets
     * @return Collection<int, Collection<int, \App\Models\Ticket>>
     */
    public static function sheets(Collection $tickets): Collection
    {
        $pairs = collect();
        $singles = collect();

        $tickets->sortBy('id')
            ->groupBy(fn ($ticket): string => $ticket->sale_id.'-'.$ticket->passenger_id)
            ->each(function (Collection $passengerTickets) use ($pairs, $singles): void {
                $outbound = $passengerTickets->first(fn ($ticket): bool => ! $ticket->isReturnLeg() && (bool) $ticket->is_round_trip);
                $return = $passengerTickets->first(fn ($ticket): bool => $ticket->isReturnLeg());

                if ($outbound && $return) {
                    $pairs->push(collect([$outbound, $return]));
                    $passengerTickets = $passengerTickets->reject(fn ($ticket): bool => $ticket->is($outbound) || $ticket->is($return));
                }

                $passengerTickets->each(fn ($ticket) => $singles->push($ticket));
            });

        return $pairs
            ->sortBy(fn (Collection $pair): int => $pair->first()->id)
            ->values()
            ->concat($singles->sortBy('id')->values()->chunk(2)->map->values());
    }

    /**
     * Generar PDF para un pasajero con sus boletos de ida y vuelta
     */
    public function generatePassengerTickets(Sale $sale, Collection $passengerTickets): string
    {
        $data = [
            'sale' => $sale,
            'tickets' => $passengerTickets,
            'passenger' => $passengerTickets->first()->passenger,
            'hasChild' => $passengerTickets->contains('travels_with_child', true),
        ];

        // Guardar temporalmente y leer el contenido
        $tempPath = sys_get_temp_dir().'/ticket_'.uniqid().'.pdf';

        Pdf::loadView('tickets.pdf.passenger-tickets', $data)
            ->setPaper('a4', 'portrait')
            ->save($tempPath);

        $content = file_get_contents($tempPath);
        unlink($tempPath); // Eliminar archivo temporal

        return $content;
    }

    /**
     * Generar PDFs para todos los pasajeros de una venta
     */
    public function generateAllPassengerTickets(Sale $sale): array
    {
        $pdfs = [];

        // Agrupar tickets por pasajero
        $ticketsByPassenger = $sale->tickets()->with(['passenger', 'trip.bus', 'trip.schedule', 'trip.route', 'returnTrip', 'origin', 'destination', 'seat'])->get()->groupBy('passenger_id');

        foreach ($ticketsByPassenger as $passengerId => $tickets) {
            $passenger = $tickets->first()->passenger;
            $trip = $tickets->first()->trip;
            $seat = $tickets->first()->seat;

            // Crear nombre de archivo personalizado: Boleto_ID_NombreColectivo.pdf
            $ticketId = $tickets->first()->id;
            $colectivo = str_replace(' ', '_', $trip?->bus?->name ?? 'Sin fecha');

            $filename = "Boleto_N°{$ticketId}_{$colectivo}.pdf";
            $pdfContent = $this->generatePassengerTickets($sale, $tickets);

            $pdfs[$filename] = $pdfContent;
        }

        return $pdfs;
    }

    /**
     * Generar PDF combinado para viaje de ida y vuelta
     */
    public function generateRoundTripTicket(Sale $sale, Collection $passengerTickets): string
    {
        $data = [
            'sale' => $sale,
            'tickets' => $passengerTickets,
            'passenger' => $passengerTickets->first()->passenger,
            'hasChild' => $passengerTickets->contains('travels_with_child', true),
            'isRoundTrip' => true,
        ];

        // Guardar temporalmente y leer el contenido
        $tempPath = sys_get_temp_dir().'/round_trip_ticket_'.uniqid().'.pdf';

        Pdf::loadView('tickets.pdf.passenger-tickets', $data)
            ->setPaper('a4', 'portrait')
            ->save($tempPath);

        $content = file_get_contents($tempPath);
        unlink($tempPath); // Eliminar archivo temporal

        return $content;
    }

    /**
     * Obtener tickets agrupados por pasajero para una venta
     */
    public function getTicketsByPassenger(Sale $sale): Collection
    {
        return $sale->tickets()
            ->with(['passenger', 'trip.bus', 'trip.schedule', 'trip.route', 'returnTrip', 'origin', 'destination'])
            ->get()
            ->groupBy('passenger_id');
    }

    /**
     * PDF combinado de una VENTA completa: todos los boletos juntos con el
     * MISMO maquetado de la vista individual, pares por hoja (2 por página
     * A4). Es lo que abre el link shareable de venta multi-pasajero.
     */
    public function generateSaleCombinedTickets(Sale $sale): string
    {
        $allTickets = $sale->tickets()
            ->with(['passenger', 'trip.bus', 'trip.schedule', 'trip.route', 'returnTrip', 'origin', 'destination', 'seat'])
            ->get();

        $data = [
            'sale' => $sale,
            'tickets' => $allTickets,
            'passenger' => $allTickets->first()?->passenger,
            'hasChild' => $allTickets->contains('travels_with_child', true),
        ];

        $tempPath = sys_get_temp_dir().'/sale_combined_'.uniqid().'.pdf';

        Pdf::loadView('tickets.pdf.passenger-tickets', $data)
            ->setPaper('a4', 'portrait')
            ->save($tempPath);

        $content = file_get_contents($tempPath);
        unlink($tempPath);

        return $content;
    }
}
