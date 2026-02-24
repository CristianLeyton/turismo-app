<?php

namespace Database\Seeders;

use App\Models\Bus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusSeeder extends Seeder
{
    /**
     * Unidad 1: 60 asientos (nuevo layout se definirá después).
     * Unidad 2: 55 asientos, misma disposición que el colectivo actual de 55.
     */
    public function run(): void
    {
        Bus::create([
            'name' => 'Linea 1 (Oran)',
            'plate' => 'ABC123',
            'seat_count' => 60,
            'floors' => 2,
        ]);

        Bus::create([
            'name' => 'Linea 2 (Embarcacion)',
            'plate' => 'DEF456',
            'seat_count' => 55,
            'floors' => 2,
        ]);
    }
}
