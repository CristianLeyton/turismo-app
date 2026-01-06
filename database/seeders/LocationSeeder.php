<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $locations = [
            ['name' => 'Orán'],
            ['name' => 'Yrigoyen'],
            ['name' => 'Pichanal'],
            ['name' => 'Colonia Santa Rosa'],
            ['name' => 'Urundel'],
            ['name' => 'Salta'],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
}
