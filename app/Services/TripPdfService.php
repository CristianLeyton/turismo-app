<?php

namespace App\Services;

use App\Models\Trip;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class TripPdfService
{
    /**
     * Generar PDF de detalles del viaje
     */
    public function generateTripDetailsPdf(Trip $trip)
    {
        // Obtener pasajeros del viaje
        $passengers = $trip->getPassengersWithDetails();
        
        // Obtener información de paradas
        $stops = $trip->route->stops()->with('location')->get();
        $firstStop = $stops->first();
        $lastStop = $stops->last();
        
        // Renderizar vista para PDF con datos simples
        $pdf = Pdf::loadView('pdf.trip-details', [
            'trip' => $trip,
            'passengers' => $passengers,
            'firstStop' => $firstStop,
            'lastStop' => $lastStop,
            'stops' => $stops,
            'stopsCount' => $stops->count(),
            'passengersCount' => $passengers->count()
        ]);
        
        // Configurar PDF similar al de passenger-tickets que funciona
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'defaultFont' => 'Arial',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'isPhpEnabled' => true,
            'isJavascriptEnabled' => true,
            'chroot' => realpath(public_path()),
            'tempDir' => sys_get_temp_dir(),
            'fontDir' => public_path('fonts'),
            'unicode' => true,
            'encoding' => 'UTF-8',
        ]);
        
        return $pdf;
    }
    
    /**
     * Descargar PDF del viaje
     */
    public function downloadTripPdf(Trip $trip)
    {
        $pdf = $this->generateTripDetailsPdf($trip);
        
        // Generar nombre del archivo
        $busName = $trip->bus->name;
        $date = $trip->trip_date->format('d-m-Y');
        $filename = 'viaje_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $busName) . '_' . $date . '.pdf';
        
        return $pdf->download($filename);
    }
    
    /**
     * Generar PDF como stream para vista previa
     */
    public function streamTripPdf(Trip $trip)
    {
        $pdf = $this->generateTripDetailsPdf($trip);
        
        // Generar nombre del archivo
        $busName = $trip->bus->name;
        $date = $trip->trip_date->format('d-m-Y');
        $filename = 'viaje_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $busName) . '_' . $date . '.pdf';
        
        return $pdf->stream($filename);
    }
}
