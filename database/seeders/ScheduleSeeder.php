<?php

namespace Database\Seeders;

use App\Models\Route;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    /**
     * Horarios para rutas Orán-Salta / Salta-Orán (Unidad 1) y Embarcación-Salta / Salta-Embarcación (Unidad 2).
     */
    public function run(): void
    {
        $oranSalta = Route::where('name', 'Orán - Salta')->firstOrFail();
        $saltaOran = Route::where('name', 'Salta - Orán')->firstOrFail();
        $embarcacionSalta = Route::where('name', 'Embarcación - Salta')->firstOrFail();
        $saltaEmbarcacion = Route::where('name', 'Salta - Embarcación')->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Unidad 1: Orán - Salta
        |--------------------------------------------------------------------------
        */
        Schedule::create([
            'route_id' => $oranSalta->id,
            'name' => 'Madrugada',
            'departure_time' => Carbon::createFromTime(02, 0, 0),
            'arrival_time' => Carbon::createFromTime(06, 30, 0),
            'is_active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Unidad 1: Salta - Orán
        |--------------------------------------------------------------------------
        */
        Schedule::create([
            'route_id' => $saltaOran->id,
            'name' => 'Linea 1 (Oran)',
            'departure_time' => Carbon::createFromTime(18, 0, 0),
            'arrival_time' => Carbon::createFromTime(23, 00, 0),
            'is_active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Unidad 2: Embarcación - Salta (salida 01:30, llegada 06:30)
        |--------------------------------------------------------------------------
        */
        Schedule::create([
            'route_id' => $embarcacionSalta->id,
            'name' => 'Madrugada',
            'departure_time' => Carbon::createFromTime(1, 30, 0),
            'arrival_time' => Carbon::createFromTime(6, 30, 0),
            'is_active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Unidad 2: Salta - Embarcación (salida 15:00, llegada 20:00)
        |--------------------------------------------------------------------------
        */
        Schedule::create([
            'route_id' => $saltaEmbarcacion->id,
            'name' => 'Linea 2 (Embarcacion)',
            'departure_time' => Carbon::createFromTime(18, 0, 0),
            'arrival_time' => Carbon::createFromTime(22, 30, 0),
            'is_active' => true,
        ]);
    }
}