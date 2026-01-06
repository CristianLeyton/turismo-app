<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SeatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        for ($busId = 1; $busId <= 2; $busId++) {
            $seatCount = $busId === 1 ? 60 : 50;

            for ($i = 1; $i <= $seatCount; $i++) {
                \App\Models\Seat::create([
                    'bus_id' => $busId,
                    'seat_number' => $i,
                    'is_active' => true,
                ]);
            }
        }
    }
}
