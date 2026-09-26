<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\RescheduleTicket;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Bus;
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
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class RescheduleTicketPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-page@test.local',
            'username' => 'admin-page',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
    }

    private function createOneWayTicket(string $date = '2026-10-01'): Ticket
    {
        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create([
            'name' => 'Linea P',
            'plate' => 'PPP111',
            'seat_count' => 4,
            'floors' => 1,
        ]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create([
                'bus_id' => $bus->id,
                'seat_number' => (string) $n,
                'is_active' => true,
                'floor' => '1',
            ]);
        }

        $route = Route::create(['name' => 'Orán - Salta P', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana P',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => $date,
        ]);

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'sale_date' => now(),
            'total_amount' => 100,
        ]);

        $passenger = Passenger::create([
            'first_name' => 'Juana',
            'last_name' => 'Rendida',
            'dni' => '45678912',
            'passenger_type' => 'adult',
        ]);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first()->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
        ]);
    }

    public function test_la_pagina_renderiza_para_admin(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket();

        Livewire::test(RescheduleTicket::class, ['record' => $ticket->id])
            ->assertOk()
            ->assertSee('Reprogramar boleto')
            ->assertSee('Nueva fecha de ida')
            ->assertSee('Juana');
    }

    public function test_la_pagina_renderiza_por_http_para_admin(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket();

        $response = $this->get(TicketResource::getUrl('reschedule', ['record' => $ticket->id]));

        $response->assertOk();
        $response->assertSee('Reprogramar boleto');
        $response->assertSee('Juana');
    }

    public function test_no_admin_recibe_403(): void
    {
        $vendedor = User::create([
            'name' => 'Vendedor',
            'email' => 'vendedor-page@test.local',
            'username' => 'vendedor-page',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $this->actingAs($vendedor);

        $ticket = $this->createOneWayTicket();

        $response = $this->get(TicketResource::getUrl('reschedule', ['record' => $ticket->id]));

        $response->assertForbidden();
    }

    public function test_elegir_fecha_y_horario_resuelve_el_viaje_y_autoselecciona_asiento(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-01');

        $component = Livewire::test(RescheduleTicket::class, ['record' => $ticket->id]);

        $data = $component->instance()->data;

        // Elegir nueva fecha y horario (el mismo schedule: crea viaje el 2026-10-05)
        $scheduleId = $ticket->trip->schedule_id;

        $component
            ->set('data.date', '2026-10-05')
            ->set('data.schedule_id', $scheduleId);

        $newTrip = Trip::query()
            ->whereDate('trip_date', '2026-10-05')
            ->where('schedule_id', $scheduleId)
            ->first();

        $this->assertNotNull($newTrip, 'El viaje destino debió crearse.');

        $data = $component->instance()->data;
        $this->assertSame((int) $newTrip->id, (int) ($data['trip_id'] ?? 0));

        // Autoselección del asiento con mismo número (asiento 1 libre en el nuevo viaje)
        $seat1 = Seat::where('bus_id', $newTrip->bus_id)->where('seat_number', '1')->first();
        $this->assertEquals([$seat1->id], $data['seat_ids'] ?? []);
    }

    public function test_confirmar_sin_asiento_falla_sin_mover_el_boleto(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-01');

        $originalTripId = $ticket->trip_id;

        $component = Livewire::test(RescheduleTicket::class, ['record' => $ticket->id]);

        $component
            ->set('data.date', '2026-10-05')
            ->set('data.schedule_id', $ticket->trip->schedule_id)
            // El usuario limpió su selección (el autoselect ya no vale) y confirmó sin asiento.
            ->set('data.seat_ids', [])
            ->call('confirmReschedule');

        // El boleto NO se movió
        $ticket->refresh();
        $this->assertSame($originalTripId, $ticket->trip_id);
        $this->assertSame(0, $ticket->dateChanges()->count());
    }

    public function test_confirmar_con_asiento_reprograma_el_boleto(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-01');

        $component = Livewire::test(RescheduleTicket::class, ['record' => $ticket->id]);

        $component
            ->set('data.date', '2026-10-05')
            ->set('data.schedule_id', $ticket->trip->schedule_id)
            ->call('confirmReschedule');

        $component->assertOk();

        $ticket->refresh();

        $newTrip = Trip::query()->whereDate('trip_date', '2026-10-05')->where('schedule_id', $ticket->trip->schedule_id)->first();

        $this->assertNotNull($newTrip);
        $this->assertSame((int) $newTrip->id, (int) $ticket->trip_id);
        $this->assertNotNull($ticket->seat_id, 'El boleto debe conservar un asiento.');
        $this->assertSame('1', (string) $ticket->seat->seat_number);
        $this->assertSame(1, $ticket->dateChanges()->count());
    }
}
