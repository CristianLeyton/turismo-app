<?php

namespace Tests\Feature;

use App\Filament\Clusters\Locations\Resources\RouteStops\Pages\ManageRouteStops;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\User;
use App\Support\Permissions;
use Carbon\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteStopsReorderTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
    }

    private function setupRoute(string $name = 'La Plata - El Charco'): Route
    {
        $bus = Bus::create(['name' => 'Don Esteban', 'seat_count' => 10]);
        $route = Route::create(['name' => $name, 'bus_id' => $bus->id, 'is_active' => true]);

        Schedule::create([
            'route_id' => $route->id,
            'name' => 'Diautivo',
            'departure_time' => Carbon::createFromTime(13, 0),
            'arrival_time' => Carbon::createFromTime(12, 0),
            'is_active' => true,
        ]);

        return $route;
    }

    private function createStop(Route $route, Location $location, int $order, int $departure, ?int $arrival): RouteStop
    {
        return RouteStop::create([
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => $order,
            'departure_offset_minutes' => $departure,
            'arrival_offset_minutes' => $arrival,
        ]);
    }

    #[Test]
    public function el_listado_muestra_por_defecto_solo_la_primera_ruta_activa(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $routeIda = $this->setupRoute('La Plata - Destino A');
        $routeVuelta = $this->setupRoute('Origen B - Destino A');

        $stopIda = $this->createStop(
            $routeIda,
            Location::create(['name' => 'Parada de la primera ruta', 'is_active' => true]),
            1,
            0,
            0,
        );
        $stopVuelta = $this->createStop(
            $routeVuelta,
            Location::create(['name' => 'Parada de la segunda ruta', 'is_active' => true]),
            1,
            0,
            0,
        );

        Livewire::test(ManageRouteStops::class)
            ->assertOk()
            ->assertSee('Parada de la primera ruta')
            ->assertDontSee('Parada de la segunda ruta');
    }

    #[Test]
    public function el_listado_no_auto_pagina_con_ruta_elegida(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $route = $this->setupRoute();

        for ($i = 1; $i <= 50; $i++) {
            $this->createStop(
                $route,
                Location::create(['name' => "Parada {$i}", 'is_active' => true]),
                $i,
                $i,
                $i,
            );
        }

        $component = Livewire::test(ManageRouteStops::class)
            ->assertOk()
            ->assertSee('Parada 1')
            ->assertSee('Parada 50');

        $this->assertTrue(true, 'El listado paginado no debería truncar el recorrido en una sola ruta.');
    }

    #[Test]
    public function arrastrar_una_parada_intercambia_sus_horarios_con_la_de_destino(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $route = $this->setupRoute();

        $stopA = $this->createStop(
            $route,
            Location::create(['name' => 'Cruce Castelar', 'is_active' => true]),
            1,
            270,
            290,
        );
        $stopB = $this->createStop(
            $route,
            Location::create(['name' => 'Moreno', 'is_active' => true]),
            2,
            200,
            220,
        );

        // Arrastramos Moreno (pos 2) a la posición 1, donde estaba Cruce Castelar.
        Livewire::test(ManageRouteStops::class)
            ->assertOk()
            ->call('reorderTable', [$stopB->getKey(), $stopA->getKey()], $stopB->getKey());

        $stopA->refresh();
        $stopB->refresh();

        $this->assertSame(1, $stopB->stop_order);
        $this->assertSame(2, $stopA->stop_order);

        // Moreno (arrastrado) recibe los horarios de la posición 1
        // (los de Cruce Castelar); Cruce Castelar los de Moreno.
        $this->assertSame(270, $stopB->departure_offset_minutes);
        $this->assertSame(290, $stopB->arrival_offset_minutes);
        $this->assertSame(200, $stopA->departure_offset_minutes);
        $this->assertSame(220, $stopA->arrival_offset_minutes);
    }

    #[Test]
    public function reordenar_modo_boton_deja_los_horarios_pegados_a_la_posicion(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $route = $this->setupRoute();

        $stopA = $this->createStop(
            $route,
            Location::create(['name' => 'A', 'is_active' => true]),
            1,
            0,
            10,
        );
        $stopB = $this->createStop(
            $route,
            Location::create(['name' => 'B', 'is_active' => true]),
            2,
            60,
            75,
        );
        $stopC = $this->createStop(
            $route,
            Location::create(['name' => 'C', 'is_active' => true]),
            3,
            120,
            130,
        );

        Livewire::test(ManageRouteStops::class)
            ->assertOk()
            ->call('reorderTable', [$stopC->getKey(), $stopA->getKey(), $stopB->getKey()]);

        $stopA->refresh();
        $stopB->refresh();
        $stopC->refresh();

        $this->assertSame(1, $stopC->stop_order);
        $this->assertSame(2, $stopA->stop_order);
        $this->assertSame(3, $stopB->stop_order);

        // Cada posición conserva el horario que tenía antes.
        $this->assertSame([0, 10], [$stopC->departure_offset_minutes, $stopC->arrival_offset_minutes]);
        $this->assertSame([60, 75], [$stopA->departure_offset_minutes, $stopA->arrival_offset_minutes]);
        $this->assertSame([120, 130], [$stopB->departure_offset_minutes, $stopB->arrival_offset_minutes]);
    }

    #[Test]
    public function no_se_puede_arrastar_sin_ruta_elegida(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $route = $this->setupRoute();

        $stopA = $this->createStop(
            $route,
            Location::create(['name' => 'A', 'is_active' => true]),
            1,
            0,
            10,
        );
        $stopB = $this->createStop(
            $route,
            Location::create(['name' => 'B', 'is_active' => true]),
            2,
            60,
            75,
        );

        // Sin filtro, el drag estará bloqueado (isReorderable = false).
        Livewire::test(ManageRouteStops::class)
            ->set('tableFilters', [])
            ->assertOk()
            ->call('reorderTable', [$stopB->getKey(), $stopA->getKey()], $stopB->getKey());

        $stopA->refresh();
        $stopB->refresh();

        $this->assertSame(1, $stopA->stop_order);
        $this->assertSame(2, $stopB->stop_order);
        $this->assertSame(0, $stopA->departure_offset_minutes);
        $this->assertSame(60, $stopB->departure_offset_minutes);
    }

    #[Test]
    public function el_formulario_guarda_timepicker_como_offset(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $route = $this->setupRoute();
        $location = Location::create(['name' => 'La Plata', 'is_active' => true]);

        // Salida de referencia del horario: 13:00. 17:30 -> 270 min; 18:00 -> 300.
        Livewire::test(ManageRouteStops::class)
            ->assertOk()
            ->callAction('create', data: [
                'route_id' => $route->id,
                'location_id' => $location->id,
                'stop_order' => 2,
                'departure_offset_minutes' => '17:30',
                'arrival_offset_minutes' => '18:00',
            ]);

        $stop = RouteStop::query()->where('route_id', $route->id)->where('location_id', $location->id)->first();

        $this->assertNotNull($stop, 'La parada se debería crear en la base.');

        $this->assertSame(270, $stop->departure_offset_minutes);
        $this->assertSame(300, $stop->arrival_offset_minutes);
    }

    #[Test]
    public function editar_una_parada_muestra_horas_y_guarda_el_nuevo_offset(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $route = $this->setupRoute();
        $location = Location::create(['name' => 'Merlo', 'is_active' => true]);

        // Salida 13:00 + 240 min = 17:00.
        $stop = $this->createStop($route, $location, 3, 240, null);

        Livewire::test(ManageRouteStops::class)
            ->assertOk()
            ->mountTableAction('edit', $stop->getKey())
            ->callTableAction('edit', $stop->getKey(), data: [
                'route_id' => $route->id,
                'location_id' => $location->id,
                'stop_order' => 3,
                'departure_offset_minutes' => '15:00', // == 13:00 + 120 minutos
                'arrival_offset_minutes' => '18:30',   // == 13:00 + 330 minutos
            ]);

        $stop->refresh();

        $this->assertSame(120, $stop->departure_offset_minutes);
        $this->assertSame(330, $stop->arrival_offset_minutes);
    }

    #[Test]
    public function la_tabla_muestra_horas_en_formato_hh_mm(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $route = $this->setupRoute();
        $location = Location::create(['name' => 'Morón', 'is_active' => true]);

        // Salida 13:00 + 200 min = 16:20.
        $this->createStop($route, $location, 1, 200, 220);

        Livewire::test(ManageRouteStops::class)
            ->assertOk()
            ->assertSee('16:20')
            ->assertSee('16:40');
    }
}
