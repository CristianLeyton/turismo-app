<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Route;
use App\Models\Schedule;
use Carbon\Carbon;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buscar las rutas
        $oranSalta = Route::where('name', 'Orán - Salta')->firstOrFail();
        $saltaOran = Route::where('name', 'Salta - Orán')->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Horarios para: Orán - Salta
        |--------------------------------------------------------------------------
        */
        Schedule::create([
            'route_id' => $oranSalta->id,
            'name' => 'Tarde',
            'departure_time' => Carbon::createFromTime(14, 0, 0), // 14:00
            'arrival_time' => Carbon::createFromTime(19, 0, 0),   // 19:00
            'is_active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Horarios para: Salta - Orán
        |--------------------------------------------------------------------------
        */
        Schedule::create([
            'route_id' => $saltaOran->id,
            'name' => 'Noche',
            'departure_time' => Carbon::createFromTime(19, 0, 0), // 19:00
            'arrival_time' => Carbon::createFromTime(0, 0, 0),    // 00:00 (medianoche)
            'is_active' => true,
        ]);
    }
}