<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketAudit\TicketAuditResource;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Eliminación de boletos con MOTIVO obligatorio.
 *
 * El modal de `DeleteAction` (ViewTicket) exige un motivo, que se persiste en
 * `tickets.deletion_reason` antes del soft delete y se muestra en el detalle
 * de la auditoría de boletos (`ticket-audit-detail`) junto a la marca
 * "Eliminado".
 */
class TicketDeleteReasonTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-delete-reason@test.local',
            'username' => 'admin-delete-reason',
            'password' => bcrypt('secreto123'),
            'is_admin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    /**
     * Escenario base: ruta Orán → Salta con un viaje y un pasaje vendido.
     * Usa firstOrCreate para poder invocarse varias veces en el mismo test.
     */
    private function createOneWayTicket(string $date = '2026-10-01'): Ticket
    {
        $oran = Location::firstOrCreate(['name' => 'Orán'], ['is_active' => true]);
        $salta = Location::firstOrCreate(['name' => 'Salta'], ['is_active' => true]);

        $bus = Bus::firstOrCreate(
            ['plate' => 'PPP111'],
            ['name' => 'Linea P', 'seat_count' => 4, 'floors' => 1],
        );
        foreach ([1, 2, 3, 4] as $n) {
            Seat::firstOrCreate(
                ['bus_id' => $bus->id, 'seat_number' => (string) $n],
                ['is_active' => true, 'floor' => '1'],
            );
        }

        $route = Route::firstOrCreate(
            ['name' => 'Orán - Salta P'],
            ['bus_id' => $bus->id, 'is_active' => true],
        );
        RouteStop::firstOrCreate(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::firstOrCreate(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::firstOrCreate(
            ['route_id' => $route->id, 'name' => 'Mañana P'],
            ['departure_time' => '08:00', 'arrival_time' => '12:00', 'is_active' => true],
        );

        $trip = Trip::firstOrCreate(
            ['route_id' => $route->id, 'schedule_id' => $schedule->id, 'trip_date' => $date],
            ['bus_id' => $bus->id],
        );

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'sale_date' => now(),
            'total_amount' => 100,
        ]);

        $passenger = Passenger::firstOrCreate(
            ['dni' => '77766655'],
            ['first_name' => 'Juana', 'last_name' => 'Sosa', 'passenger_type' => 'adult'],
        );

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

    public function test_el_modal_de_eliminar_exige_el_motivo(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', [])
            ->assertHasActionErrors(['motivo']);

        $this->assertNotSoftDeleted($ticket);
    }

    public function test_con_motivo_se_elimina_y_el_motivo_queda_guardado(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', ['motivo' => 'Venta duplicada en el sistema'])
            ->assertHasNoActionErrors();

        $deleted = $ticket->fresh();

        $this->assertSoftDeleted($deleted);
        $this->assertSame('Venta duplicada en el sistema', $deleted->deletion_reason);
        $this->assertSame($this->admin->id, $deleted->deleted_by);
    }

    public function test_el_motivo_es_obligatorio_tambien_cuando_se_pide_password(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');

        $ticket = $this->createOneWayTicket();

        // Con la contraseña correcta pero sin motivo: no se elimina.
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', ['password' => 'secreto123'])
            ->assertHasActionErrors(['motivo']);

        $this->assertNotSoftDeleted($ticket);

        // Con los dos campos: se elimina y guarda el motivo.
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', ['password' => 'secreto123', 'motivo' => 'Pasajero canceló el viaje'])
            ->assertHasNoActionErrors();

        $deleted = $ticket->fresh();

        $this->assertSoftDeleted($deleted);
        $this->assertSame('Pasajero canceló el viaje', $deleted->deletion_reason);
    }

    public function test_el_detalle_del_historial_muestra_el_motivo_de_la_eliminacion(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-02');

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', ['motivo' => 'Doble carga de la venta 42'])
            ->assertHasNoActionErrors();

        $response = $this->get(TicketAuditResource::getUrl('view', ['record' => $ticket->id]));

        $response->assertOk();
        $response->assertSee('Eliminado');
        $response->assertSee('Motivo:');
        $response->assertSee('Doble carga de la venta 42');
    }

    public function test_un_boleto_eliminado_sin_motivo_historico_muestra_guion(): void
    {
        $this->actingAs($this->admin);

        // Borrado anterior a esta funcionalidad: sin motivo registrado.
        $ticket = $this->createOneWayTicket('2026-10-03');
        $ticket->deleted_by = $this->admin->id;
        $ticket->save();
        $ticket->delete();

        $response = $this->get(TicketAuditResource::getUrl('view', ['record' => $ticket->id]));

        $response->assertOk();
        $response->assertSee('Motivo:');
        $response->assertDontSee('Venta duplicada');
    }
}
