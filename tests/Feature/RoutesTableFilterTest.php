<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Route;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoutesTableFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
    }

    public function test_el_listado_de_rutas_muestra_solo_las_activas_por_defecto(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $bus = Bus::create(['name' => 'B1', 'seat_count' => 10]);
        Route::create(['name' => 'Ruta ACTIVA', 'bus_id' => $bus->id, 'is_active' => true]);
        Route::create(['name' => 'Ruta INACTIVA', 'bus_id' => $bus->id, 'is_active' => false]);

        Livewire::test(\App\Filament\Clusters\Locations\Resources\Routes\Pages\ManageRoutes::class)
            ->assertOk()
            ->assertSee('Ruta ACTIVA')
            ->assertDontSee('Ruta INACTIVA');
    }
}
