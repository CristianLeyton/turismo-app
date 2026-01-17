<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketDownloadController;

Route::get('/', function () {
    return redirect('/admin');
});

// Rutas para descarga de tickets PDF
Route::get('tickets/download', [TicketDownloadController::class, 'download'])->name('tickets.download');
Route::get('tickets/download/multiple', [TicketDownloadController::class, 'downloadMultiple'])->name('tickets.download.multiple');
