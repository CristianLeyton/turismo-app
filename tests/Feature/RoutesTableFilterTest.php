<?php

namespace Tests\Feature;

use App\Filament\Clusters\Locations\Resources\Locations\Pages\ManageLocations;
use App\Filament\Clusters\Locations\Resources\Routes\Pages\ManageRoutes;
use App\Filament\Clusters\Locations\Resources\RouteStops\Pages\ManageRouteStops;
use App\Filament\Clusters\Locations\Resources\Schedules\Pages\ManageSchedules;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    private function routeWith(string $name, array $stopNames, int $activeSchedules, bool $isActive = true): Route
    {
        $bus = Bus::firstOrCreate(['name' => 'B1'], ['seat_count' => 10]);
        $route = Route::create(['name' => $name, 'bus_id' => $bus->id, 'is_active' => $isActive]);

        foreach (array_values($stopNames) as $index => $stopName) {
            RouteStop::create([
                'route_id' => $route->id,
                'location_id' => Location::firstOrCreate(['name' => $stopName], ['is_active' => true])->id,
                'stop_order' => $index + 1,
                'departure_offset_minutes' => $index * 30,
            ]);
        }

        for ($i = 0; $i < $activeSchedules; $i++) {
            Schedule::create(['route_id' => $route->id, 'departure_time' => sprintf('%02d:00', 8 + $i), 'is_active' => true]);
        }

        Schedule::create(['route_id' => $route->id, 'departure_time' => '23:00', 'is_active' => false]);

        return $route;
    }

    public function test_el_listado_muestra_recorrido_paradas_y_horarios_de_cada_ruta(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $route = $this->routeWith('Oran - Salta', ['Orán', 'Pichanal', 'Urundel', 'Güemes', 'Salta'], activeSchedules: 2);

        Livewire::test(ManageRoutes::class)
            ->assertTableColumnStateSet('summary', 'Orán → Salta', $route->getKey())
            ->assertTableColumnStateSet('stops_count', 5, $route->getKey())
            ->assertTableColumnStateSet('active_schedules_count', 2, $route->getKey())
            ->assertTableColumnStateSet('missing', null, $route->getKey());
    }

    public function test_una_ruta_activa_incompleta_se_marca_con_lo_que_le_falta(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $sinHorarios = $this->routeWith('Sin horarios', ['Orán', 'Salta'], activeSchedules: 0);
        $vacia = $this->routeWith('Vacía', [], activeSchedules: 0);
        $inactiva = $this->routeWith('Inactiva', [], activeSchedules: 0, isActive: false);

        Livewire::test(ManageRoutes::class)
            ->filterTable('is_active', null)
            ->assertTableColumnStateSet('missing', ['Sin horarios'], $sinHorarios->getKey())
            ->assertTableColumnStateSet('missing', ['Sin paradas', 'Sin horarios'], $vacia->getKey())
            ->assertTableColumnStateSet('missing', null, $inactiva->getKey());
    }

    public function test_el_listado_no_hace_una_consulta_por_ruta(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $this->routeWith('Ruta 1', ['Orán', 'Salta'], activeSchedules: 1);

        DB::enableQueryLog();
        Livewire::test(ManageRoutes::class)->assertOk();
        $queriesWithOneRoute = count(DB::getQueryLog());

        for ($i = 2; $i <= 6; $i++) {
            $this->routeWith("Ruta {$i}", ['Orán', 'Pichanal', 'Salta'], activeSchedules: 2);
        }

        DB::flushQueryLog();
        Livewire::test(ManageRoutes::class)->assertOk();
        $queriesWithSixRoutes = count(DB::getQueryLog());

        $this->assertLessThanOrEqual($queriesWithOneRoute + 2, $queriesWithSixRoutes);
    }

    public function test_el_listado_de_rutas_no_ofrece_columnas_tecnicas(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        foreach ([ManageRoutes::class, ManageSchedules::class, ManageRouteStops::class, ManageLocations::class] as $page) {
            Livewire::test($page)
                ->assertTableColumnDoesNotExist('created_at')
                ->assertTableColumnDoesNotExist('updated_at')
                ->assertTableColumnDoesNotExist('deleted_at');
        }
    }
}
