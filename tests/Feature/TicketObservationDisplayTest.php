<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\MyTickets;
use App\Filament\Resources\TicketAudit\TicketAuditResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Bus;
use App\Models\Clients;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketEdit;
use App\Models\Trip;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dónde se ve la observación interna del boleto: en el detalle del boleto y
 * en la auditoría sí; en el PDF del pasajero, el link público y el panel de
 * clientes nunca.
 */
class TicketObservationDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const OBSERVATION = 'Paga el saldo al subir';

    private User $admin;

    protected function tearDown(): void
    {
        // La caché de Setting es estática: sin esto el parámetro prendido
        // pasaría a otros tests del mismo proceso.
        Setting::flushCache();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Las observaciones arrancan apagadas: estos tests las prenden y
        // los casos con el parámetro apagado lo apagan explícitamente.
        Setting::flushCache();
        Setting::set(Setting::OBSERVACIONES_VENTA, '1');

        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));
        Setting::flushCache();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-obs@test.local',
            'username' => 'admin-obs',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
    }

    // ====================== Detalle del boleto ======================

    public function test_el_detalle_del_boleto_muestra_la_observacion(): void
    {
        $ticket = $this->createTicket(self::OBSERVATION);

        $this->actingAs($this->admin)
            ->get(TicketResource::getUrl('view', ['record' => $ticket]))
            ->assertOk()
            ->assertSee('Detalles de venta')
            ->assertSee('Observación')
            ->assertSee(self::OBSERVATION);
    }

    public function test_el_detalle_de_un_boleto_sin_observacion_no_muestra_el_recuadro(): void
    {
        $ticket = $this->createTicket(null);

        $this->actingAs($this->admin)
            ->get(TicketResource::getUrl('view', ['record' => $ticket]))
            ->assertOk()
            ->assertDontSee('Observación');
    }

    // ====================== Auditoría ======================

    public function test_la_auditoria_muestra_la_observacion_del_boleto(): void
    {
        $ticket = $this->createTicket(self::OBSERVATION);
        $this->markAsEdited($ticket);

        $this->actingAs($this->admin)
            ->get(TicketAuditResource::getUrl('view', ['record' => $ticket->id]))
            ->assertOk()
            ->assertSee('Observación')
            ->assertSee(self::OBSERVATION);
    }

    public function test_la_auditoria_de_un_boleto_sin_observacion_no_muestra_la_seccion(): void
    {
        $ticket = $this->createTicket(null);
        $this->markAsEdited($ticket);

        $this->actingAs($this->admin)
            ->get(TicketAuditResource::getUrl('view', ['record' => $ticket->id]))
            ->assertOk()
            ->assertDontSee('Observación');
    }

    // ====================== Nunca para el pasajero ======================

    public function test_el_pdf_del_pasajero_no_incluye_la_observacion(): void
    {
        $ticket = $this->createTicket(self::OBSERVATION);

        $html = view('tickets.pdf.passenger-tickets', [
            'sale' => $ticket->sale,
            'tickets' => collect([$ticket]),
            'passenger' => $ticket->passenger,
            'hasChild' => false,
        ])->render();

        $this->assertStringContainsString($ticket->passenger->last_name, $html);
        $this->assertStringNotContainsString(self::OBSERVATION, $html);
        $this->assertStringNotContainsString('Observación', $html);
    }

    public function test_el_link_publico_del_boleto_no_incluye_la_observacion(): void
    {
        $ticket = $this->createTicket(self::OBSERVATION);

        $response = $this->get($ticket->shareUrl());

        $response->assertOk();
        $this->assertStringNotContainsString(self::OBSERVATION, (string) $response->getContent());
    }

    public function test_el_panel_de_clientes_no_muestra_la_observacion(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $ticket = $this->createTicket(self::OBSERVATION);
        $client = Clients::create([
            'dni' => $ticket->passenger->dni,
            'nombre' => 'Juana',
            'apellido' => 'Rendida',
            'password' => 'clave-prueba',
            'must_change_password' => false,
        ]);

        $this->actingAs($client, 'client');
        Filament::setCurrentPanel('clientes');

        // El boleto tiene fecha futura: está en la pestaña "Próximos".
        Livewire::test(MyTickets::class)
            ->assertSuccessful()
            ->set('activeTab', MyTickets::TAB_UPCOMING)
            ->assertCanSeeTableRecords([$ticket])
            ->assertDontSee(self::OBSERVATION);

        Setting::flushCache();
    }

    // ====================== Parámetro apagado ======================

    public function test_con_el_parametro_apagado_el_detalle_y_la_auditoria_no_muestran_la_observacion(): void
    {
        $ticket = $this->createTicket(self::OBSERVATION);
        $this->markAsEdited($ticket);
        Setting::set(Setting::OBSERVACIONES_VENTA, '0');

        $this->actingAs($this->admin)
            ->get(TicketResource::getUrl('view', ['record' => $ticket]))
            ->assertOk()
            ->assertDontSee(self::OBSERVATION);

        $this->actingAs($this->admin)
            ->get(TicketAuditResource::getUrl('view', ['record' => $ticket->id]))
            ->assertOk()
            ->assertDontSee(self::OBSERVATION);

        // Al volver a prenderlo, la observación guardada reaparece.
        Setting::set(Setting::OBSERVACIONES_VENTA, '1');

        $this->actingAs($this->admin)
            ->get(TicketResource::getUrl('view', ['record' => $ticket]))
            ->assertSee(self::OBSERVATION);
    }

    // ====================== Helpers ======================

    private function createTicket(?string $observation): Ticket
    {
        $oran = Location::create(['name' => 'Orán D', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta D', 'is_active' => true]);
        $bus = Bus::create(['name' => 'Linea D', 'plate' => 'DDD111', 'seat_count' => 4, 'floors' => 1]);
        $seat = Seat::create(['bus_id' => $bus->id, 'seat_number' => '1', 'is_active' => true, 'floor' => '1']);

        $route = Route::create(['name' => 'Orán - Salta D', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);
        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana D',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
        $trip = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-10',
        ]);

        $sale = Sale::create(['user_id' => $this->admin->id, 'sale_date' => now(), 'total_amount' => 100]);
        $passenger = Passenger::create([
            'first_name' => 'Juana',
            'last_name' => 'Rendida',
            'dni' => '45678912',
            'passenger_type' => 'adult',
        ]);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'seat_id' => $seat->id,
            'bus_id' => $bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
            'observation' => $observation,
        ]);
    }

    /** La auditoría lista solo boletos eliminados, reprogramados o editados. */
    private function markAsEdited(Ticket $ticket): void
    {
        TicketEdit::create([
            'ticket_id' => $ticket->id,
            'field' => 'first_name',
            'field_label' => 'Nombre',
            'old_value' => 'Juana',
            'new_value' => 'Juana María',
            'reason' => null,
            'user_id' => $this->admin->id,
        ]);
    }
}
