<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;

class TicketPdfService
{
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
        $tempPath = sys_get_temp_dir() . '/ticket_' . uniqid() . '.pdf';
        
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
        $ticketsByPassenger = $sale->tickets()->with(['passenger', 'trip', 'returnTrip', 'origin', 'destination', 'seat'])->get()->groupBy('passenger_id');
        
        foreach ($ticketsByPassenger as $passengerId => $tickets) {
            $passenger = $tickets->first()->passenger;
            $trip = $tickets->first()->trip;
            $seat = $tickets->first()->seat;
            
            // Crear nombre de archivo personalizado: NombreCompleto_IDViaje_Asiento.pdf
            $passengerName = str_replace(' ', '_', $passenger->full_name);
            $tripId = $trip->id;
            $seatNumber = $seat ? $seat->seat_number : 'SinAsiento';
            
            $filename = "{$passengerName}_Viaje{$tripId}_Asiento{$seatNumber}.pdf";
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
        $tempPath = sys_get_temp_dir() . '/round_trip_ticket_' . uniqid() . '.pdf';
        
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
            ->with(['passenger', 'trip', 'returnTrip', 'origin', 'destination'])
            ->get()
            ->groupBy('passenger_id');
    }
}
