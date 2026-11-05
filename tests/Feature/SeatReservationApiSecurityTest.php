<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Clients;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\SeatReservation;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * API de reservas del selector (change api-reservas-segura, hallazgo H5):
 * exige login, opera sólo sobre la sesión del pedido, no expone detalles
 * internos y ya no tiene endpoint de limpieza.
 */
class SeatReservationApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    /** @var array<int, Seat> */
    private array $seats = [];

    private string $mySession;

    private string $otherSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mySession = Str::random(40);
        $this->otherSession = Str::random(40);

        $bus = Bus::create(['name' => 'Linea S', 'plate' => 'SSS111', 'seat_count' => 3, 'floors' => 1]);
        foreach ([1, 2, 3] as $number) {
            $this->seats[$number] = Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $number, 'is_active' => true, 'floor' => '1']);
        }
        $route = Route::create(['name' => 'Ruta S', 'bus_id' => $bus->id, 'is_active' => true]);
        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana S',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
        $this->trip = Trip::create(['route_id' => $route->id, 'schedule_id' => $schedule->id, 'bus_id' => $bus->id, 'trip_date' => '2026-12-01']);
    }

    public function test_sin_login_las_cuatro_operaciones_responden_401_y_no_tocan_reservas(): void
    {
        $this->reserve(2, $this->otherSession, now()->addMinute());
        $before = SeatReservation::query()->get(['seat_id', 'user_session_id', 'expires_at'])->toArray();

        foreach (['reserve', 'release', 'keep-alive', 'status'] as $operation) {
            $this->withCredentials()
                ->withCookie(config('session.cookie'), $this->mySession)
                ->postJson("/api/seat-reservations/{$operation}", [
                    'trip_id' => $this->trip->id,
                    'seat_ids' => [$this->seats[1]->id],
                    'trip_ids' => [$this->trip->id],
                    'session_id' => $this->otherSession,
                ])
                ->assertUnauthorized()
                ->assertJson(['message' => 'Unauthenticated.']);
        }

        $this->assertSame($before, SeatReservation::query()->get(['seat_id', 'user_session_id', 'expires_at'])->toArray());
    }

    public function test_reservar_funciona_para_un_vendedor(): void
    {
        $this->api(User::factory()->create(), 'reserve', ['trip_id' => $this->trip->id, 'seat_ids' => [$this->seats[1]->id]])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertReservedBy(1, $this->mySession);
    }

    public function test_reservar_funciona_para_un_cliente_del_panel_de_clientes(): void
    {
        $client = Clients::create(['dni' => '11223344', 'nombre' => 'Ana', 'apellido' => 'Gómez', 'can_buy' => true]);

        $this->api($client, 'reserve', ['trip_id' => $this->trip->id, 'seat_ids' => [$this->seats[1]->id]], guard: 'client')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertReservedBy(1, $this->mySession);
    }

    public function test_reservar_con_un_session_id_ajeno_reserva_a_nombre_de_la_sesion_del_pedido(): void
    {
        $this->api(User::factory()->create(), 'reserve', [
            'trip_id' => $this->trip->id,
            'seat_ids' => [$this->seats[1]->id],
            'session_id' => $this->otherSession,
        ])->assertOk();

        $this->assertReservedBy(1, $this->mySession);
        $this->assertDatabaseMissing('seat_reservations', ['user_session_id' => $this->otherSession]);
    }

    public function test_liberar_con_el_session_id_de_otro_no_toca_sus_reservas(): void
    {
        $this->reserve(1, $this->mySession);
        $this->reserve(2, $this->otherSession);

        $this->api(User::factory()->create(), 'release', ['session_id' => $this->otherSession])->assertOk();

        $this->assertDatabaseMissing('seat_reservations', ['user_session_id' => $this->mySession]);
        $this->assertReservedBy(2, $this->otherSession);
    }

    public function test_renovar_con_el_session_id_de_otro_no_renueva_sus_reservas(): void
    {
        $this->reserve(1, $this->mySession, now()->addMinute());
        $this->reserve(2, $this->otherSession, now()->addMinute());

        $this->api(User::factory()->create(), 'keep-alive', ['session_id' => $this->otherSession])
            ->assertOk()
            ->assertJson(['extended_reservations' => 1]);

        $mine = SeatReservation::query()->where('user_session_id', $this->mySession)->first();
        $theirs = SeatReservation::query()->where('user_session_id', $this->otherSession)->first();
        $this->assertTrue($mine->expires_at->greaterThan(now()->addMinutes(4)));
        $this->assertTrue($theirs->expires_at->lessThan(now()->addMinutes(2)));
    }

    public function test_el_estado_devuelve_solo_las_reservas_de_la_sesion_del_pedido(): void
    {
        $this->reserve(1, $this->mySession);
        $this->reserve(2, $this->otherSession);

        $this->api(User::factory()->create(), 'status', ['trip_id' => $this->trip->id, 'session_id' => $this->otherSession])
            ->assertOk()
            ->assertJson(['reserved_seats' => [$this->seats[1]->id], 'reservation_count' => 1]);
    }

    public function test_el_endpoint_de_limpieza_ya_no_existe_y_el_comando_sigue_limpiando(): void
    {
        $this->reserve(1, $this->otherSession, now()->subMinute());

        $this->api(User::factory()->create(), 'cleanup')->assertNotFound();
        $this->assertDatabaseCount('seat_reservations', 1);

        $this->artisan('app:cleanup-expired-reservations')->assertSuccessful();
        $this->assertDatabaseCount('seat_reservations', 0);
    }

    public function test_un_error_inesperado_al_reservar_responde_un_mensaje_generico(): void
    {
        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'insert into "seat_reservations"')) {
                throw new RuntimeException('detalle-interno-secreto');
            }
        });

        $response = $this->api(User::factory()->create(), 'reserve', ['trip_id' => $this->trip->id, 'seat_ids' => [$this->seats[1]->id]]);

        $response->assertStatus(500)
            ->assertJson(['success' => false, 'message' => 'No se pudo reservar los asientos. Intentá de nuevo.']);
        $this->assertStringNotContainsString('detalle-interno-secreto', $response->getContent());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function api(Authenticatable $user, string $operation, array $data = [], string $guard = 'web'): TestResponse
    {
        return $this->actingAs($user, $guard)
            ->withCredentials()
            ->withCookie(config('session.cookie'), $this->mySession)
            ->postJson("/api/seat-reservations/{$operation}", $data);
    }

    private function reserve(int $seatNumber, string $sessionId, $expiresAt = null): void
    {
        SeatReservation::insert([
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[$seatNumber]->id,
            'user_session_id' => $sessionId,
            'expires_at' => $expiresAt ?? now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertReservedBy(int $seatNumber, string $sessionId): void
    {
        $this->assertDatabaseHas('seat_reservations', [
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[$seatNumber]->id,
            'user_session_id' => $sessionId,
        ]);
    }
}
