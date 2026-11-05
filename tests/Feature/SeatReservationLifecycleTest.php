<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\SeatReservation;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Support\SeatConflict;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Ciclo de vida de las reservas temporales: reserva sobre una vencida,
 * detección del conflicto en cualquier motor y operaciones acotadas a viajes.
 */
class SeatReservationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private Trip $otherTrip;

    /** @var array<int, Seat> */
    private array $seats = [];

    /** Sesión del vendedor que hace los pedidos (40 caracteres, como las de Laravel). */
    private string $mySession;

    private ?User $seller = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mySession = Str::random(40);

        $bus = Bus::create(['name' => 'Linea R', 'plate' => 'RRR111', 'seat_count' => 3, 'floors' => 1]);
        foreach ([1, 2, 3] as $number) {
            $this->seats[$number] = Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $number, 'is_active' => true, 'floor' => '1']);
        }
        $route = Route::create(['name' => 'Ruta R', 'bus_id' => $bus->id, 'is_active' => true]);
        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana R',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
        $this->trip = Trip::create(['route_id' => $route->id, 'schedule_id' => $schedule->id, 'bus_id' => $bus->id, 'trip_date' => '2026-12-01']);
        $this->otherTrip = Trip::create(['route_id' => $route->id, 'schedule_id' => $schedule->id, 'bus_id' => $bus->id, 'trip_date' => '2026-12-02']);
    }

    public function test_detecta_el_conflicto_de_reserva_de_mysql(): void
    {
        $exception = new QueryException(
            'mysql',
            'insert into `seat_reservations` ...',
            [],
            new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '709-13' for key 'seat_reservations.unique_trip_seat_reservation'", 23000),
        );

        $this->assertTrue(SeatConflict::isReservationViolation($exception));
        $this->assertFalse(SeatConflict::isActiveSeatViolation($exception));
    }

    public function test_detecta_el_conflicto_de_reserva_de_sqlite(): void
    {
        $this->reserve($this->trip, 1, 'otra-sesion');

        try {
            SeatReservation::insert([
                'trip_id' => $this->trip->id,
                'seat_id' => $this->seats[1]->id,
                'user_session_id' => $this->mySession,
                'expires_at' => now()->addMinutes(5),
            ]);
            $this->fail('La base debió rechazar la segunda reserva del asiento.');
        } catch (QueryException $e) {
            $this->assertTrue(SeatConflict::isReservationViolation($e));
        }
    }

    public function test_una_reserva_vencida_de_otra_sesion_no_impide_reservar(): void
    {
        $this->reserve($this->trip, 1, 'otra-sesion', now()->subMinute());

        $response = $this->api('/api/seat-reservations/reserve', [
            'trip_id' => $this->trip->id,
            'seat_ids' => [$this->seats[1]->id],
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('seat_reservations', ['trip_id' => $this->trip->id, 'seat_id' => $this->seats[1]->id, 'user_session_id' => $this->mySession]);
        $this->assertDatabaseMissing('seat_reservations', ['user_session_id' => 'otra-sesion']);
    }

    public function test_una_reserva_vigente_de_otra_sesion_se_rechaza(): void
    {
        $this->reserve($this->trip, 1, 'otra-sesion');

        $response = $this->api('/api/seat-reservations/reserve', [
            'trip_id' => $this->trip->id,
            'seat_ids' => [$this->seats[1]->id],
        ]);

        $response->assertStatus(409)->assertJson(['success' => false]);
        $this->assertDatabaseHas('seat_reservations', ['seat_id' => $this->seats[1]->id, 'user_session_id' => 'otra-sesion']);
    }

    public function test_el_choque_al_insertar_devuelve_el_mensaje_de_conflicto(): void
    {
        $this->reserve($this->trip, 1, 'otra-sesion');

        $result = SeatReservation::reserveSeats($this->trip->id, [$this->seats[1]->id], $this->mySession);

        $this->assertFalse($result['success']);
        $this->assertSame('Algunos asientos ya están reservados por otro usuario', $result['message']);
    }

    public function test_la_reserva_no_borra_vencidas_de_otros_asientos(): void
    {
        $this->reserve($this->trip, 2, 'otra-sesion', now()->subMinute());

        SeatReservation::reserveSeats($this->trip->id, [$this->seats[1]->id], $this->mySession);

        $this->assertDatabaseHas('seat_reservations', ['seat_id' => $this->seats[2]->id, 'user_session_id' => 'otra-sesion']);
    }

    public function test_liberar_con_viajes_solo_toca_esos_viajes(): void
    {
        $this->reserve($this->trip, 1, $this->mySession);
        $this->reserve($this->otherTrip, 1, $this->mySession);

        $this->api('/api/seat-reservations/release', [
            'trip_ids' => [$this->trip->id],
        ])->assertOk();

        $this->assertDatabaseMissing('seat_reservations', ['trip_id' => $this->trip->id]);
        $this->assertDatabaseHas('seat_reservations', ['trip_id' => $this->otherTrip->id, 'user_session_id' => $this->mySession]);
    }

    public function test_liberar_sin_viajes_libera_toda_la_sesion(): void
    {
        $this->reserve($this->trip, 1, $this->mySession);
        $this->reserve($this->otherTrip, 1, $this->mySession);
        $this->reserve($this->trip, 2, 'otra-sesion');

        $this->api('/api/seat-reservations/release', [])->assertOk();

        $this->assertDatabaseMissing('seat_reservations', ['user_session_id' => $this->mySession]);
        $this->assertDatabaseHas('seat_reservations', ['user_session_id' => 'otra-sesion']);
    }

    public function test_renovar_con_viajes_solo_renueva_esos_viajes(): void
    {
        $this->reserve($this->trip, 1, $this->mySession, now()->addMinute());
        $this->reserve($this->otherTrip, 1, $this->mySession, now()->addMinute());

        $this->api('/api/seat-reservations/keep-alive', [
            'trip_ids' => [$this->trip->id],
        ])->assertOk()->assertJson(['extended_reservations' => 1]);

        $renewed = SeatReservation::where('trip_id', $this->trip->id)->first();
        $untouched = SeatReservation::where('trip_id', $this->otherTrip->id)->first();
        $this->assertTrue($renewed->expires_at->greaterThan(now()->addMinutes(4)));
        $this->assertTrue($untouched->expires_at->lessThan(now()->addMinutes(2)));
    }

    public function test_renovar_sin_viajes_renueva_toda_la_sesion_y_no_las_vencidas(): void
    {
        $this->reserve($this->trip, 1, $this->mySession, now()->addMinute());
        $this->reserve($this->otherTrip, 1, $this->mySession, now()->addMinute());
        $this->reserve($this->trip, 2, $this->mySession, now()->subMinute());

        $this->api('/api/seat-reservations/keep-alive', [])
            ->assertOk()
            ->assertJson(['extended_reservations' => 2]);

        $expired = SeatReservation::where('seat_id', $this->seats[2]->id)->first();
        $this->assertTrue($expired->expires_at->isPast());
    }

    public function test_asientos_libres_para_la_sesion_usan_la_regla_de_la_reserva(): void
    {
        foreach ([4, 5, 6] as $number) {
            $this->seats[$number] = Seat::create(['bus_id' => $this->trip->bus_id, 'seat_number' => (string) $number, 'is_active' => true, 'floor' => '1']);
        }

        $this->seats[2]->update(['is_active' => false]);
        $this->sellSeat(1);
        $this->sellSeat(6)->delete();
        $this->reserve($this->trip, 3, 'otra-sesion');
        $this->reserve($this->trip, 4, $this->mySession);
        $this->reserve($this->trip, 5, 'otra-sesion', now()->subMinute());

        // Libres: 4 (propia), 5 (vencida de otro) y 6 (boleto dado de baja).
        $this->assertSame(3, $this->trip->availableSeatsCountForSession($this->mySession));
        // Para otra sesión, la reserva de "mi-sesion" ocupa el 4 y la propia del 3 cuenta como libre.
        $this->assertSame(3, $this->trip->availableSeatsCountForSession('otra-sesion'));
        $this->assertSame(2, $this->trip->availableSeatsCountForSession('tercera-sesion'));
    }

    private function sellSeat(int $seatNumber): Ticket
    {
        $user = User::factory()->vendedor()->create();
        $origin = Location::create(['name' => 'Origen '.$seatNumber, 'is_active' => true]);
        $destination = Location::create(['name' => 'Destino '.$seatNumber, 'is_active' => true]);
        $sale = Sale::create(['user_id' => $user->id, 'sale_date' => now(), 'total_amount' => 100]);
        $passenger = Passenger::create(['first_name' => 'Ana', 'last_name' => 'Gómez', 'dni' => '2233344'.$seatNumber, 'passenger_type' => 'adult']);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[$seatNumber]->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'price' => 100,
        ]);
    }

    private function reserve(Trip $trip, int $seatNumber, string $sessionId, $expiresAt = null): void
    {
        SeatReservation::create([
            'trip_id' => $trip->id,
            'seat_id' => $this->seats[$seatNumber]->id,
            'user_session_id' => $sessionId,
            'expires_at' => $expiresAt ?? now()->addMinutes(5),
        ]);
    }

    /**
     * Pedido a la API como un vendedor autenticado, con la cookie de la
     * sesión `$this->mySession`.
     *
     * @param  array<string, mixed>  $data
     */
    private function api(string $uri, array $data = []): TestResponse
    {
        return $this->actingAs($this->seller ??= User::factory()->create())
            ->withCredentials() // postJson manda las cookies sólo con esto
            ->withCookie(config('session.cookie'), $this->mySession)
            ->postJson($uri, $data);
    }
}
