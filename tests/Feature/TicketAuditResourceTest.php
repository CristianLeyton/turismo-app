<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketAudit\Classes\TicketAuditTable;
use App\Filament\Resources\TicketAudit\Schemas\TicketAuditDetailSchema;
use App\Filament\Resources\TicketAudit\TicketAuditResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Bus;
use App\Models\Clients;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\PaymentMethod;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDateChange;
use App\Models\TicketEdit;
use App\Models\Trip;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    /**
     * Registra una edición de datos del boleto (corrección de venta) como la
     * deja la página Editar boleto: una fila por campo cambiado.
     */
    private function editTicket(
        Ticket $ticket,
        string $field = 'first_name',
        string $label = 'Nombre',
        ?string $old = 'Juana',
        ?string $new = 'Juana María',
        ?string $reason = 'Error de carga en ventanilla',
        ?string $at = null,
    ): TicketEdit {
        $edit = TicketEdit::create([
            'ticket_id' => $ticket->id,
            'field' => $field,
            'field_label' => $label,
            'old_value' => $old,
            'new_value' => $new,
            'reason' => $reason,
            'user_id' => $this->admin->id,
        ]);

        if ($at) {
            $edit->forceFill(['created_at' => $at])->save();
        }

        return $edit->refresh();
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

    public function test_solo_muestra_boletos_eliminados_reprogramados_o_editados(): void
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

        // Boleto solo editado (ni eliminado ni reprogramado): debe aparecer.
        $edited = $this->createOneWayTicket('2026-10-04');
        $this->editTicket($edited);

        Livewire::test(TicketAuditResource::getPages()['index']->getPage())
            ->assertOk()
            ->assertSee('Rendida')
            ->assertSee('Eliminado')
            ->assertSee('Reprogramado')
            ->assertSee('Editado');

        $rows = TicketAuditResource::getEloquentQuery()->get();

        $this->assertCount(3, $rows, 'Deben listarse el eliminado, el reprogramado y el editado.');
        $this->assertTrue($rows->contains(fn (Ticket $t) => $t->id === $deleted->id));
        $this->assertTrue($rows->contains(fn (Ticket $t) => $t->id === $rescheduled->id));
        $this->assertTrue($rows->contains(fn (Ticket $t) => $t->id === $edited->id));
    }

    public function test_filtro_estado_editados(): void
    {
        $this->actingAs($this->admin);

        $deleted = $this->createOneWayTicket('2026-10-02');
        $deleted->delete();

        $edited = $this->createOneWayTicket('2026-10-04');
        $this->editTicket($edited);

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());

        $visibleIds = fn (): array => $component->instance()
            ->getFilteredTableQuery()
            ->pluck('tickets.id')
            ->all();

        // Sin filtro: los dos aparecen.
        $this->assertEqualsCanonicalizing([$deleted->id, $edited->id], $visibleIds());

        // 'edited' => solo el boleto con ediciones de datos.
        $component->set('tableFilters.audit_state.value', 'edited');
        $this->assertEqualsCanonicalizing([$edited->id], $visibleIds());
    }

    public function test_la_ultima_modificacion_considera_las_ediciones(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-03');
        $this->editTicket($ticket, at: '2026-10-10 12:00:00');

        $this->assertSame(
            'Editado',
            TicketAuditTable::ultimaModificacion($ticket->fresh()),
        );
        $this->assertSame(
            '10/10/2026',
            TicketAuditTable::ultimaActividad($ticket->fresh())?->format('d/m/Y'),
        );

        // Si después se reprograma, manda la acción más reciente.
        $oldTripId = $ticket->trip_id;
        $newTrip = $this->createAnotherTripFor($ticket, '2026-10-20');
        $ticket->update(['trip_id' => $newTrip->id]);
        TicketDateChange::create([
            'ticket_id' => $ticket->id,
            'leg' => TicketDateChange::LEG_OUTBOUND,
            'from_trip_id' => $oldTripId,
            'to_trip_id' => $newTrip->id,
            'user_id' => $this->admin->id,
        ])->forceFill(['created_at' => Carbon::parse('2026-10-12 09:00:00')])->save();

        $this->assertSame(
            'Reprogramado',
            TicketAuditTable::ultimaModificacion($ticket->fresh()),
        );
        $this->assertSame(
            '12/10/2026',
            TicketAuditTable::ultimaActividad($ticket->fresh())?->format('d/m/Y'),
        );

        // Y si se elimina, la eliminación es la última acción.
        $ticket->delete();

        $this->assertSame('Eliminado', TicketAuditTable::ultimaModificacion($ticket->fresh()));
        $this->assertNotNull(TicketAuditTable::ultimaActividad($ticket->fresh()));
    }

    public function test_la_tabla_se_ordena_por_la_actividad_mas_reciente(): void
    {
        $this->actingAs($this->admin);

        $editado = $this->createOneWayTicket('2026-10-03');
        $this->editTicket($editado, at: '2026-10-10 12:00:00');

        // Se elimina ahora: su última actividad (hoy) es más reciente que
        // la edición del otro boleto (10/10).
        $eliminado = $this->createOneWayTicket('2026-10-04');
        $eliminado->delete();

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());

        $ids = $component->instance()
            ->getTableRecords()
            ->pluck('id')
            ->all();

        $this->assertSame(
            [$eliminado->id, $editado->id],
            $ids,
            'El orden por defecto debe ser por la actividad más reciente.'
        );
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

    /** Reprogramación del boleto hecha por el usuario del panel `$userId`. */
    private function rescheduleTicket(Ticket $ticket, int $userId, string $date): void
    {
        $this->recordDateChange($ticket, $date, [
            'user_id' => $userId,
            'actor_type' => TicketDateChange::ACTOR_USER,
        ]);
    }

    /**
     * Reprogramación hecha por un cliente desde su panel (sin usuario), con el
     * cliente del padrón enlazado salvo que `$linked` sea false.
     */
    private function rescheduleTicketAsClient(Ticket $ticket, string $date, bool $linked = true): void
    {
        $client = $linked
            ? Clients::firstOrCreate(
                ['dni' => $ticket->passenger->dni],
                ['nombre' => 'Ana', 'apellido' => 'García', 'password' => 'clave-prueba', 'must_change_password' => false],
            )
            : null;

        $this->recordDateChange($ticket, $date, [
            'user_id' => null,
            'actor_type' => TicketDateChange::ACTOR_CLIENT,
            'client_id' => $client?->id,
        ]);
    }

    /** @param  array<string, mixed>  $actor */
    private function recordDateChange(Ticket $ticket, string $date, array $actor): void
    {
        $fromTripId = $ticket->trip_id;
        $ticket->update(['trip_id' => $this->createAnotherTripFor($ticket, $date)->id]);

        TicketDateChange::create([
            'ticket_id' => $ticket->id,
            'leg' => TicketDateChange::LEG_OUTBOUND,
            'from_trip_id' => $fromTripId,
            'to_trip_id' => $ticket->trip_id,
        ] + $actor);
    }

    public function test_un_cambio_de_fecha_hecho_por_un_cliente_aparece_en_el_historial(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-02');
        $this->rescheduleTicketAsClient($ticket, '2026-10-06');

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());

        $this->assertSame(
            [$ticket->id],
            $component->instance()->getFilteredTableQuery()->pluck('tickets.id')->all(),
        );
        $this->assertSame('Reprogramado', TicketAuditDetailSchema::estado($ticket->fresh()->load('dateChanges')));
    }

    public function test_filtro_realizado_por_separa_clientes_y_vendedores(): void
    {
        $this->actingAs($this->admin);
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $porCliente = $this->createOneWayTicket('2026-10-02');
        $this->rescheduleTicketAsClient($porCliente, '2026-10-06');

        $porVendedor = $this->createOneWayTicket('2026-10-03');
        $this->rescheduleTicket($porVendedor, $this->admin->id, '2026-10-07');

        $editado = $this->createOneWayTicket('2026-10-04');
        $this->editTicket($editado);

        $eliminado = $this->createOneWayTicket('2026-10-05');
        $eliminado->delete();

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());

        $visibleIds = fn (): array => $component->instance()
            ->getFilteredTableQuery()
            ->pluck('tickets.id')
            ->all();

        // Sin elegir nada: todo el historial.
        $this->assertEqualsCanonicalizing(
            [$porCliente->id, $porVendedor->id, $editado->id, $eliminado->id],
            $visibleIds(),
        );

        // "Clientes": sólo lo que asignaron ellos desde su panel.
        $component->set('tableFilters.actor.value', 'clients');
        $this->assertSame([$porCliente->id], $visibleIds());

        // "Vendedores": los cambios del personal, de cualquier rol.
        $component->set('tableFilters.actor.value', 'sellers');
        $this->assertEqualsCanonicalizing(
            [$porVendedor->id, $editado->id, $eliminado->id],
            $visibleIds(),
        );

        // Volver a "Todos".
        $component->set('tableFilters.actor.value', null);
        $this->assertCount(4, $visibleIds());
    }

    public function test_el_filtro_realizado_por_cuenta_vendedores_de_cualquier_rol(): void
    {
        $this->actingAs($this->admin);
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $vendedor = User::factory()->vendedor()->create();

        $porVendedor = $this->createOneWayTicket('2026-10-03');
        $this->rescheduleTicket($porVendedor, $vendedor->id, '2026-10-07');

        $porAdmin = $this->createOneWayTicket('2026-10-04');
        $this->rescheduleTicket($porAdmin, $this->admin->id, '2026-10-08');

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage())
            ->set('tableFilters.actor.value', 'sellers');

        $this->assertEqualsCanonicalizing(
            [$porVendedor->id, $porAdmin->id],
            $component->instance()->getFilteredTableQuery()->pluck('tickets.id')->all(),
        );
    }

    public function test_un_boleto_cambiado_por_cliente_y_por_vendedor_sale_en_los_dos_filtros(): void
    {
        $this->actingAs($this->admin);
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $ticket = $this->createOneWayTicket('2026-10-02');
        $this->rescheduleTicketAsClient($ticket, '2026-10-06');
        $this->rescheduleTicket($ticket->fresh(), $this->admin->id, '2026-10-09');

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());

        foreach (['clients', 'sellers'] as $actor) {
            $component->set('tableFilters.actor.value', $actor);

            $this->assertSame(
                [$ticket->id],
                $component->instance()->getFilteredTableQuery()->pluck('tickets.id')->all(),
                "Filtro {$actor}",
            );
        }
    }

    public function test_un_usuario_borrado_del_todo_cuenta_como_vendedor_y_no_como_cliente(): void
    {
        $this->actingAs($this->admin);
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $vendedor = User::factory()->vendedor()->create();
        $ticket = $this->createOneWayTicket('2026-10-02');
        $this->rescheduleTicket($ticket, $vendedor->id, '2026-10-06');

        $vendedor->forceDelete();

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());
        $visibleIds = fn (): array => $component->instance()->getFilteredTableQuery()->pluck('tickets.id')->all();

        $component->set('tableFilters.actor.value', 'clients');
        $this->assertSame([], $visibleIds());

        $component->set('tableFilters.actor.value', 'sellers');
        $this->assertSame([$ticket->id], $visibleIds());
    }

    public function test_el_filtro_realizado_por_muestra_su_indicador(): void
    {
        $this->actingAs($this->admin);
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage())
            ->set('tableFilters.actor.value', 'sellers');

        $labels = array_map(
            fn ($indicator) => $indicator->getLabel(),
            $component->instance()->getTable()->getFilter('actor')->getIndicators(),
        );

        $this->assertSame(['Realizado por: Vendedores'], $labels);
    }

    public function test_el_filtro_realizado_por_solo_esta_con_el_panel_de_clientes_activo(): void
    {
        $this->actingAs($this->admin);
        $page = TicketAuditResource::getPages()['index']->getPage();

        // Sin configurar: apagado.
        Livewire::test($page)->assertTableFilterHidden('actor');

        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '0');
        Livewire::test($page)->assertTableFilterHidden('actor');

        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
        Livewire::test($page)->assertTableFilterVisible('actor');
    }

    public function test_un_valor_guardado_con_el_panel_apagado_no_filtra_ni_se_anuncia(): void
    {
        $this->actingAs($this->admin);
        $page = TicketAuditResource::getPages()['index']->getPage();

        $porCliente = $this->createOneWayTicket('2026-10-02');
        $this->rescheduleTicketAsClient($porCliente, '2026-10-06');
        $porVendedor = $this->createOneWayTicket('2026-10-03');
        $this->rescheduleTicket($porVendedor, $this->admin->id, '2026-10-07');

        // Elige "Clientes" con el panel encendido: el filtro queda guardado en la sesión.
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
        $encendido = Livewire::test($page)->set('tableFilters.actor.value', 'clients');
        $this->assertSame([$porCliente->id], $encendido->instance()->getFilteredTableQuery()->pluck('tickets.id')->all());

        // Se apaga el panel: vuelve al Historial y ve todo, sin ese filtro.
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '0');
        $apagado = Livewire::test($page);

        $this->assertEqualsCanonicalizing(
            [$porCliente->id, $porVendedor->id],
            $apagado->instance()->getFilteredTableQuery()->pluck('tickets.id')->all(),
        );
        $this->assertNull($apagado->instance()->getTable()->getFilter('actor'), 'Con el panel apagado el filtro no existe, ni su indicador.');

        // Al encenderlo de nuevo, vuelve a filtrar.
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
        $this->assertSame(
            [$porCliente->id],
            Livewire::test($page)->set('tableFilters.actor.value', 'clients')->instance()->getFilteredTableQuery()->pluck('tickets.id')->all(),
        );
    }

    public function test_el_detalle_dice_cliente_con_su_nombre_cuando_lo_hizo_un_cliente(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-02');
        $this->rescheduleTicketAsClient($ticket, '2026-10-06');

        $html = $this->mountedViewHtml($ticket);

        $this->assertStringContainsString('Realizado por:', $html);
        $this->assertStringContainsString('Cliente Ana García', $html);
        $this->assertStringNotContainsString('Usuario eliminado del sistema', $html);
    }

    public function test_el_detalle_dice_solo_cliente_si_no_se_lo_puede_identificar(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-02');
        $this->rescheduleTicketAsClient($ticket, '2026-10-06', linked: false);

        $html = $this->mountedViewHtml($ticket);

        $this->assertStringContainsString('Cliente', $html);
        $this->assertStringNotContainsString('Cliente Ana', $html);
        $this->assertStringNotContainsString('Usuario eliminado del sistema', $html);
    }

    public function test_el_detalle_muestra_el_nombre_de_un_cliente_dado_de_baja(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-02');
        $this->rescheduleTicketAsClient($ticket, '2026-10-06');
        Clients::query()->where('dni', $ticket->passenger->dni)->firstOrFail()->delete();

        $this->assertStringContainsString('Cliente Ana García', $this->mountedViewHtml($ticket));
    }

    public function test_el_detalle_dice_usuario_eliminado_solo_si_lo_hizo_un_usuario_que_ya_no_existe(): void
    {
        $this->actingAs($this->admin);

        $vendedor = User::factory()->vendedor()->create(['name' => 'Carolina Pérez', 'username' => 'carolina']);
        $ticket = $this->createOneWayTicket('2026-10-02');
        $this->rescheduleTicket($ticket, $vendedor->id, '2026-10-06');

        $this->assertStringContainsString('Carolina Pérez (carolina)', $this->mountedViewHtml($ticket));

        $vendedor->forceDelete();

        $html = $this->mountedViewHtml($ticket->fresh());
        $this->assertStringContainsString('Usuario eliminado del sistema', $html);
        $this->assertStringNotContainsString('Cliente', explode('Realizado por:', $html)[1] ?? '');
    }

    public function test_el_filtro_realizado_por_se_llama_asi_y_ofrece_clientes_y_vendedores(): void
    {
        $this->actingAs($this->admin);
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $filter = Livewire::test(TicketAuditResource::getPages()['index']->getPage())
            ->instance()
            ->getTable()
            ->getFilter('actor');

        $this->assertSame('Realizado por', $filter->getLabel());
        $this->assertSame(['clients' => 'Clientes', 'sellers' => 'Vendedores'], $filter->getOptions());
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

    public function test_la_vista_de_un_boleto_editado_muestra_el_historial_de_ediciones(): void
    {
        $this->actingAs($this->admin);

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        PaymentMethod::create(['code' => 'transferencia', 'label' => 'Transferencia', 'is_active' => true]);
        PaymentMethod::clearCache();

        $ticket = $this->createOneWayTicket('2026-10-03');

        // Una misma edición: dos campos, mismo autor, momento y motivo.
        $this->editTicket($ticket, 'price', 'Precio', '100', '150', 'Se cobró de menos', '2026-10-10 12:00:00');
        $this->editTicket($ticket, 'payment_method', 'Método de pago', 'efectivo', 'transferencia', 'Se cobró de menos', '2026-10-10 12:00:00');

        $response = $this->get(TicketAuditResource::getUrl('view', ['record' => $ticket->id]));

        $response->assertOk();
        $response->assertSee('Editado');
        $response->assertSee('Historial de ediciones');
        $response->assertSee('2 campos corregidos en 1 edición.');
        $response->assertSee('Realizado por:');
        $response->assertSee('Admin (admin-audit)');
        $response->assertSee('Se cobró de menos');
        $response->assertSee('Antes');
        $response->assertSee('Después');
        $response->assertSee('$100');
        $response->assertSee('$150');
        $response->assertSee('Efectivo');
        $response->assertSee('Transferencia');
        $response->assertSee('10/10/2026 12:00');
    }

    public function test_el_modal_ver_de_un_boleto_editado_incluye_las_ediciones(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket('2026-10-03');
        $this->editTicket($ticket, 'price', 'Precio', '100', '150', 'Se cobró de menos', '2026-10-10 12:00:00');

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());
        $component->mountTableAction('ver', $ticket->id);

        $mounted = $component->instance()->mountedActions;
        $this->assertNotEmpty($mounted, 'La acción "ver" debe quedar montada.');
        $this->assertSame('ver', $mounted[0]['name']);

        $html = $this->mountedActionHtml($component->instance());

        $this->assertStringContainsString('Historial de ediciones', $html);
        $this->assertStringContainsString('Realizado por:', $html);
        $this->assertStringContainsString('Se cobró de menos', $html);
        $this->assertStringContainsString('Antes', $html);
        $this->assertStringContainsString('Después', $html);
        $this->assertStringContainsString('$150', $html, 'El precio nuevo se muestra con formato de moneda.');
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

        $this->assertStringContainsString('Boleto eliminado', $html);
        $this->assertMatchesRegularExpression('/Eliminado por<\/div> <div[^>]*> Admin <\/div> <div[^>]*>admin-audit<\/div>/', $html, 'Debe identificar quién eliminó el boleto.');
        $this->assertStringContainsString('no pueden restaurarse ni editarse', $html);

        // La eliminación va antes que los datos del boleto, para que no pase desapercibida.
        $this->assertLessThan(strpos($html, 'Salida actual'), strpos($html, 'Boleto eliminado'));
    }

    public function test_el_modal_muestra_el_colectivo_una_sola_vez(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket();
        $this->editTicket($ticket);

        $this->assertSame(1, substr_count($this->mountedViewHtml($ticket), 'Colectivo Linea P'));
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

        // Se compara el texto como lo ve el usuario: el navegador colapsa los
        // espacios y saltos de línea, así que el formato del Blade no importa.
        return (string) preg_replace('/\s+/', ' ', $schema->toHtml());
    }

    private function mountedViewHtml(Ticket $ticket): string
    {
        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());
        $component->mountTableAction('ver', $ticket->id);

        return $this->mountedActionHtml($component->instance());
    }

    public function test_la_accion_ver_tiene_menu_para_celular_y_boton_de_escritorio(): void
    {
        $this->actingAs($this->admin);

        $recordActions = collect(
            Livewire::test(TicketAuditResource::getPages()['index']->getPage())
                ->instance()
                ->getTable()
                ->getRecordActions(),
        );

        $mobileMenu = $recordActions->first(fn ($action): bool => $action instanceof ActionGroup
            && str_contains($action->getExtraAttributes()['class'] ?? '', 'md:hidden'));

        $this->assertNotNull($mobileMenu, 'Falta el menú de tres puntitos para celular.');
        $this->assertSame(['ver'], collect($mobileMenu->getActions())->map(fn ($action): string => $action->getName())->values()->all());
        $this->assertSame('Ver detalles', collect($mobileMenu->getActions())->first()->getLabel());

        $desktop = $recordActions->first(fn ($action): bool => ! $action instanceof ActionGroup
            && str_contains($action->getExtraAttributes()['class'] ?? '', 'hidden md:inline-flex'));

        $this->assertNotNull($desktop, 'Falta el botón de escritorio.');
        $this->assertSame('Ver detalles', $desktop->getExtraAttributes()['title'] ?? null);
    }

    public function test_el_modal_de_un_boleto_sin_fecha_muestra_fecha_a_confirmar(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket();
        $ticket->update(['trip_id' => null, 'seat_id' => null]);
        $this->editTicket($ticket);

        $html = $this->mountedViewHtml($ticket);

        $this->assertStringContainsString('Salida actual:</strong> <span class="font-semibold text-amber-600 dark:text-amber-400">Fecha y horario a confirmar</span>', $html);
    }

    public function test_el_modal_de_un_tramo_de_vuelta_aclara_que_el_precio_es_el_de_la_ida(): void
    {
        $this->actingAs($this->admin);

        $ida = $this->createOneWayTicket();
        $ida->update(['is_round_trip' => true, 'price' => 15000]);

        $vuelta = $ida->replicate(['share_token', 'active_seat_guard']);
        $vuelta->fill([
            'is_return_leg' => true,
            'price' => 0,
            'origin_location_id' => $ida->destination_location_id,
            'destination_location_id' => $ida->origin_location_id,
            'trip_id' => null,
            'seat_id' => null,
        ])->save();
        $this->editTicket($vuelta);

        $html = $this->mountedViewHtml($vuelta);

        $this->assertStringContainsString('$15.000', $html);
        $this->assertStringContainsString('Precio del boleto de ida', $html);

        $this->assertStringNotContainsString('Precio del boleto de ida', $this->mountedViewHtml(tap($ida, fn (Ticket $t) => $this->editTicket($t))));
    }

    public function test_la_tabla_es_compacta_sin_columnas_de_detalle(): void
    {
        $this->actingAs($this->admin);

        $this->createOneWayTicket();

        $component = Livewire::test(TicketAuditResource::getPages()['index']->getPage());

        $columns = array_keys($component->instance()->getTable()->getColumns());

        $this->assertEqualsCanonicalizing(
            ['id', 'passenger.last_name', 'passenger.dni', 'estado', 'ultima_actividad'],
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
