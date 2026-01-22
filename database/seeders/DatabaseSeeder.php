<?php

namespace Database\Seeders;

use App\Models\Route;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        User::create([
            'id' => 1,
            'name' => 'Super Administrador',
            'password' => bcrypt('superadmin'),
            'username' => 'superadmin',
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'id' => 2,
            'name' => 'Casa Central',
            'email' => 'admin@mail.com',
            'password' => bcrypt('admin'),
            'username' => 'CasaCentral',
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'id' => 3,
            'name' => 'Vendedor',
            'email' => 'user@mail.com',
            'password' => bcrypt('vendedor'),
            'username' => 'vendedor',
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);
    
    $this->call([
        BusSeeder::class,
        SeatSeeder::class,
        LocationSeeder::class,
        RouteSeeder::class,
        RouteStopSeeder::class,
        ScheduleSeeder::class,
        SeatLayoutSeeder::class,
    ]);
    }
}
