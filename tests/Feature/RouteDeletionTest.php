<?php

namespace Tests\Feature;

use App\Filament\Clusters\Locations\Resources\Routes\Pages\EditRoute;
use App\Filament\Clusters\Locations\Resources\Routes\Pages\ManageRoutes;
use App\Filament\Clusters\Locations\Resources\Routes\RouteResource;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RouteDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Bus $bus;

    private Route $route;

    private Schedule $schedule;

    private int $ticketsCreated = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->bus = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 40, 'floors' => 1]);
        $this->route = Route::create(['name' => 'Oran - Salta', 'bus_id' => $this->bus->id, 'is_active' => true]);
        $this->schedule = Schedule::create(['route_id' => $this->route->id, 'departure_time' => '08:00', 'is_active' => true]);
    }

    private function sellTicket(string $date, ?Route $route = null): Ticket
    {
        $route ??= $this->route;
        $schedule = $route->is($this->route)
            ? $this->schedule
            : Schedule::create(['route_id' => $route->id, 'departure_time' => '08:00', 'is_active' => true]);

        $this->ticketsCreated++;

        return Ticket::create([
            'sale_id' => Sale::create(['user_id' => auth()->id(), 'sale_date' => now(), 'total_amount' => 100])->id,
            'trip_id' => Trip::create([
                'route_id' => $route->id,
                'schedule_id' => $schedule->id,
                'bus_id' => $this->bus->id,
                'trip_date' => $date,
            ])->id,
            'seat_id' => Seat::create([
                'bus_id' => $this->bus->id,
                'seat_number' => (string) $this->ticketsCreated,
                'is_active' => true,
                'floor' => '1',
            ])->id,
            'passenger_id' => Passenger::create([
                'first_name' => 'Pasajero',
                'last_name' => (string) $this->ticketsCreated,
                'dni' => (string) (30000000 + $this->ticketsCreated),
                'passenger_type' => 'adult',
            ])->id,
            'is_round_trip' => false,
            'origin_location_id' => Location::firstOrCreate(['name' => 'Orán'], ['is_active' => true])->id,
            'destination_location_id' => Location::firstOrCreate(['name' => 'Salta'], ['is_active' => true])->id,
            'price' => 100,
        ]);
    }

    public function test_una_ruta_con_viajes_pendientes_avisa_y_no_se_puede_eliminar(): void
    {
        $this->sellTicket(today()->addDay()->toDateString());
        $this->sellTicket(today()->addDays(2)->toDateString());

        Livewire::test(ManageRoutes::class)
            ->mountAction(TestAction::make(DeleteAction::class)->table($this->route))
            ->assertMountedActionModalSee('No se puede eliminar la ruta')
            ->assertMountedActionModalSee('Esta ruta tiene 2 boletos vendidos en viajes que todavía no partieron, por eso no se puede eliminar.')
            ->callMountedAction();

        $this->assertNotSoftDeleted($this->route);
    }

    public function test_desde_el_aviso_se_puede_desactivar_la_ruta(): void
    {
        $this->sellTicket(today()->addDay()->toDateString());

        Livewire::test(ManageRoutes::class)
            ->callAction([
                TestAction::make(DeleteAction::class)->table($this->route),
                TestAction::make('deactivateRoute'),
            ])
            ->assertNotified('Ruta desactivada');

        $this->route->refresh();
        $this->assertFalse($this->route->is_active);
        $this->assertNotSoftDeleted($this->route);
    }

    public function test_una_ruta_sin_viajes_pendientes_se_elimina(): void
    {
        $this->sellTicket(today()->subDays(3)->toDateString());

        Livewire::test(ManageRoutes::class)
            ->mountAction(TestAction::make(DeleteAction::class)->table($this->route))
            ->assertMountedActionModalDontSee('No se puede eliminar la ruta')
            ->callMountedAction();

        $this->assertSoftDeleted($this->route);
    }

    public function test_un_viaje_de_hoy_que_ya_partio_no_bloquea_la_eliminacion(): void
    {
        $this->travelTo(today()->setTime(10, 0));
        $this->sellTicket(today()->toDateString());

        $this->assertSame(0, RouteResource::pendingTicketsCount($this->route));

        Livewire::test(ManageRoutes::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($this->route));

        $this->assertSoftDeleted($this->route);
    }

    public function test_un_viaje_vacio_no_bloquea_la_eliminacion(): void
    {
        Trip::create([
            'route_id' => $this->route->id,
            'schedule_id' => $this->schedule->id,
            'bus_id' => $this->bus->id,
            'trip_date' => today()->addDay()->toDateString(),
        ]);

        Livewire::test(ManageRoutes::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($this->route));

        $this->assertSoftDeleted($this->route);
    }

    public function test_tampoco_se_elimina_desde_la_pantalla_de_la_ruta(): void
    {
        $this->sellTicket(today()->addDay()->toDateString());

        Livewire::test(EditRoute::class, ['record' => $this->route->getRouteKey()])
            ->mountAction(DeleteAction::class)
            ->assertMountedActionModalSee('No se puede eliminar la ruta')
            ->callMountedAction();

        $this->assertNotSoftDeleted($this->route);
    }

    public function test_eliminar_en_lote_se_corta_si_alguna_ruta_tiene_viajes_pendientes(): void
    {
        $otherRoute = Route::create(['name' => 'Salta - Oran', 'bus_id' => $this->bus->id, 'is_active' => true]);
        $this->sellTicket(today()->addDay()->toDateString());

        Livewire::test(ManageRoutes::class)
            ->selectTableRecords([$this->route->getKey(), $otherRoute->getKey()])
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
            ->assertNotified('No se eliminó ninguna ruta');

        $this->assertNotSoftDeleted($this->route);
        $this->assertNotSoftDeleted($otherRoute);
    }

    public function test_el_aviso_usa_singular_con_un_solo_boleto(): void
    {
        $this->assertSame(
            'Esta ruta tiene 1 boleto vendido en un viaje que todavía no partió, por eso no se puede eliminar. Podés desactivarla para que no se use en nuevas ventas.',
            RouteResource::cannotDeleteMessage(1),
        );
    }
}
