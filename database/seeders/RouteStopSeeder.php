<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RouteStopSeeder extends Seeder
{
    /**
     * Paradas para Orán-Salta, Salta-Orán (Unidad 1) y Embarcación-Salta, Salta-Embarcación (Unidad 2).
     */
    public function run(): void
    {
        $oranSalta = Route::where('name', 'Orán - Salta')->firstOrFail();
        $saltaOran = Route::where('name', 'Salta - Orán')->firstOrFail();
        $embarcacionSalta = Route::where('name', 'Embarcación - Salta')->firstOrFail();
        $saltaEmbarcacion = Route::where('name', 'Salta - Embarcación')->firstOrFail();

        // Orden para Orán → Salta (sin Embarcación): Orán, Yrigoyen, Pichanal, ..., Salta
        $locationsOranSalta = Location::where('name', '!=', 'Embarcación')->orderBy('id')->get();

        /*
        |--------------------------------------------------------------------------
        | Ruta: Orán → Salta (Unidad 1)
        |--------------------------------------------------------------------------
        */
        foreach ($locationsOranSalta as $index => $location) {
            RouteStop::create([
                'route_id'    => $oranSalta->id,
                'location_id' => $location->id,
                'stop_order'  => $index + 1,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Ruta: Salta → Orán (Unidad 1, orden inverso)
        |--------------------------------------------------------------------------
        */
        foreach ($locationsOranSalta->reverse()->values() as $index => $location) {
            RouteStop::create([
                'route_id'    => $saltaOran->id,
                'location_id' => $location->id,
                'stop_order'  => $index + 1,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Ruta: Embarcación → Salta (Unidad 2)
        | Ida: Embarcación, Pichanal, Colonia Santa Rosa, Urundel, Gral. Güemes, Salta
        |--------------------------------------------------------------------------
        */
        $nombresIdaEmbarcacion = [
            'Embarcación',
            'Orán',
            'Yrigoyen',
            'Pichanal',
            'Colonia Santa Rosa',
            'Urundel',
            'Gral. Güemes',
            'Salta',
        ];
        $paradasIdaEmbarcacion = collect($nombresIdaEmbarcacion)->map(
            fn (string $name) => Location::where('name', $name)->firstOrFail()
        );

        foreach ($paradasIdaEmbarcacion->values() as $index => $location) {
            RouteStop::create([
                'route_id'    => $embarcacionSalta->id,
                'location_id' => $location->id,
                'stop_order'  => $index + 1,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Ruta: Salta → Embarcación (Unidad 2)
        | Vuelta: Salta, Gral. Güemes, Urundel, Colonia Santa Rosa, Pichanal, Yrigoyen, Orán, Embarcación
        |--------------------------------------------------------------------------
        */
        $nombresVueltaEmbarcacion = [
            'Salta',
            'Gral. Güemes',
            'Urundel',
            'Colonia Santa Rosa',
            'Pichanal',
            'Yrigoyen',
            'Orán',
            'Embarcación',
        ];
        $paradasVueltaEmbarcacion = collect($nombresVueltaEmbarcacion)->map(
            fn (string $name) => Location::where('name', $name)->firstOrFail()
        );

        foreach ($paradasVueltaEmbarcacion->values() as $index => $location) {
            RouteStop::create([
                'route_id'    => $saltaEmbarcacion->id,
                'location_id' => $location->id,
                'stop_order'  => $index + 1,
            ]);
        }
    }
}

