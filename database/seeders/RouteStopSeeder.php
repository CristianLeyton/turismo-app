<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Route;
use App\Models\Location;
use App\Models\RouteStop;

class RouteStopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
        // Traemos las locations en el orden que ya definiste
        $locations = Location::orderBy('id')->get();

        // Rutas
        $oranSalta = Route::where('name', 'Orán - Salta')->firstOrFail();
        $saltaOran = Route::where('name', 'Salta - Orán')->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Ruta: Orán → Salta
        |--------------------------------------------------------------------------
        */
        foreach ($locations as $index => $location) {
            RouteStop::create([
                'route_id'    => $oranSalta->id,
                'location_id' => $location->id,
                'stop_order'  => $index + 1,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Ruta: Salta → Orán (orden inverso)
        |--------------------------------------------------------------------------
        */
        foreach ($locations->reverse()->values() as $index => $location) {
            RouteStop::create([
                'route_id'    => $saltaOran->id,
                'location_id' => $location->id,
                'stop_order'  => $index + 1,
            ]);
        }
    }
}

