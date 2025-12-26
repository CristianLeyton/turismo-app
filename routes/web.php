<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Pages\VentaDePasajes;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::post('/venta-de-pasajes/generar-pdf', [VentaDePasajes::class, 'generarPdf'])->name('venta-de-pasajes.generar-pdf');
});
