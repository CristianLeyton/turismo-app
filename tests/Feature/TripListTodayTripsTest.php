<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\ManageTrips;
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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lista de viajes: el switch "Mostrar solo los viajes que parten hoy" (spec
 * trip-list) se combina con el de viajes sin pasajeros y cede ante la
 * búsqueda por "Viaje Nº".
 */
class TripListTodayTripsTest extends TestCase
{
    use RefreshDatabase;

    private Bus $bus;

    private Route $route;

    private Location $origin;

    private Location $destination;

    private int $schedules = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Las 15:00 de hoy: el viaje de las 08:00 ya salió.
        $this->travelTo(Carbon::parse('2026-10-05 15:00:00'));

        $this->actingAs(User::factory()->admin()->create());

        $this->origin = Location::create(['name' => 'Orán H', 'is_active' => true]);
        $this->destination = Location::create(['name' => 'Salta H', 'is_active' => true]);
        $this->bus = Bus::create(['name' => 'Linea H', 'plate' => 'HHH111', 'seat_count' => 4, 'floors' => 1]);
        Seat::create(['bus_id' => $this->bus->id, 'seat_number' => '1', 'is_active' => true, 'floor' => '1']);
        $this->route = Route::create(['name' => 'Ruta H', 'bus_id' => $this->bus->id, 'is_active' => true]);
    }

    public function test_por_defecto_el_switch_esta_apagado_y_se_ven_todas_las_fechas(): void
    {
        $today = $this->tripWithPassenger('2026-10-05');
        $tomorrow = $this->tripWithPassenger('2026-10-06');

        Livewire::test(ManageTrips::class)
            ->assertSee('Mostrar solo los viajes que parten hoy')
            ->assertSet('tableFilters.only_today_trips.only_today', false)
            ->assertCanSeeTableRecords([$today, $tomorrow]);
    }

    public function test_encendido_muestra_solo_los_de_hoy_incluso_los_que_ya_salieron(): void
    {
        $alreadyLeft = $this->tripWithPassenger('2026-10-05', '08:00');
        $later = $this->tripWithPassenger('2026-10-05', '20:00');
        $tomorrow = $this->tripWithPassenger('2026-10-06');
        $yesterday = $this->tripWithPassenger('2026-10-04');

        Livewire::test(ManageTrips::class)
            ->filterTable('only_today_trips', ['only_today' => true])
            ->assertCanSeeTableRecords([$alreadyLeft, $later])
            ->assertCanNotSeeTableRecords([$tomorrow, $yesterday]);
    }

    public function test_con_los_dos_switches_se_ven_los_de_hoy_con_y_sin_pasajeros(): void
    {
        $todayWithPassengers = $this->tripWithPassenger('2026-10-05');
        $todayEmpty = $this->createTrip('2026-10-05', '20:00');
        $tomorrowEmpty = $this->createTrip('2026-10-06');

        Livewire::test(ManageTrips::class)
            ->filterTable('only_today_trips', ['only_today' => true])
            ->filterTable('include_empty_trips', ['include_empty' => true])
            ->assertCanSeeTableRecords([$todayWithPassengers, $todayEmpty])
            ->assertCanNotSeeTableRecords([$tomorrowEmpty]);
    }

    public function test_solo_hoy_sigue_ocultando_los_viajes_de_hoy_sin_pasajeros(): void
    {
        $todayWithPassengers = $this->tripWithPassenger('2026-10-05');
        $todayEmpty = $this->createTrip('2026-10-05', '20:00');

        Livewire::test(ManageTrips::class)
            ->filterTable('only_today_trips', ['only_today' => true])
            ->assertCanSeeTableRecords([$todayWithPassengers])
            ->assertCanNotSeeTableRecords([$todayEmpty]);
    }

    public function test_buscar_por_numero_muestra_un_viaje_de_otro_dia_con_el_switch_encendido(): void
    {
        $today = $this->tripWithPassenger('2026-10-05');
        $tomorrow = $this->tripWithPassenger('2026-10-06');

        Livewire::test(ManageTrips::class)
            ->filterTable('only_today_trips', ['only_today' => true])
            ->filterTable('trip_id', ['id' => $tomorrow->id])
            ->assertCanSeeTableRecords([$tomorrow])
            ->assertCanNotSeeTableRecords([$today]);
    }

    public function test_apagar_el_switch_vuelve_a_mostrar_todas_las_fechas(): void
    {
        $today = $this->tripWithPassenger('2026-10-05');
        $tomorrow = $this->tripWithPassenger('2026-10-06');

        Livewire::test(ManageTrips::class)
            ->filterTable('only_today_trips', ['only_today' => true])
            ->assertCanNotSeeTableRecords([$tomorrow])
            ->filterTable('only_today_trips', ['only_today' => false])
            ->assertCanSeeTableRecords([$today, $tomorrow]);
    }

    public function test_el_switch_se_conserva_en_la_sesion(): void
    {
        $today = $this->tripWithPassenger('2026-10-05');
        $tomorrow = $this->tripWithPassenger('2026-10-06');

        Livewire::test(ManageTrips::class)
            ->filterTable('only_today_trips', ['only_today' => true]);

        // Al volver a abrir la lista, el filtro sigue aplicado.
        Livewire::test(ManageTrips::class)
            ->assertSet('tableFilters.only_today_trips.only_today', true)
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$tomorrow]);
    }

    private function createTrip(string $date, string $time = '08:00'): Trip
    {
        $this->schedules++;

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'name' => "Horario H{$this->schedules}",
            'departure_time' => $time,
            'arrival_time' => '23:00',
            'is_active' => true,
        ]);

        return Trip::create([
            'route_id' => $this->route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $this->bus->id,
            'trip_date' => $date,
        ]);
    }

    private function tripWithPassenger(string $date, string $time = '08:00'): Trip
    {
        $trip = $this->createTrip($date, $time);

        Ticket::create([
            'sale_id' => Sale::create(['user_id' => auth()->id(), 'sale_date' => now(), 'total_amount' => 100])->id,
            'trip_id' => $trip->id,
            'seat_id' => Seat::where('bus_id', $this->bus->id)->first()->id,
            'passenger_id' => Passenger::create([
                'first_name' => 'Ana',
                'last_name' => 'Pérez',
                'dni' => (string) (30000000 + $trip->id),
                'passenger_type' => 'adult',
            ])->id,
            'is_round_trip' => false,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'price' => 100,
        ]);

        return $trip;
    }
}
