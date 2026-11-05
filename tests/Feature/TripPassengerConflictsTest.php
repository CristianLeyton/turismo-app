<?php

namespace Tests\Feature;

use App\Exceptions\DuplicatePassengerOnTripException;
use App\Models\Bus;
use App\Models\Linea;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Support\TripPassengerConflicts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripPassengerConflictsTest extends TestCase
{
    use RefreshDatabase;

    protected Bus $bus;

    protected Trip $trip;

    protected Location $oran;

    protected Location $salta;

    protected int $seatNumber = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
        $this->actingAs($user);

        $this->oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $this->bus = $this->createBus('Linea 1');
        $this->trip = $this->createTrip($this->bus, '2026-12-01');
    }

    private function createBus(string $name, ?Linea $linea = null, bool $isReinforcement = false): Bus
    {
        $bus = Bus::create([
            'name' => $name,
            'plate' => strtoupper(substr(md5($name), 0, 6)),
            'seat_count' => 10,
            'floors' => 1,
            'linea_id' => $linea?->id,
            'is_reinforcement' => $isReinforcement,
        ]);

        foreach (range(1, 10) as $number) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $number, 'is_active' => true, 'floor' => '1']);
        }

        return $bus;
    }

    private function createTrip(Bus $bus, string $date): Trip
    {
        $route = Route::create(['name' => 'Orán - Salta '.$bus->id, 'bus_id' => $bus->id, 'is_active' => true]);
        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        return Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => $date,
        ]);
    }

    private function createTicket(?Trip $trip, string $dni, ?string $childDni = null): Ticket
    {
        $sale = Sale::create(['user_id' => auth()->id(), 'sale_date' => now(), 'total_amount' => 100]);

        $passenger = Passenger::create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'dni' => $dni,
            'passenger_type' => 'adult',
        ]);

        if ($childDni !== null) {
            Passenger::create([
                'first_name' => 'Nene',
                'last_name' => 'Pérez',
                'dni' => $childDni,
                'parent_passenger_id' => $passenger->id,
                'passenger_type' => 'child',
            ]);
        }

        $seat = $trip
            ? Seat::query()->where('bus_id', $trip->bus_id)->where('seat_number', (string) $this->seatNumber++)->first()
            : null;

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip?->id,
            'seat_id' => $seat?->id,
            'bus_id' => $trip?->bus_id ?? $this->bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'travels_with_child' => $childDni !== null,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 100,
        ]);
    }

    public function test_detecta_el_mismo_adulto_en_el_viaje(): void
    {
        $ticket = $this->createTicket($this->trip, '30123456');

        $conflicts = TripPassengerConflicts::find(['30123456'], [$this->trip->id]);

        $this->assertCount(1, $conflicts);
        $this->assertSame('30123456', $conflicts->first()['dni']);
        $this->assertSame($ticket->id, $conflicts->first()['ticket']->id);
        $this->assertSame($this->trip->id, $conflicts->first()['trip']->id);
    }

    public function test_detecta_un_adulto_que_ya_viaja_como_menor(): void
    {
        $this->createTicket($this->trip, '30123456', childDni: '45123456');

        $conflicts = TripPassengerConflicts::find(['45123456'], [$this->trip->id]);

        $this->assertCount(1, $conflicts);
        $this->assertSame('45123456', $conflicts->first()['dni']);
    }

    public function test_detecta_un_menor_que_ya_viaja_como_adulto_y_menor_contra_menor(): void
    {
        $this->createTicket($this->trip, '45123456');
        $this->createTicket($this->trip, '30999999', childDni: '46000000');

        $this->assertCount(1, TripPassengerConflicts::find(['45123456'], [$this->trip->id]));
        $this->assertCount(1, TripPassengerConflicts::find(['46000000'], [$this->trip->id]));
    }

    public function test_un_boleto_borrado_no_cuenta(): void
    {
        $this->createTicket($this->trip, '30123456')->delete();

        $this->assertCount(0, TripPassengerConflicts::find(['30123456'], [$this->trip->id]));
    }

    public function test_un_boleto_sin_fecha_no_cuenta(): void
    {
        $this->createTicket(null, '30123456');

        $this->assertCount(0, TripPassengerConflicts::find(['30123456'], [$this->trip->id]));
        $this->assertCount(0, TripPassengerConflicts::find(['30123456'], [null]));
    }

    public function test_dni_vacio_se_ignora(): void
    {
        $this->createTicket($this->trip, '30123456', childDni: '');

        $this->assertCount(0, TripPassengerConflicts::find(['', null, '  '], [$this->trip->id]));
    }

    public function test_el_boleto_excluido_no_cuenta(): void
    {
        $ticket = $this->createTicket($this->trip, '30123456');

        $this->assertCount(0, TripPassengerConflicts::find(['30123456'], [$this->trip->id], [$ticket->id]));
    }

    public function test_otro_viaje_del_mismo_dia_no_cuenta(): void
    {
        $linea = Linea::create(['name' => 'Oran - Salta']);
        $this->bus->update(['linea_id' => $linea->id]);
        $refuerzo = $this->createBus('Refuerzo', $linea, true);
        $tripRefuerzo = $this->createTrip($refuerzo, '2026-12-01');

        $this->createTicket($this->trip, '30123456');

        $this->assertCount(0, TripPassengerConflicts::find(['30123456'], [$tripRefuerzo->id]));
    }

    public function test_dni_con_espacios_coincide(): void
    {
        $this->createTicket($this->trip, '30123456');

        $this->assertCount(1, TripPassengerConflicts::find([' 30123456 '], [$this->trip->id]));
    }

    public function test_repeated_dnis_suma_adultos_y_menores(): void
    {
        $this->assertSame(['30123456'], TripPassengerConflicts::repeatedDnis(['30123456', '', '45123456', '30123456', null]));
        $this->assertSame([], TripPassengerConflicts::repeatedDnis(['30123456', '45123456', '', '']));
    }

    public function test_la_excepcion_lleva_los_conflictos_y_el_mensaje_generico(): void
    {
        $this->createTicket($this->trip, '30123456');
        $conflicts = TripPassengerConflicts::find(['30123456'], [$this->trip->id]);

        $exception = new DuplicatePassengerOnTripException($conflicts);

        $this->assertSame($conflicts, $exception->conflicts());
        $this->assertSame(['trip' => [DuplicatePassengerOnTripException::MESSAGE]], $exception->errors());
    }
}
