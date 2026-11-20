<?php

use App\Http\Controllers\AdminTicketPdfController;
use App\Http\Controllers\Api\SeatReservationController;
use App\Http\Controllers\ClientLogoutController;
use App\Http\Controllers\ClientTicketPdfController;
use App\Http\Controllers\TicketDownloadController;
use App\Http\Controllers\TicketShareController;
use App\Http\Middleware\EnsureClientPanelIsEnabled;
use App\Models\Trip;
use App\Services\TripExcelService;
use App\Services\TripPdfService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Rutas sueltas del panel de clientes: como el panel, 404 mientras el
// parámetro "Panel de clientes activo" esté apagado.
Route::middleware(EnsureClientPanelIsEnabled::class)->group(function () {
    // Dirección anterior del ingreso de clientes: puede estar compartida.
    Route::redirect('clientes/ingresar', '/clientes/login', 301);

    // "Salir" del panel de clientes: cierra sólo el guard `client`.
    Route::post('clientes/salir', ClientLogoutController::class)->name('clientes.logout');

    // PDF de boletos para el panel público de clientes (valida propiedad por DNI del cliente autenticado)
    Route::get('clientes/boletos/{ticket}/pdf', [ClientTicketPdfController::class, 'download'])->name('clientes.boletos.pdf');

    // "Ver boleto" del panel de clientes: según DESCARGA_AUTOMATICA_PDF, el link
    // compartible del boleto (desactivado) o el PDF (activado / sin configurar).
    Route::get('clientes/boletos/{ticket}', [ClientTicketPdfController::class, 'show'])->name('clientes.boletos.show');
});

// Links compartibles de boletos: quien tenga el link recibe el PDF y puede
// descargarlo (sin login; el token es el permiso).
// 't_...' = boleto individual. Los tokens de venta ('s_...') dan 404.
Route::get('boletos/{token}', [TicketShareController::class, 'show'])->name('boletos.share');
Route::get('boletos/{token}/pdf', [TicketShareController::class, 'pdf'])->name('boletos.share.pdf');

// PDF de un boleto para el personal del panel (vista previa y descarga del
// "Ver boleto"): exige sesión del panel y permiso `tickets.view`, incluye
// boletos eliminados y no genera links públicos.
Route::get('admin/boletos/{ticket}/pdf', AdminTicketPdfController::class)
    ->middleware('auth')
    ->withTrashed()
    ->name('admin.boletos.pdf');

// Rutas para descarga de tickets PDF
Route::get('tickets/download', [TicketDownloadController::class, 'download'])->name('tickets.download');
Route::get('tickets/download/multiple', [TicketDownloadController::class, 'downloadMultiple'])->name('tickets.download.multiple');

// PDF / Excel con los pasajeros de un viaje. El panel ya no las usa (exporta
// desde las acciones de TripResource), pero se mantienen por enlaces viejos
// guardados: exigen sesión del panel y permiso `trips.view`.
Route::get('/trips/{trip}/pdf', function (Trip $trip, TripPdfService $service) {
    return $service->downloadTripPdf($trip);
})->middleware(['auth', 'can:view,trip'])->name('trips.pdf.download');

Route::get('/trips/{trip}/excel', function (Trip $trip, TripExcelService $service) {
    return $service->downloadTripExcel($trip);
})->middleware(['auth', 'can:view,trip'])->name('trips.excel.download');

// Reservas temporales de asientos del selector: sólo con sesión de un
// vendedor (guard web) o de un cliente (guard client). Operan siempre sobre la
// sesión del pedido; la limpieza de vencidas es sólo por comando.
Route::prefix('api/seat-reservations')->middleware('auth:web,client')->group(function () {
    Route::post('/reserve', [SeatReservationController::class, 'reserve']);
    Route::post('/release', [SeatReservationController::class, 'release']);
    Route::post('/keep-alive', [SeatReservationController::class, 'keepAlive']);
    Route::post('/status', [SeatReservationController::class, 'status']);
});
