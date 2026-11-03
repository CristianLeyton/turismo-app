<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\PaymentMethod;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reservas temporales en el asistente de venta: disponibilidad del paso 1,
 * liberación acotada a los viajes de la venta y cambio de viaje.
 */
class SeatReservationWizardTest extends TestCase
{
    use RefreshDatabase;

    private Location $origin;

    private Location $destination;

    private Bus $bus;

    private Schedule $morning;

    private Schedule $afternoon;

    /** @var array<int, Seat> */
    private array $seats = [];

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');
        Setting::flushCache();

        $this->travelTo(Carbon::parse('2026-10-01 06:00:00'));
        $this->actingAs(User::factory()->vendedor()->create());

        $this->origin = Location::create(['name' => 'Orán V', 'is_active' => true]);
        $this->destination = Location::create(['name' => 'Salta V', 'is_active' => true]);
        $this->bus = Bus::create(['name' => 'Linea V', 'plate' => 'VVV111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $number) {
            $this->seats[$number] = Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $number, 'is_active' => true, 'floor' => '1']);
        }
        $route = Route::create(['name' => 'Orán - Salta V', 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->origin->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->destination->id, 'stop_order' => 2]);
        $this->morning = Schedule::create(['route_id' => $route->id, 'name' => 'Mañana V', 'departure_time' => '08:00', 'arrival_time' => '12:00', 'is_active' => true]);
        $this->afternoon = Schedule::create(['route_id' => $route->id, 'name' => 'Tarde V', 'departure_time' => '15:00', 'arrival_time' => '19:00', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    // ====================== Disponibilidad del paso 1 ======================

    public function test_el_paso_1_no_cuenta_asientos_inactivos_ni_reservados_por_otros(): void
    {
        $trip = $this->tripFor($this->morning);
        $this->seats[1]->update(['is_active' => false]);
        $this->reserve($trip, 2, 'otra-sesion');

        $page = $this->mountWizard($this->morning, 3);
        $this->nextStep($page);

        $page->assertNotDispatched('next-wizard-step');
        $page->assertNotified('Asientos insuficientes');
    }

    public function test_el_paso_1_cuenta_como_libres_las_reservas_propias(): void
    {
        $trip = $this->tripFor($this->morning);
        $this->sellSeat($trip, 3);
        $this->sellSeat($trip, 4);
        $this->reserve($trip, 1, session()->getId());
        $this->reserve($trip, 2, session()->getId());

        $page = $this->mountWizard($this->morning, 2);
        $this->nextStep($page);

        $page->assertDispatched('next-wizard-step');
    }

    // ====================== Liberación acotada a la venta ======================

    public function test_cargar_la_pagina_de_venta_no_borra_las_reservas_de_la_sesion(): void
    {
        $trip = $this->tripFor($this->morning);
        $sessionId = Str::random(40);
        $this->reserve($trip, 1, $sessionId);

        $this->withCookie(config('session.cookie'), $sessionId)
            ->get(TicketResource::getUrl('create'))
            ->assertOk();

        $this->assertSame($sessionId, $this->app['session']->getId(), 'La página debió cargarse con la sesión de la reserva.');
        $this->assertDatabaseHas('seat_reservations', ['trip_id' => $trip->id, 'user_session_id' => $sessionId]);
    }

    public function test_cancelar_libera_solo_los_viajes_de_la_venta(): void
    {
        $outbound = $this->tripFor($this->morning);
        $return = $this->tripFor($this->afternoon);
        $otherSale = Trip::findOrCreateForBooking($this->morning->id, '2026-10-05', $this->origin->id, $this->destination->id)['trip'];
        $sessionId = session()->getId();
        $this->reserve($outbound, 1, $sessionId);
        $this->reserve($return, 1, $sessionId);
        $this->reserve($otherSale, 1, $sessionId);

        Livewire::test(CreateTicket::class)
            ->set('data.trip_id', $outbound->id)
            ->set('data.return_trip_id', $return->id)
            ->callAction(TestAction::make('cancel')->schemaComponent('form-actions', 'content'));

        $this->assertDatabaseMissing('seat_reservations', ['trip_id' => $outbound->id]);
        $this->assertDatabaseMissing('seat_reservations', ['trip_id' => $return->id]);
        $this->assertDatabaseHas('seat_reservations', ['trip_id' => $otherSale->id, 'user_session_id' => $sessionId]);
    }

    // ====================== Cambio de viaje en el paso 1 ======================

    public function test_cambiar_el_horario_libera_el_viaje_anterior_y_vacia_la_seleccion(): void
    {
        $morningTrip = $this->tripFor($this->morning);
        $page = $this->mountWizard($this->morning);
        $this->nextStep($page);
        $page->assertSet('data.reserved_trip_id', $morningTrip->id);

        $this->reserve($morningTrip, 1, session()->getId());
        $page->set('data.seat_ids', [$this->seats[1]->id]);

        $page->fillForm(['schedule_id' => (string) $this->afternoon->id]);
        $this->nextStep($page);

        $afternoonTrip = $this->tripFor($this->afternoon);
        $page->assertSet('data.trip_id', $afternoonTrip->id)
            ->assertSet('data.reserved_trip_id', $afternoonTrip->id)
            ->assertSet('data.seat_ids', []);
        $this->assertDatabaseMissing('seat_reservations', ['trip_id' => $morningTrip->id]);
    }

    public function test_el_selector_no_expone_la_sesion_y_marca_la_reserva_propia(): void
    {
        $trip = $this->tripFor($this->morning);
        $page = $this->mountWizard($this->morning);
        $this->nextStep($page);

        // El asiento 1 no entra en la grilla del colectivo de prueba: se usa el 2.
        $this->reserve($trip, 2, session()->getId());
        $page->call('$refresh');

        $page->assertSee('Tu asiento reservado')
            ->assertDontSee(session()->getId(), false)
            ->assertDontSee('sessionId', false);
    }

    public function test_volver_al_paso_1_sin_cambiar_el_viaje_conserva_reservas_y_seleccion(): void
    {
        $trip = $this->tripFor($this->morning);
        $page = $this->mountWizard($this->morning);
        $this->nextStep($page);

        $this->reserve($trip, 1, session()->getId());
        $page->set('data.seat_ids', [$this->seats[1]->id]);

        $this->nextStep($page);

        $page->assertSet('data.seat_ids', [$this->seats[1]->id]);
        $this->assertDatabaseHas('seat_reservations', ['trip_id' => $trip->id, 'user_session_id' => session()->getId()]);
    }

    public function test_pasar_la_vuelta_a_sin_fecha_libera_la_reserva_de_la_vuelta(): void
    {
        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        $returnRoute = Route::create(['name' => 'Salta - Orán V', 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $returnRoute->id, 'location_id' => $this->destination->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $returnRoute->id, 'location_id' => $this->origin->id, 'stop_order' => 2]);
        $returnSchedule = Schedule::create(['route_id' => $returnRoute->id, 'name' => 'Vuelta V', 'departure_time' => '17:00', 'arrival_time' => '21:00', 'is_active' => true]);

        $page = $this->mountWizard($this->morning)
            ->fillForm([
                'is_round_trip' => true,
                'return_date' => '2026-10-02',
                'return_schedule_id' => (string) $returnSchedule->id,
            ]);
        $this->nextStep($page);

        $returnTripId = $page->get('data.reserved_return_trip_id');
        $this->assertNotNull($returnTripId);
        $this->reserve(Trip::find($returnTripId), 1, session()->getId());
        $page->set('data.return_seat_ids', [$this->seats[1]->id]);

        $page->fillForm(['sell_without_date_vuelta' => true]);
        $this->nextStep($page);

        $page->assertSet('data.reserved_return_trip_id', null)
            ->assertSet('data.return_seat_ids', []);
        $this->assertDatabaseMissing('seat_reservations', ['trip_id' => $returnTripId]);
    }

    // ====================== Helpers ======================

    private function mountWizard(Schedule $schedule, int $passengers = 1): Testable
    {
        return Livewire::test(CreateTicket::class)
            ->fillForm([
                'bus_id' => (string) $this->bus->id,
                'origin_location_id' => (string) $this->origin->id,
                'destination_location_id' => (string) $this->destination->id,
                'departure_date' => '2026-10-01',
                'schedule_id' => (string) $schedule->id,
                'passengers_count' => (string) $passengers,
            ]);
    }

    private function nextStep(Testable $page, int $currentStepIndex = 0): void
    {
        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => $currentStepIndex]);
    }

    private function tripFor(Schedule $schedule): Trip
    {
        $trip = Trip::findOrCreateForBooking($schedule->id, '2026-10-01', $this->origin->id, $this->destination->id)['trip'];
        $this->assertNotNull($trip);

        return $trip;
    }

    private function reserve(Trip $trip, int $seatNumber, string $sessionId): SeatReservation
    {
        return SeatReservation::create([
            'trip_id' => $trip->id,
            'seat_id' => $this->seats[$seatNumber]->id,
            'user_session_id' => $sessionId,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    private function sellSeat(Trip $trip, int $seatNumber): Ticket
    {
        $sale = Sale::create(['user_id' => auth()->id(), 'sale_date' => now(), 'total_amount' => 100]);
        $passenger = Passenger::create(['first_name' => 'Ana', 'last_name' => 'Gómez', 'dni' => '3344455'.$seatNumber, 'passenger_type' => 'adult']);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'seat_id' => $this->seats[$seatNumber]->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'price' => 100,
        ]);
    }
}
