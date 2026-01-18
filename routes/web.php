<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketDownloadController;
use App\Models\Trip;
use App\Services\TripPdfService;
use App\Services\TripExcelService;

Route::get('/', function () {
    return redirect('/admin');
});

// Rutas para descarga de tickets PDF
Route::get('tickets/download', [TicketDownloadController::class, 'download'])->name('tickets.download');
Route::get('tickets/download/multiple', [TicketDownloadController::class, 'downloadMultiple'])->name('tickets.download.multiple');

// Ruta para descargar PDF de viajes
Route::get('/trips/{trip}/pdf', function (Trip $trip, TripPdfService $service) {
    return $service->downloadTripPdf($trip);
})->name('trips.pdf.download');

// Ruta para descargar Excel de viajes
Route::get('/trips/{trip}/excel', function (Trip $trip, TripExcelService $service) {
    return $service->downloadTripExcel($trip);
})->name('trips.excel.download');
