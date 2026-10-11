<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\ClientsResource;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Models\Bus;
use App\Models\Clients;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tabla de clientes: filtro de estado (baneado / habilitado) y columna de
 * boletos históricos comprados por el cliente (unidos por DNI).
 */
class ClientsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_filtro_muestra_solo_los_baneados(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        Clients::create(['dni' => '11111111', 'nombre' => 'Ana', 'apellido' => 'García', 'can_buy' => true]);
        Clients::create(['dni' => '22222222', 'nombre' => 'Beto', 'apellido' => 'López', 'can_buy' => false, 'comments' => 'No paga']);

        $page = Livewire::test(ManageClients::class);

        // Todos: 2 registros.
        $page->assertCountTableRecords(2);

        // Baneados: sólo el de can_buy = false.
        $page->filterTable('can_buy', true)
            ->assertCountTableRecords(1)
            ->assertSeeHtml('Beto')
            ->assertDontSeeHtml('Ana');

        // Habilitados: sólo el de can_buy = true.
        $page->filterTable('can_buy', false)
            ->assertCountTableRecords(1)
            ->assertSeeHtml('Ana')
            ->assertDontSeeHtml('Beto');
    }

    public function test_la_columna_cuenta_los_boletos_de_todos_los_pasajeros_con_ese_dni(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $cliente = Clients::create(['dni' => '33333333', 'nombre' => 'Carla', 'apellido' => 'Díaz', 'can_buy' => true]);
        $f = $this->makeSaleFixture();

        // Dos compras históricas del mismo DNI (pasajeros duplicados en ventas distintas).
        $p1 = Passenger::create(['first_name' => 'Carla', 'last_name' => 'Díaz', 'dni' => '33333333', 'passenger_type' => 'adult']);
        $p2 = Passenger::create(['first_name' => 'Carla', 'last_name' => 'Díaz', 'dni' => '33333333', 'passenger_type' => 'adult']);

        Ticket::create(['sale_id' => $f['sale']->id, 'trip_id' => $f['trip']->id, 'passenger_id' => $p1->id, 'seat_id' => $f['seat']->id, 'origin_location_id' => $f['origin']->id, 'destination_location_id' => $f['destination']->id, 'price' => 100]);
        Ticket::create(['sale_id' => $f['sale']->id, 'trip_id' => $f['trip']->id, 'passenger_id' => $p2->id, 'seat_id' => $f['seat2']->id, 'origin_location_id' => $f['origin']->id, 'destination_location_id' => $f['destination']->id, 'price' => 100]);

        // Cliente sin compras.
        $sinCompras = Clients::create(['dni' => '44444444', 'nombre' => 'Eva', 'apellido' => 'Ruiz', 'can_buy' => true]);

        Livewire::test(ManageClients::class)
            ->assertTableColumnStateSet('boletos_historicos', 2, $cliente)
            ->assertTableColumnStateSet('boletos_historicos', 0, $sinCompras);

        // La relación del modelo es la fuente del contador.
        $this->assertSame(2, $cliente->tickets()->count());
    }

    /**
     * Fixture mínimo de una venta: ruta con horario y un viaje con asientos.
     */
    private function makeSaleFixture(): array
    {
        $oran = Location::create(['name' => 'Orán T', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta T', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea T', 'plate' => 'TTT222', 'seat_count' => 4, 'floors' => 1]);
        $seat = Seat::create(['bus_id' => $bus->id, 'seat_number' => '1', 'is_active' => true, 'floor' => '1']);
        $seat2 = Seat::create(['bus_id' => $bus->id, 'seat_number' => '2', 'is_active' => true, 'floor' => '1']);

        $route = Route::create(['name' => 'Orán - Salta T', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana T',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::findOrCreateForBooking($schedule->id, '2026-10-03', $oran->id, $salta->id)['trip'];
        $sale = Sale::createNew(User::factory()->create()->id);

        return [
            'origin' => $oran,
            'destination' => $salta,
            'bus' => $bus,
            'schedule' => $schedule,
            'trip' => $trip,
            'seat' => $seat,
            'seat2' => $seat2,
            'sale' => $sale,
        ];
    }
}
