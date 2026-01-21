<?php

namespace App\Services;

use App\Models\Trip;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TripExcelExport implements FromView, WithStyles, WithTitle
{
    protected $trip;
    protected $passengers;
    protected $firstStop;
    protected $lastStop;

    public function __construct(Trip $trip, $passengers, $firstStop, $lastStop)
    {
        $this->trip = $trip;
        $this->passengers = $passengers;
        $this->firstStop = $firstStop;
        $this->lastStop = $lastStop;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        return view('excel.trip-details', [
            'trip' => $this->trip,
            'passengers' => $this->passengers,
            'firstStop' => $this->firstStop,
            'lastStop' => $this->lastStop,
            'passengersCount' => $this->passengers->count()
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo simple para encabezados
            1 => [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F0F0F0']
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Detalles del Viaje';
    }
}

class TripExcelService
{
    /**
     * Generar Excel de detalles del viaje
     */
    public function generateTripDetailsExcel(Trip $trip)
    {
        // Obtener pasajeros del viaje
        $passengers = $trip->getPassengersWithDetails();
        
        // Obtener información de paradas
        $stops = $trip->route->stops()->with('location')->get();
        $firstStop = $stops->first();
        $lastStop = $stops->last();
        
        return new TripExcelExport($trip, $passengers, $firstStop, $lastStop);
    }
    
    /**
     * Descargar Excel del viaje
     */
    public function downloadTripExcel(Trip $trip)
    {
        $export = $this->generateTripDetailsExcel($trip);
        
        // Generar nombre del archivo
        $tripId = $trip->id;
        $colectivo = str_replace(' ', '_', $trip->bus->name);
        $date = $trip->trip_date->format('d-m-Y');
        $filename = "Viaje_N°{$tripId}_{$colectivo}_{$date}.xlsx";
        
        return Excel::download($export, $filename);
    }
}
