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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lista de viajes: los viajes sin pasajeros (sin boletos activos) se ocultan
 * por defecto y el switch "Ver también viajes sin pasajeros" los muestra.
 */
class TripListEmptyTripsTest extends TestCase
{
    use RefreshDatabase;

    private Bus $bus;

    private Schedule $schedule;

    private Route $route;

    private Location $origin;

    private Location $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());

        $this->origin = Location::create(['name' => 'Orán V', 'is_active' => true]);
        $this->destination = Location::create(['name' => 'Salta V', 'is_active' => true]);
        $this->bus = Bus::create(['name' => 'Linea V', 'plate' => 'VVV111', 'seat_count' => 4, 'floors' => 1]);
        Seat::create(['bus_id' => $this->bus->id, 'seat_number' => '1', 'is_active' => true, 'floor' => '1']);
        $this->route = Route::create(['name' => 'Ruta V', 'bus_id' => $this->bus->id, 'is_active' => true]);
        $this->schedule = Schedule::create([
            'route_id' => $this->route->id,
            'name' => 'Mañana V',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
    }

    private function createTrip(string $date): Trip
    {
        return Trip::create([
            'route_id' => $this->route->id,
            'schedule_id' => $this->schedule->id,
            'bus_id' => $this->bus->id,
            'trip_date' => $date,
        ]);
    }

    private function sellTicket(Trip $trip): Ticket
    {
        return Ticket::create([
            'sale_id' => Sale::create(['user_id' => auth()->id(), 'sale_date' => now(), 'total_amount' => 100])->id,
            'trip_id' => $trip->id,
            'seat_id' => Seat::where('bus_id', $this->bus->id)->first()->id,
            'passenger_id' => Passenger::create([
                'first_name' => 'Ana',
                'last_name' => 'Pérez',
                'dni' => '30123456',
                'passenger_type' => 'adult',
            ])->id,
            'is_round_trip' => false,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'price' => 100,
        ]);
    }

    public function test_por_defecto_se_ocultan_los_viajes_sin_pasajeros(): void
    {
        $withPassengers = $this->createTrip('2026-10-05');
        $this->sellTicket($withPassengers);
        $empty = $this->createTrip('2026-10-06');

        Livewire::test(ManageTrips::class)
            ->assertSee('Ver también viajes sin pasajeros')
            ->assertCanSeeTableRecords([$withPassengers])
            ->assertCanNotSeeTableRecords([$empty]);
    }

    public function test_un_viaje_con_su_unico_boleto_borrado_no_aparece(): void
    {
        $trip = $this->createTrip('2026-10-05');
        $this->sellTicket($trip)->delete();

        Livewire::test(ManageTrips::class)
            ->assertCanNotSeeTableRecords([$trip]);

        $this->assertNotNull(Trip::find($trip->id), 'El viaje oculto sigue existiendo.');
    }

    public function test_con_el_switch_encendido_se_ven_todos_los_viajes(): void
    {
        $withPassengers = $this->createTrip('2026-10-05');
        $this->sellTicket($withPassengers);
        $empty = $this->createTrip('2026-10-06');

        Livewire::test(ManageTrips::class)
            ->filterTable('include_empty_trips', ['include_empty' => true])
            ->assertCanSeeTableRecords([$withPassengers, $empty]);
    }

    public function test_el_switch_se_combina_con_el_filtro_por_fecha(): void
    {
        $withPassengers = $this->createTrip('2026-10-05');
        $this->sellTicket($withPassengers);
        $sameDateSchedule = Schedule::create([
            'route_id' => $this->route->id,
            'name' => 'Tarde V',
            'departure_time' => '16:00',
            'arrival_time' => '20:00',
            'is_active' => true,
        ]);
        $emptySameDate = Trip::create([
            'route_id' => $this->route->id,
            'schedule_id' => $sameDateSchedule->id,
            'bus_id' => $this->bus->id,
            'trip_date' => '2026-10-05',
        ]);
        $otherDate = $this->createTrip('2026-10-06');

        Livewire::test(ManageTrips::class)
            ->filterTable('include_empty_trips', ['include_empty' => true])
            ->filterTable('trip_date', ['date' => '2026-10-05'])
            ->assertCanSeeTableRecords([$withPassengers, $emptySameDate])
            ->assertCanNotSeeTableRecords([$otherDate]);
    }

    public function test_apagar_el_switch_vuelve_a_ocultarlos(): void
    {
        $empty = $this->createTrip('2026-10-06');

        Livewire::test(ManageTrips::class)
            ->filterTable('include_empty_trips', ['include_empty' => true])
            ->assertCanSeeTableRecords([$empty])
            ->filterTable('include_empty_trips', ['include_empty' => false])
            ->assertCanNotSeeTableRecords([$empty]);
    }

    public function test_buscar_por_numero_muestra_el_viaje_vacio_con_el_switch_apagado(): void
    {
        $empty = $this->createTrip('2026-10-06');
        $other = $this->createTrip('2026-10-07');

        Livewire::test(ManageTrips::class)
            ->filterTable('trip_id', ['id' => $empty->id])
            ->assertCanSeeTableRecords([$empty])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_los_viajes_sin_pasajeros_se_ven_en_gris(): void
    {
        $withPassengers = $this->createTrip('2026-10-05');
        $this->sellTicket($withPassengers);
        $empty = $this->createTrip('2026-10-06');

        $component = Livewire::test(ManageTrips::class)
            ->filterTable('include_empty_trips', ['include_empty' => true]);

        $table = $component->instance()->getTable();
        $records = $component->instance()->getTableRecords()->keyBy('id');

        $this->assertStringContainsString('opacity-70', $this->rowClasses($table->getRecordClasses($records[$empty->id])));
        $this->assertStringNotContainsString('opacity-70', $this->rowClasses($table->getRecordClasses($records[$withPassengers->id])));
    }

    /**
     * @param  array<int|string, mixed>  $classes
     */
    private function rowClasses(array $classes): string
    {
        return implode(' ', array_map(
            fn ($value, $key): string => is_string($key) ? ($value ? $key : '') : (string) $value,
            $classes,
            array_keys($classes),
        ));
    }
}
