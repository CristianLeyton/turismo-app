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

        // Fechas fijas del fixture: congelamos el reloj en la fecha del viaje
        // original para que las reprogramaciones a 2026-10-05 sigan siendo
        // "futuras" sin importar cuándo corra la suite (minDate = today).
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

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

    // ============ Fechas cruzadas (ida ≤ vuelta) en el formulario ============

    public function test_el_form_rechaza_la_ida_posterior_a_la_vuelta(): void
    {
        $this->actingAs($this->admin);

        $f = $this->createRoundTripTickets('2026-10-02', '2026-10-10');
        $originalTripId = $f['outbound']->trip_id;

        $component = Livewire::test(RescheduleTicket::class, ['record' => $f['outbound']->id]);

        // Fecha de ida posterior a la vuelta: el calendario la acota y el form
        // la rechaza (el service es el backstop, pero la UX ya no lo permite).
        $component
            ->set('data.date', '2026-10-15')
            ->set('data.schedule_id', $f['scheduleIda']->id)
            ->set('data.seat_ids', [$f['idaSeat3']->id])
            ->call('confirmReschedule');

        // El error es el del cruce de fechas, no otro cualquiera del form.
        $component
            ->assertHasFormErrors(['date'])
            ->assertSee('La fecha no puede ser posterior a la vuelta.');

        $f['outbound']->refresh();
        $this->assertSame($originalTripId, $f['outbound']->trip_id, 'El boleto no debe moverse.');
        $this->assertSame(0, $f['outbound']->dateChanges()->count());
    }

    public function test_el_form_permite_la_ida_anterior_a_la_vuelta_y_reprograma(): void
    {
        $this->actingAs($this->admin);

        $f = $this->createRoundTripTickets('2026-10-02', '2026-10-10');

        $component = Livewire::test(RescheduleTicket::class, ['record' => $f['outbound']->id]);

        $component
            ->set('data.date', '2026-10-05')
            ->set('data.schedule_id', $f['scheduleIda']->id)
            ->set('data.seat_ids', [$f['idaSeat3']->id])
            ->call('confirmReschedule');

        $f['outbound']->refresh();

        $this->assertSame('2026-10-05', $f['outbound']->trip->trip_date->format('Y-m-d'));
        $this->assertSame($f['returnTrip']->id, (int) $f['outbound']->return_trip_id);
    }

    public function test_scope_ambos_permite_mover_los_dos_tramos_al_futuro(): void
    {
        $this->actingAs($this->admin);

        $f = $this->createRoundTripTickets('2026-10-02', '2026-10-10');

        // Con "Ida y vuelta" el tope de la ida se relaja: se mueven los DOS
        // tramos más adelante (la ida 15/10 supera la vieja vuelta 10/10).
        $component = Livewire::test(RescheduleTicket::class, ['record' => $f['outbound']->id]);

        $component
            ->set('data.scope', 'both')
            ->set('data.date', '2026-10-15')
            ->set('data.schedule_id', $f['scheduleIda']->id)
            ->set('data.seat_ids', [$f['idaSeat3']->id])
            ->set('data.return_date', '2026-10-20')
            ->set('data.return_schedule_id', $f['scheduleVuelta']->id)
            ->set('data.return_seat_ids', [$f['vueltaSeat4']->id])
            ->call('confirmReschedule');

        $f['outbound']->refresh();
        $f['returnTicket']->refresh();

        $this->assertSame('2026-10-15', $f['outbound']->trip->trip_date->format('Y-m-d'));
        $this->assertSame('2026-10-20', $f['returnTicket']->trip->trip_date->format('Y-m-d'));
        $this->assertSame((int) $f['returnTicket']->trip_id, (int) $f['outbound']->return_trip_id);
    }

    public function test_el_tramo_de_vuelta_se_rotula_y_se_acota_contra_la_ida(): void
    {
        $this->actingAs($this->admin);

        $f = $this->createRoundTripTickets('2026-10-05', '2026-10-10');

        // Reprogramar el tramo de vuelta: los campos deben decir "vuelta".
        $component = Livewire::test(RescheduleTicket::class, ['record' => $f['returnTicket']->id]);

        $component
            ->assertOk()
            ->assertSee('Nueva fecha de vuelta')
            ->assertSee('Horario de vuelta')
            ->assertDontSee('Nueva fecha de ida');

        // Fecha anterior a la ida (2026-10-05): el form la rechaza.
        $component
            ->set('data.date', '2026-10-03')
            ->set('data.schedule_id', $f['scheduleVuelta']->id)
            ->set('data.seat_ids', [$f['vueltaSeat4']->id])
            ->call('confirmReschedule');

        $component
            ->assertHasFormErrors(['date'])
            ->assertSee('La fecha no puede ser anterior a hoy ni a la otra fecha del pasaje.');

        $f['returnTicket']->refresh();
        $this->assertSame($f['returnTrip']->id, (int) $f['returnTicket']->trip_id, 'La vuelta no debe moverse.');
    }

    public function test_la_ida_y_vuelta_sin_fecha_permite_reprogramar_los_dos_tramos_juntos(): void
    {
        $this->actingAs($this->admin);

        $f = $this->createBothPendingRoundTripTickets();

        $component = Livewire::test(RescheduleTicket::class, ['record' => $f['outbound']->id]);

        // La opción "Ida y vuelta" está disponible aunque la vuelta siga pendiente.
        $component
            ->assertOk()
            ->assertSee('¿Qué tramo querés reprogramar?')
            ->assertSee('Ida y vuelta');

        $component
            ->set('data.scope', 'both')
            ->set('data.date', '2026-10-05')
            ->set('data.schedule_id', $f['scheduleIda']->id)
            ->set('data.seat_ids', [$f['idaSeat']->id])
            ->set('data.return_date', '2026-10-10')
            ->set('data.return_schedule_id', $f['scheduleVuelta']->id)
            ->set('data.return_seat_ids', [$f['vueltaSeat']->id])
            ->call('confirmReschedule');

        $component->assertHasNoFormErrors();

        $f['outbound']->refresh();
        $f['returnTicket']->refresh();

        // Los DOS tramos quedaron con viaje en un solo paso.
        $this->assertSame('2026-10-05', $f['outbound']->trip->trip_date->format('Y-m-d'));
        $this->assertSame($f['idaSeat']->id, $f['outbound']->seat_id);
        $this->assertSame('2026-10-10', $f['returnTicket']->trip->trip_date->format('Y-m-d'));
        $this->assertSame($f['vueltaSeat']->id, $f['returnTicket']->seat_id);

        // Y quedaron ligados entre sí.
        $this->assertSame((int) $f['returnTicket']->trip_id, (int) $f['outbound']->return_trip_id);
        $this->assertSame(2, $f['outbound']->dateChanges()->count() + $f['returnTicket']->dateChanges()->count());
    }

    public function test_la_ida_y_vuelta_sin_fecha_sigue_aceptando_mover_solo_un_tramo(): void
    {
        $this->actingAs($this->admin);

        $f = $this->createBothPendingRoundTripTickets();

        // Sin tocar el alcance (default "Solo este tramo") se asigna sólo la ida.
        $component = Livewire::test(RescheduleTicket::class, ['record' => $f['outbound']->id]);

        $component
            ->set('data.date', '2026-10-05')
            ->set('data.schedule_id', $f['scheduleIda']->id)
            ->set('data.seat_ids', [$f['idaSeat']->id])
            ->call('confirmReschedule');

        $component->assertHasNoFormErrors();

        $f['outbound']->refresh();
        $f['returnTicket']->refresh();

        $this->assertSame('2026-10-05', $f['outbound']->trip->trip_date->format('Y-m-d'));
        $this->assertNull($f['returnTicket']->trip_id, 'La vuelta debe seguir pendiente.');
        $this->assertNull($f['outbound']->return_trip_id, 'Sin viaje de vuelta, el link sigue en NULL.');
    }

    /**
     * Fixture de la venta diferida SIN FECHA en ambos tramos: dos boletos
     * pendientes (trip_id NULL) del mismo pasajero y los viajes se resuelven al
     * reprogramar. Devuelve los dos boletos y lo necesario para asignarles fecha.
     *
     * @return array{outbound: Ticket, returnTicket: Ticket, scheduleIda: Schedule, scheduleVuelta: Schedule, idaSeat: Seat, vueltaSeat: Seat}
     */
    private function createBothPendingRoundTripTickets(): array
    {
        $oran = Location::create(['name' => 'Orán SF', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta SF', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea SF', 'plate' => 'SFSF11', 'seat_count' => 6, 'floors' => 1]);
        foreach ([1, 2, 3, 4, 5, 6] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $routeIda = Route::create(['name' => 'Orán - Salta SF', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeIda->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeIda->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $routeVuelta = Route::create(['name' => 'Salta - Orán SF', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $salta->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $oran->id, 'stop_order' => 2]);

        $scheduleIda = Schedule::create([
            'route_id' => $routeIda->id,
            'name' => 'Mañana SF',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
        $scheduleVuelta = Schedule::create([
            'route_id' => $routeVuelta->id,
            'name' => 'Tarde SF',
            'departure_time' => '14:00',
            'arrival_time' => '18:00',
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'sale_date' => now(),
            'total_amount' => 15000,
        ]);

        $passenger = Passenger::create([
            'first_name' => 'Sin',
            'last_name' => 'Fecha',
            'dni' => '11223344',
            'passenger_type' => 'adult',
        ]);

        $outbound = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => null,
            'seat_id' => null,
            'return_trip_id' => null,
            'bus_id' => $bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 15000,
        ]);

        $returnTicket = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => null,
            'seat_id' => null,
            'return_trip_id' => null,
            'bus_id' => $bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => true,
            'origin_location_id' => $salta->id,
            'destination_location_id' => $oran->id,
            'price' => 0,
        ]);

        return [
            'outbound' => $outbound,
            'returnTicket' => $returnTicket,
            'scheduleIda' => $scheduleIda,
            'scheduleVuelta' => $scheduleVuelta,
            'idaSeat' => Seat::where('bus_id', $bus->id)->where('seat_number', '3')->first(),
            'vueltaSeat' => Seat::where('bus_id', $bus->id)->where('seat_number', '4')->first(),
        ];
    }

    /**
     * Fixture de ida y vuelta: dos boletos del mismo pasajero con viajes reales
     * en direcciones opuestas y un asiento libre extra por sentido.
     *
     * @return array{outbound: Ticket, returnTicket: Ticket, outboundTrip: Trip, returnTrip: Trip, scheduleIda: Schedule, scheduleVuelta: Schedule, idaSeat3: Seat, vueltaSeat4: Seat}
     */
    private function createRoundTripTickets(string $outboundDate, string $returnDate): array
    {
        $oran = Location::create(['name' => 'Orán RT', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta RT', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea RT', 'plate' => 'RTRT11', 'seat_count' => 6, 'floors' => 1]);
        foreach ([1, 2, 3, 4, 5, 6] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $routeIda = Route::create(['name' => 'Orán - Salta RT', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeIda->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeIda->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $routeVuelta = Route::create(['name' => 'Salta - Orán RT', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $salta->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $oran->id, 'stop_order' => 2]);

        $scheduleIda = Schedule::create([
            'route_id' => $routeIda->id,
            'name' => 'Mañana RT',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
        $scheduleVuelta = Schedule::create([
            'route_id' => $routeVuelta->id,
            'name' => 'Tarde RT',
            'departure_time' => '14:00',
            'arrival_time' => '18:00',
            'is_active' => true,
        ]);

        $outboundTrip = Trip::create([
            'route_id' => $routeIda->id,
            'schedule_id' => $scheduleIda->id,
            'bus_id' => $bus->id,
            'trip_date' => $outboundDate,
        ]);
        $returnTrip = Trip::create([
            'route_id' => $routeVuelta->id,
            'schedule_id' => $scheduleVuelta->id,
            'bus_id' => $bus->id,
            'trip_date' => $returnDate,
        ]);

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'sale_date' => now(),
            'total_amount' => 100,
        ]);

        $passenger = Passenger::create([
            'first_name' => 'Ida',
            'last_name' => 'Vuelta',
            'dni' => '99887766',
            'passenger_type' => 'adult',
        ]);

        $seat = fn (string $number) => Seat::where('bus_id', $bus->id)->where('seat_number', $number)->first();

        $outbound = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $outboundTrip->id,
            'return_trip_id' => $returnTrip->id,
            'seat_id' => $seat('1')->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
        ]);

        $returnTicket = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $returnTrip->id,
            'seat_id' => $seat('2')->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => true,
            'origin_location_id' => $salta->id,
            'destination_location_id' => $oran->id,
            'price' => 0,
        ]);

        return [
            'outbound' => $outbound,
            'returnTicket' => $returnTicket,
            'outboundTrip' => $outboundTrip,
            'returnTrip' => $returnTrip,
            'scheduleIda' => $scheduleIda,
            'scheduleVuelta' => $scheduleVuelta,
            'idaSeat3' => $seat('3'),
            'vueltaSeat4' => $seat('4'),
        ];
    }
}
