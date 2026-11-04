<?php

use App\Http\Controllers\AdminTicketPdfController;
use App\Http\Controllers\AdminTicketShareLinkController;
use App\Http\Controllers\Api\SeatReservationController;
use App\Http\Controllers\ClientLogoutController;
use App\Http\Controllers\ClientTicketPdfController;
use App\Http\Controllers\TicketDownloadController;
use App\Http\Controllers\TicketShareController;
use App\Models\Trip;
use App\Services\TripExcelService;
use App\Services\TripPdfService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Dirección anterior del ingreso de clientes: puede estar compartida.
Route::redirect('clientes/ingresar', '/clientes/login', 301);

// "Salir" del panel de clientes: cierra sólo el guard `client`.
Route::post('clientes/salir', ClientLogoutController::class)->name('clientes.logout');

// PDF de boletos para el panel público de clientes (valida propiedad por DNI del cliente autenticado)
Route::get('clientes/boletos/{ticket}/pdf', [ClientTicketPdfController::class, 'download'])->name('clientes.boletos.pdf');

// "Ver boleto" del panel de clientes: según DESCARGA_AUTOMATICA_PDF, el link
// compartible del boleto (desactivado) o el PDF (activado / sin configurar).
Route::get('clientes/boletos/{ticket}', [ClientTicketPdfController::class, 'show'])->name('clientes.boletos.show');

// Links compartibles de boletos: quien tenga el link ve el PDF y puede
// compartirlo/descargarlo (sin login; el token es el permiso).
// 't_...' = boleto individual · 's_...' = PDF combinado de la venta.
Route::get('boletos/{token}', [TicketShareController::class, 'show'])->name('boletos.share');
Route::get('boletos/{token}/pdf', [TicketShareController::class, 'pdf'])->name('boletos.share.pdf');

// PDF de un boleto para el personal del panel (vista previa y descarga del
// "Ver boleto"): exige sesión del panel y permiso `tickets.view`, incluye
// boletos eliminados y no genera links públicos.
Route::get('admin/boletos/{ticket}/pdf', AdminTicketPdfController::class)
    ->middleware('auth')
    ->withTrashed()
    ->name('admin.boletos.pdf');

// "Ver link" del panel: crea el token público recién al hacer click.
Route::get('admin/boletos/{ticket}/link', AdminTicketShareLinkController::class)
    ->middleware('auth')
    ->name('admin.boletos.link');

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

// Reservas temporales de asientos del selector: sólo con sesión de un
// vendedor (guard web) o de un cliente (guard client). Operan siempre sobre la
// sesión del pedido; la limpieza de vencidas es sólo por comando.
Route::prefix('api/seat-reservations')->middleware('auth:web,client')->group(function () {
    Route::post('/reserve', [SeatReservationController::class, 'reserve']);
    Route::post('/release', [SeatReservationController::class, 'release']);
    Route::post('/keep-alive', [SeatReservationController::class, 'keepAlive']);
    Route::post('/status', [SeatReservationController::class, 'status']);
});
