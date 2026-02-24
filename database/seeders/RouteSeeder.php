<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\Route;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    /**
     * Rutas de Unidad 1 (Orán - Salta) y Unidad 2 (Embarcación - Salta).
     */
    public function run(): void
    {
        $unidad1 = Bus::where('name', 'Linea 1 (Oran)')->firstOrFail();
        $unidad2 = Bus::where('name', 'Linea 2 (Embarcacion)')->firstOrFail();

        // Unidad 1: rutas existentes
        Route::create([
            'bus_id' => $unidad1->id,
            'name' => 'Orán - Salta',
        ]);
        Route::create([
            'bus_id' => $unidad1->id,
            'name' => 'Salta - Orán',
        ]);

        // Unidad 2: rutas Embarcación - Salta
        Route::create([
            'bus_id' => $unidad2->id,
            'name' => 'Embarcación - Salta',
        ]);
        Route::create([
            'bus_id' => $unidad2->id,
            'name' => 'Salta - Embarcación',
        ]);
    }
}
