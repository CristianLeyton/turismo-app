<?php

namespace Database\Seeders;

use App\Models\Bus;
use GuzzleHttp\Promise\Create;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        Bus::create([
            'name' => 'Expresso Sofia Turismo',
            'plate' => 'ABC123',
            'seat_count' => 60,
        ]);

        Bus::create([
            'name' => 'Colectivo La Estrella',
            'plate' => 'DEF456',
            'seat_count' => 50,
        ]);
    }
}
