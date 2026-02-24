<?php

namespace Database\Seeders;

use App\Models\Seat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SeatSeeder extends Seeder
{
    /**
     * Unidad 1 (60): arriba (piso 2) 1-48, abajo (piso 1) 49-60.
     * Unidad 2 (55): abajo (piso 1) 1-9, arriba (piso 2) 10-55.
     */
    public function run(): void
    {
        $buses = \App\Models\Bus::orderBy('id')->get();
        if ($buses->isEmpty()) {
            return;
        }

        foreach ($buses as $bus) {
            $seatCount = (int) $bus->seat_count;
            $floors = (int) $bus->floors;

            for ($i = 1; $i <= $seatCount; $i++) {
                $floor = '1';
                if ($seatCount === 60 && $floors >= 2) {
                    // Unidad 1 (60): arriba 1-48, abajo 49-60
                    $floor = $i <= 48 ? '2' : '1';
                } elseif ($floors >= 2 && $i > 9) {
                    // Unidad 2 (55): abajo 1-9, arriba 10-55
                    $floor = '2';
                }
                Seat::create([
                    'bus_id'      => $bus->id,
                    'seat_number' => $i,
                    'is_active'   => true,
                    'floor'       => $floor,
                ]);
            }
        }
    }
}
