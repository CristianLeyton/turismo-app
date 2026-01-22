<?php

use App\Http\Controllers\Api\SeatReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas para el sistema de reservación de asientos
Route::prefix('seat-reservations')->group(function () {
    Route::post('/reserve', [SeatReservationController::class, 'reserve']);
    Route::post('/release', [SeatReservationController::class, 'release']);
    Route::post('/keep-alive', [SeatReservationController::class, 'keepAlive']);
    Route::get('/status', [SeatReservationController::class, 'status']);
    Route::post('/cleanup', [SeatReservationController::class, 'cleanup']);
});
