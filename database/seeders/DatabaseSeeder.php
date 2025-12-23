<?php

namespace Database\Seeders;

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

        User::factory()->create([
            'id' => 1,
            'name' => 'Administrador',
            'email' => 'admin@mail.com',
            'password' => bcrypt('admin'), 
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'id' => 2,
            'name' => 'Usuario',
            'email' => 'user@mail.com',
            'password' => bcrypt('user'), 
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);
    }
}
