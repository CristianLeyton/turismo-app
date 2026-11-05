<?php

namespace Tests\Feature;

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
use App\Support\SeatConflict;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Un solo boleto vigente por viaje y asiento (índice único sobre la columna
 * generada active_seat_guard) y su detección independiente del motor.
 */
class ActiveSeatConstraintTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private Seat $seat;

    private Sale $sale;

    private Passenger $passenger;

    private Location $origin;

    private Location $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->vendedor()->create());

        $this->origin = Location::create(['name' => 'Orán U', 'is_active' => true]);
        $this->destination = Location::create(['name' => 'Salta U', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea U', 'plate' => 'UUU111', 'seat_count' => 2, 'floors' => 1]);
        $this->seat = Seat::create(['bus_id' => $bus->id, 'seat_number' => '1', 'is_active' => true, 'floor' => '1']);
        $route = Route::create(['name' => 'Ruta U', 'bus_id' => $bus->id, 'is_active' => true]);
        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana U',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
        $this->trip = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-05',
        ]);
        $this->sale = Sale::create(['user_id' => auth()->id(), 'sale_date' => now(), 'total_amount' => 100]);
        $this->passenger = Passenger::create([
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'dni' => '22333444',
            'passenger_type' => 'adult',
        ]);
    }

    public function test_dos_boletos_vigentes_en_el_mismo_asiento_y_viaje_se_rechazan(): void
    {
        $this->createTicket();

        try {
            $this->createTicket();
            $this->fail('La base debió rechazar el segundo boleto vigente.');
        } catch (QueryException $e) {
            $this->assertTrue(SeatConflict::isActiveSeatViolation($e));
        }

        $this->assertSame(1, Ticket::count());
    }

    public function test_un_boleto_dado_de_baja_no_ocupa_el_asiento(): void
    {
        // Vendido, dado de baja y revendido dos veces: siempre un solo vigente.
        $this->createTicket()->delete();
        $this->createTicket()->delete();
        $this->createTicket();

        $this->assertSame(1, Ticket::count());
        $this->assertSame(3, Ticket::withTrashed()->count());
    }

    public function test_varios_boletos_sin_fecha_no_participan(): void
    {
        $this->createTicket(['trip_id' => null, 'seat_id' => null]);
        $this->createTicket(['trip_id' => null, 'seat_id' => null]);

        $this->assertSame(2, Ticket::count());
    }

    public function test_detecta_el_mensaje_de_mysql(): void
    {
        $exception = new QueryException(
            'mysql',
            'insert into `tickets` ...',
            [],
            new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '709-304-1' for key 'tickets.tickets_active_trip_seat_unique'", 23000),
        );

        $this->assertTrue(SeatConflict::isActiveSeatViolation($exception));
    }

    public function test_no_confunde_otras_violaciones_de_integridad(): void
    {
        $exception = new QueryException(
            'mysql',
            'insert into `sales` ...',
            [],
            new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'abc' for key 'sales.sales_share_token_unique'", 23000),
        );

        $this->assertFalse(SeatConflict::isActiveSeatViolation($exception));
    }

    public function test_la_migracion_se_detiene_si_hay_duplicados_vigentes_y_no_cambia_la_tabla(): void
    {
        $migration = require database_path('migrations/2026_11_02_194728_add_active_seat_guard_to_tickets_table.php');

        // Volver al esquema anterior (índice con deleted_at, que admite duplicados vigentes).
        $migration->down();
        $this->assertFalse(Schema::hasColumn('tickets', 'active_seat_guard'));

        $first = $this->createTicket();
        $second = $this->createTicket();

        try {
            $migration->up();
            $this->fail('La migración debió detenerse por los duplicados.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("viaje {$this->trip->id}", $e->getMessage());
            $this->assertStringContainsString("seat_id) {$this->seat->id}", $e->getMessage());
            $this->assertStringContainsString("boletos {$first->id}, {$second->id}", $e->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('tickets', 'active_seat_guard'), 'La tabla no debió modificarse.');
        $this->assertSame(2, DB::table('tickets')->whereNull('deleted_at')->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTicket(array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'sale_id' => $this->sale->id,
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seat->id,
            'passenger_id' => $this->passenger->id,
            'is_round_trip' => false,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'price' => 100,
        ], $overrides));
    }
}
