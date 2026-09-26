<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketAudit\TicketAuditResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\TicketDateChange;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketAuditResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-audit@test.local',
            'username' => 'admin-audit',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
    }

    /**
     * Escenario base: ruta Orán → Salta con un schedule, un viaje en la fecha
     * dada y un pasaje vendido a "Juana Rendida".
     *
     * Usa firstOrCreate para poder invocarse varias veces dentro del mismo
     * test (locations/buses/pasajeros tienen índices unique).
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
        RouteStop::firstOrCreate(
            ['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1],
        );
        RouteStop::firstOrCreate(
            ['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2],
        );

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
            ['dni' => '45678912'],
            ['first_name' => 'Juana', 'last_name' => 'Rendida', 'passenger_type' => 'adult'],
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

    private function deleteTicketAs(Ticket $ticket, User $user): void
    {
        $this->actingAs($user);
        $ticket->delete();
        $this->refreshApplicationContext();
    }

    public function test_no_admin_no_ve_el_recurso_ni_puede_acceder(): void
    {
        $vendedor = User::create([
            'name' => 'Vendedor',
            'email' => 'vendedor-audit@test.local',
            'username' => 'vendedor-audit',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $this->actingAs($vendedor);

        $this->assertFalse(TicketAuditResource::canViewAny());
        $this->assertFalse(TicketAuditResource::shouldRegisterNavigation());

        $response = $this->get(TicketAuditResource::getUrl('index'));

        $response->assertForbidden();
    }

    public function test_solo_muestra_boletos_eliminados_o_reprogramados(): void
    {
        $this->actingAs($this->admin);

        // Boleto activo, sin cambios: NO debe aparecer.
        $this->createOneWayTicket('2026-10-01');

        // Boleto eliminado: debe aparecer.
        $deleted = $this->createOneWayTicket('2026-10-02');
        $deleted->deleted_by = $this->admin->id;
        $deleted->save();
        $deleted->delete();

        // Boleto reprogramado (no eliminado): debe aparecer.
        $rescheduled = $this->createOneWayTicket('2026-10-03');
        $rescheduled->update(['trip_id' => $this->createAnotherTripFor($rescheduled)->id]);
        TicketDateChange::create([
            'ticket_id' => $rescheduled->id,
            'leg' => TicketDateChange::LEG_OUTBOUND,
            'from_trip_id' => $rescheduled->getOriginal('trip_id'),
            'to_trip_id' => $rescheduled->trip_id,
            'from_schedule_id' => $rescheduled->trip->schedule_id,
            'to_schedule_id' => $rescheduled->trip->schedule_id,
            'from_seat_id' => $rescheduled->seat_id,
            'to_seat_id' => $rescheduled->seat_id,
            'user_id' => $this->admin->id,
        ]);

        Livewire::test(TicketAuditResource::getPages()['index']->getPage())
            ->assertOk()
            ->assertSee('Rendida')
            ->assertSee('Eliminado')
            ->assertSee('Reprogramado');

        $rows = TicketAuditResource::getEloquentQuery()->get();

        $this->assertCount(2, $rows, 'Solo deben listarse el eliminado y el reprogramado.');
        $this->assertTrue($rows->contains(fn (Ticket $t) => $t->id === $deleted->id));
        $this->assertTrue($rows->contains(fn (Ticket $t) => $t->id === $rescheduled->id));
    }

    public function test_filtro_estado_eliminados(): void
    {
        $this->actingAs($this->admin);

        $deleted = $this->createOneWayTicket('2026-10-02');
        $deleted->delete();

        $rescheduled = $this->createOneWayTicket('2026-10-03');
        $rescheduled->update(['trip_id' => $this->createAnotherTripFor($rescheduled)->id]);
        TicketDateChange::create([
            'ticket_id' => $rescheduled->id,
            'leg' => TicketDateChange::LEG_OUTBOUND,
            'from_trip_id' => $rescheduled->getOriginal('trip_id'),
            'to_trip_id' => $rescheduled->trip_id,
            'user_id' => $this->admin->id,
        ]);

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());

        $visibleIds = fn (): array => $component->instance()
            ->getFilteredTableQuery()
            ->pluck('tickets.id')
            ->all();

        // Sin filtro: los dos aparecen.
        $this->assertEqualsCanonicalizing([$deleted->id, $rescheduled->id], $visibleIds());

        // 'deleted' => solo el boleto eliminado.
        $component->set('tableFilters.audit_state.value', 'deleted');
        $this->assertEqualsCanonicalizing([$deleted->id], $visibleIds());

        // 'rescheduled' => solo el reprogramado.
        $component->set('tableFilters.audit_state.value', 'rescheduled');
        $this->assertEqualsCanonicalizing([$rescheduled->id], $visibleIds());
    }

    public function test_la_vista_de_un_boleto_eliminado_muestra_quien_y_cuando_lo_borro(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-02');
        $ticket->deleted_by = $this->admin->id;
        $ticket->save();
        $ticket->delete();

        $response = $this->get(TicketAuditResource::getUrl('view', ['record' => $ticket->id]));

        $response->assertOk();
        $response->assertSee('Eliminado');
        $response->assertSee('Admin');
        $response->assertSee('no pueden restaurarse ni editarse');
        $response->assertSee('Este boleto nunca fue reprogramado.');
    }

    public function test_la_vista_de_un_boleto_reprogramado_muestra_el_historial(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-03');
        $oldTripId = $ticket->trip_id;

        $newTrip = $this->createAnotherTripFor($ticket, '2026-10-05');
        $ticket->update(['trip_id' => $newTrip->id]);

        TicketDateChange::create([
            'ticket_id' => $ticket->id,
            'leg' => TicketDateChange::LEG_OUTBOUND,
            'from_trip_id' => $oldTripId,
            'to_trip_id' => $newTrip->id,
            'from_schedule_id' => $ticket->trip->schedule_id,
            'to_schedule_id' => $newTrip->schedule_id,
            'from_seat_id' => $ticket->seat_id,
            'to_seat_id' => $ticket->seat_id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->get(TicketAuditResource::getUrl('view', ['record' => $ticket->id]));

        $response->assertOk();
        $response->assertSee('Historial de reprogramaciones');
        $response->assertSee('Ida');
        $response->assertSee('Admin');
        $response->assertSee('03/10/2026');   // desde (fecha vieja)
        $response->assertSee('05/10/2026');   // hacia (fecha nueva)
        $response->assertSee('Asiento 1');
    }

    public function test_los_boletos_eliminados_no_se_pueden_restaurar_ni_editar(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-02');
        $ticket->delete();
        $ticket->refresh();

        // Gate a nivel recurso (defensa principal).
        $this->assertFalse(TicketAuditResource::canEdit($ticket));
        $this->assertFalse(TicketAuditResource::canDelete($ticket));
        $this->assertFalse(TicketAuditResource::canRestore($ticket));
        $this->assertFalse(TicketAuditResource::canForceDelete($ticket));
        $this->assertFalse(TicketAuditResource::canCreate());

        // La tabla solo registra la acción de lectura "ver" (modal de detalle).
        $livewire = Livewire::test(TicketAuditResource::getPages()['index']->getPage());
        $table = $livewire->instance()->getTable();

        $this->assertContains(
            'ver',
            array_keys($table->getFlatActions()),
            'La tabla de auditoría debe registrar la acción de lectura "ver".'
        );

        $this->assertNotContains(
            'edit',
            array_keys($table->getFlatActions()),
            'La tabla de auditoría no debe registrar acciones de escritura.'
        );
        $this->assertNotContains('delete', array_keys($table->getFlatActions()));
        $this->assertNotContains('restore', array_keys($table->getFlatActions()));

        // Las páginas no registran acciones de header (sin Edit/Restore/Delete).
        $headerActions = (function (): array {
            $m = new \ReflectionMethod($this, 'getHeaderActions');

            return array_keys($m->invoke($this));
        })->call($livewire->instance());

        $this->assertSame(
            [],
            $headerActions,
            'La página de listado no debe registrar acciones.'
        );

        // No existe ruta de edición para este recurso.
        $this->assertFalse(TicketAuditResource::hasPage('edit'));
        $this->assertFalse(TicketAuditResource::hasPage('create'));
    }

    public function test_el_recurso_boletos_queda_dentro_del_cluster(): void
    {
        $this->actingAs($this->admin);

        $this->assertSame(
            \App\Filament\Clusters\Tickets\TicketsCluster::class,
            TicketResource::getCluster(),
        );
        $this->assertSame(
            \App\Filament\Clusters\Tickets\TicketsCluster::class,
            TicketAuditResource::getCluster(),
        );
        $this->assertTrue(TicketResource::shouldRegisterNavigation());
    }

    public function test_el_modal_ver_monta_el_detalle_con_autor_y_antes_despues(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-03');
        $oldTripId = $ticket->trip_id;

        $newTrip = $this->createAnotherTripFor($ticket, '2026-10-05');
        $ticket->update(['trip_id' => $newTrip->id]);

        TicketDateChange::create([
            'ticket_id' => $ticket->id,
            'leg' => TicketDateChange::LEG_OUTBOUND,
            'from_trip_id' => $oldTripId,
            'to_trip_id' => $newTrip->id,
            'from_schedule_id' => $ticket->trip->schedule_id,
            'to_schedule_id' => $newTrip->schedule_id,
            'from_seat_id' => $ticket->seat_id,
            'to_seat_id' => $ticket->seat_id,
            'user_id' => $this->admin->id,
        ]);

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());
        $component->mountTableAction('ver', $ticket->id);

        $mounted = $component->instance()->mountedActions;
        $this->assertNotEmpty($mounted, 'La acción "ver" debe quedar montada.');
        $this->assertSame('ver', $mounted[0]['name']);

        $html = $this->mountedActionHtml($component->instance());

        $this->assertStringContainsString('Historial de reprogramaciones', $html);
        $this->assertStringContainsString('Realizado por:', $html, 'El detalle debe mostrar quién hizo la reprogramación.');
        $this->assertStringContainsString('Admin (admin-audit)', $html, 'El detalle debe identificar al autor del cambio.');
        $this->assertStringContainsString('Antes', $html);
        $this->assertStringContainsString('Después', $html);
        $this->assertStringContainsString('03/10/2026', $html, 'Debe verse la fecha vieja.');
        $this->assertStringContainsString('05/10/2026', $html, 'Debe verse la fecha nueva.');
        $this->assertStringContainsString('Asiento 1', $html);
    }

    public function test_el_modal_ver_de_un_boleto_eliminado_incluye_la_eliminacion(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-02');
        $ticket->deleted_by = $this->admin->id;
        $ticket->save();
        $ticket->delete();

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());
        $component->mountTableAction('ver', $ticket->id);

        $mounted = $component->instance()->mountedActions;
        $this->assertNotEmpty($mounted, 'La acción "ver" debe quedar montada.');
        $this->assertSame('ver', $mounted[0]['name']);

        $html = $this->mountedActionHtml($component->instance());

        $this->assertStringContainsString('fue ELIMINADO', $html);
        $this->assertStringContainsString('por Admin (admin-audit)', $html, 'Debe identificar quién eliminó el boleto.');
        $this->assertStringContainsString('no pueden restaurarse ni editarse', $html);
    }

    /**
     * Renderiza el HTML del schema de la acción montada (el contenido del
     * modal "Ver"). Es la misma Blade que se renderiza en la página de vista.
     */
    private function mountedActionHtml(object $instance): string
    {
        $getSchema = new \ReflectionMethod($instance, 'getMountedActionSchema');
        $getSchema->setAccessible(true);

        $schema = $getSchema->invoke($instance);
        $this->assertNotNull($schema, 'La acción montada debe resolver un schema.');

        return $schema->toHtml();
    }

    public function test_la_tabla_es_compacta_sin_columnas_de_detalle(): void
    {
        $this->actingAs($this->admin);

        $this->createOneWayTicket();

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());

        $columns = array_keys($component->instance()->getTable()->getColumns());

        $this->assertEqualsCanonicalizing(
            ['id', 'passenger.last_name', 'passenger.dni', 'estado', 'last_change_at'],
            $columns,
            'La tabla debe ser compacta: el detalle va en el modal.'
        );
    }

    /**
     * Crea un segundo viaje (otra fecha) sobre la misma ruta/horario para
     * simular el destino de una reprogramación.
     */
    private function createAnotherTripFor(Ticket $ticket, string $date = '2026-10-05'): Trip
    {
        return Trip::create([
            'route_id' => $ticket->trip->route_id,
            'schedule_id' => $ticket->trip->schedule_id,
            'bus_id' => $ticket->trip->bus_id,
            'trip_date' => $date,
        ]);
    }
}
