<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Clients;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route as BusRoute;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Ticket;
use App\Models\TicketDateChange;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autor de cada reprogramación (usuario o cliente): migración con backfill y
 * modelo. El registro al reprogramar y el texto del detalle tienen sus tests
 * en ClientPanelTicketsTest y TicketAuditResourceTest.
 */
class TicketDateChangeActorTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_07_235335_add_actor_to_ticket_date_changes_table.php';

    /** Boleto de un pasajero con ese DNI, con su viaje y venta. */
    private function createTicketFor(string $dni, int $n = 1): Ticket
    {
        $origin = Location::firstOrCreate(['name' => 'Origen'], ['is_active' => true]);
        $destination = Location::firstOrCreate(['name' => 'Destino'], ['is_active' => true]);

        $bus = Bus::create(['name' => "Colectivo {$dni}-{$n}", 'plate' => 'AC'.abs(crc32($dni.$n)), 'seat_count' => 4, 'floors' => 1]);
        $route = BusRoute::create(['name' => "Ruta {$dni}-{$n}", 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $origin->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $destination->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana',
            'departure_time' => '08:00',
            'arrival_time' => '09:00',
            'is_active' => true,
        ]);

        $trip = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-10',
        ]);

        $passenger = Passenger::firstOrCreate(
            ['dni' => $dni],
            ['first_name' => 'Pasajero', 'last_name' => 'García', 'passenger_type' => 'adult'],
        );

        $sale = Sale::create([
            'user_id' => User::factory()->vendedor()->create()->id,
            'sale_date' => now(),
            'total_amount' => 15000,
        ]);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'bus_id' => $bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'price' => 15000,
        ]);
    }

    private function createClient(string $dni): Clients
    {
        return Clients::create([
            'dni' => $dni,
            'nombre' => 'Ana',
            'apellido' => 'García',
            'password' => 'clave-prueba',
            'must_change_password' => false,
        ]);
    }

    /** Fila de reprogramación armada como las anteriores al cambio: sin autor explícito. */
    private function legacyChange(Ticket $ticket, ?int $userId): TicketDateChange
    {
        return TicketDateChange::create([
            'ticket_id' => $ticket->id,
            'leg' => TicketDateChange::LEG_OUTBOUND,
            'from_trip_id' => null,
            'to_trip_id' => $ticket->trip_id,
            'user_id' => $userId,
        ]);
    }

    private function runBackfill(): void
    {
        (require base_path(self::MIGRATION))->backfill();
    }

    public function test_una_reprogramacion_sin_autor_explicito_queda_como_de_usuario(): void
    {
        $change = $this->legacyChange($this->createTicketFor('11223344'), User::factory()->vendedor()->create()->id);

        $this->assertSame(TicketDateChange::ACTOR_USER, $change->fresh()->actor_type);
        $this->assertNull($change->fresh()->client_id);
    }

    public function test_el_backfill_deja_como_de_usuario_las_que_tienen_usuario(): void
    {
        $change = $this->legacyChange($this->createTicketFor('11223344'), User::factory()->vendedor()->create()->id);
        $this->createClient('11223344');

        $this->runBackfill();

        $this->assertSame(TicketDateChange::ACTOR_USER, $change->fresh()->actor_type);
        $this->assertNull($change->fresh()->client_id, 'Un cambio de usuario no se enlaza a ningún cliente.');
    }

    public function test_el_backfill_marca_como_cliente_las_sin_usuario_y_las_enlaza_por_dni(): void
    {
        $client = $this->createClient('11223344');
        $change = $this->legacyChange($this->createTicketFor('11223344'), null);

        $this->runBackfill();

        $this->assertSame(TicketDateChange::ACTOR_CLIENT, $change->fresh()->actor_type);
        $this->assertSame($client->id, $change->fresh()->client_id);
    }

    public function test_el_backfill_sin_cliente_que_coincida_queda_como_cliente_sin_enlace(): void
    {
        $change = $this->legacyChange($this->createTicketFor('55667788'), null);

        $this->runBackfill();

        $this->assertSame(TicketDateChange::ACTOR_CLIENT, $change->fresh()->actor_type);
        $this->assertNull($change->fresh()->client_id);
    }

    public function test_el_backfill_enlaza_tambien_a_clientes_dados_de_baja(): void
    {
        $client = $this->createClient('11223344');
        $change = $this->legacyChange($this->createTicketFor('11223344'), null);
        $client->delete();

        $this->runBackfill();

        $this->assertSame($client->id, $change->fresh()->client_id);
    }

    public function test_el_backfill_puede_correrse_mas_de_una_vez(): void
    {
        $client = $this->createClient('11223344');
        $porCliente = $this->legacyChange($this->createTicketFor('11223344'), null);
        $porUsuario = $this->legacyChange($this->createTicketFor('99887766'), User::factory()->vendedor()->create()->id);

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame([TicketDateChange::ACTOR_CLIENT, $client->id], [$porCliente->fresh()->actor_type, $porCliente->fresh()->client_id]);
        $this->assertSame(TicketDateChange::ACTOR_USER, $porUsuario->fresh()->actor_type);
    }

    public function test_el_cambio_de_un_cliente_carga_su_cliente_aunque_este_dado_de_baja(): void
    {
        $client = $this->createClient('11223344');
        $change = TicketDateChange::create([
            'ticket_id' => $this->createTicketFor('11223344')->id,
            'leg' => TicketDateChange::LEG_OUTBOUND,
            'actor_type' => TicketDateChange::ACTOR_CLIENT,
            'client_id' => $client->id,
        ]);

        $client->delete();

        $this->assertTrue($change->fresh()->client->is($client));
        $this->assertTrue($change->fresh()->client->trashed());
    }

    public function test_al_borrar_del_todo_a_un_cliente_el_cambio_sigue_siendo_de_cliente(): void
    {
        $client = $this->createClient('11223344');
        $change = TicketDateChange::create([
            'ticket_id' => $this->createTicketFor('11223344')->id,
            'leg' => TicketDateChange::LEG_OUTBOUND,
            'actor_type' => TicketDateChange::ACTOR_CLIENT,
            'client_id' => $client->id,
        ]);

        $client->forceDelete();

        $this->assertSame(TicketDateChange::ACTOR_CLIENT, $change->fresh()->actor_type);
        $this->assertNull($change->fresh()->client_id);
    }

    public function test_al_borrar_del_todo_a_un_usuario_su_cambio_sigue_siendo_de_usuario(): void
    {
        $user = User::factory()->vendedor()->create();
        $change = TicketDateChange::create([
            'ticket_id' => $this->createTicketFor('11223344')->id,
            'leg' => TicketDateChange::LEG_OUTBOUND,
            'actor_type' => TicketDateChange::ACTOR_USER,
            'user_id' => $user->id,
        ]);

        $user->forceDelete();

        $this->assertSame(TicketDateChange::ACTOR_USER, $change->fresh()->actor_type);
        $this->assertNull($change->fresh()->user_id);
    }
}
