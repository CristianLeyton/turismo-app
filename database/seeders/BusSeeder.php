<?php

namespace Database\Seeders;

use App\Models\Bus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusSeeder extends Seeder
{
    /**
     * Línea 1 (Orán): 55 asientos, layout colectivo 55 plazas.
     * Línea 2 (Embarcación): 60 asientos, layout colectivo 60 plazas.
     */
    public function run(): void
    {
        Bus::create([
            'name' => 'Linea 1 (Oran)',
            'plate' => 'ABC123',
            'seat_count' => 55,
            'floors' => 2,
        ]);

        Bus::create([
            'name' => 'Linea 2 (Embarcacion)',
            'plate' => 'DEF456',
            'seat_count' => 60,
            'floors' => 2,
        ]);
    }
}
