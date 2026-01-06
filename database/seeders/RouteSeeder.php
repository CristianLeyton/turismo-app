<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Route;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $routes = [
            ['name' => 'Orán - Salta'],
            ['name' => 'Salta - Orán'],
        ];

        foreach ($routes as $route) {
            Route::create($route);
        }
    }
}
