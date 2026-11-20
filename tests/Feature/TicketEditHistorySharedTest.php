<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketAudit\Pages\ListTicketAudit;
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
use App\Models\TicketEdit;
use App\Models\Trip;
use App\Models\User;
use App\Support\Permissions;
use App\Support\TicketEditHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Historial de ediciones compartido entre la ida y la vuelta de un pasajero
 * (spec ticket-edit-history): los cambios de datos del pasajero hechos desde
 * un boleto se ven también en su boleto hermano de la misma venta.
 */
class TicketEditHistorySharedTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Ticket $outbound;

    private Ticket $return;

    private Ticket $otherSale;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 17:45:05');

        $this->editor = User::factory()->vendedor()->create(['name' => 'Julian']);

        $oran = Location::create(['name' => 'Orán H', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta H', 'is_active' => true]);
        $bus = Bus::create(['name' => 'Coche H', 'plate' => 'HHH111', 'seat_count' => 4, 'floors' => 1]);
        $seats = collect(['1', '2', '3'])->map(fn (string $number): Seat => Seat::create(['bus_id' => $bus->id, 'seat_number' => $number, 'is_active' => true, 'floor' => '1']));

        $route = Route::create(['name' => 'Orán - Salta H', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);
        $schedule = Schedule::create(['route_id' => $route->id, 'name' => 'Mañana H', 'departure_time' => '08:00', 'arrival_time' => '12:00', 'is_active' => true]);

        $outboundTrip = Trip::create(['route_id' => $route->id, 'schedule_id' => $schedule->id, 'bus_id' => $bus->id, 'trip_date' => '2030-11-05']);
        $returnTrip = Trip::create(['route_id' => $route->id, 'schedule_id' => $schedule->id, 'bus_id' => $bus->id, 'trip_date' => '2030-11-07']);

        $sale = Sale::create(['user_id' => $this->editor->id, 'sale_date' => now(), 'total_amount' => 35000]);
        $passenger = Passenger::create(['first_name' => 'victor haguin', 'last_name' => 'abdala', 'dni' => '92706221', 'passenger_type' => 'adult']);

        $this->outbound = Ticket::create([
            'sale_id' => $sale->id, 'trip_id' => $outboundTrip->id, 'seat_id' => $seats[0]->id, 'passenger_id' => $passenger->id,
            'is_round_trip' => true, 'is_return_leg' => false,
            'origin_location_id' => $oran->id, 'destination_location_id' => $salta->id, 'price' => 35000,
        ]);
        $this->return = Ticket::create([
            'sale_id' => $sale->id, 'trip_id' => $returnTrip->id, 'seat_id' => $seats[1]->id, 'passenger_id' => $passenger->id,
            'is_round_trip' => true, 'is_return_leg' => true,
            'origin_location_id' => $salta->id, 'destination_location_id' => $oran->id, 'price' => 0,
        ]);

        // Otra venta a una persona con el mismo DNI: no comparte historial.
        $otherSale = Sale::create(['user_id' => $this->editor->id, 'sale_date' => now(), 'total_amount' => 35000]);
        $this->otherSale = Ticket::create([
            'sale_id' => $otherSale->id, 'trip_id' => $outboundTrip->id, 'seat_id' => $seats[2]->id,
            'passenger_id' => Passenger::create(['first_name' => 'victor haguin', 'last_name' => 'abdala', 'dni' => '92706221', 'passenger_type' => 'adult'])->id,
            'is_round_trip' => false, 'is_return_leg' => false,
            'origin_location_id' => $oran->id, 'destination_location_id' => $salta->id, 'price' => 35000,
        ]);

        // El vendedor completa los datos reales desde la ida y corrige el precio.
        $this->edit($this->outbound, 'first_name', 'victor haguin', 'maximo');
        $this->edit($this->outbound, 'last_name', 'abdala', 'abregu');
        $this->edit($this->outbound, 'dni', '92706221', '45468804');
        $this->edit($this->outbound, 'price', '3500', '35000');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ====================== Ediciones visibles ======================

    public function test_la_ida_ve_todas_sus_ediciones(): void
    {
        $this->assertEqualsCanonicalizing(['first_name', 'last_name', 'dni', 'price'], $this->fields($this->outbound));
    }

    public function test_la_vuelta_ve_los_datos_del_pasajero_editados_desde_la_ida(): void
    {
        $this->assertEqualsCanonicalizing(['first_name', 'last_name', 'dni'], $this->fields($this->return));
    }

    public function test_la_vuelta_no_ve_el_precio_de_la_ida(): void
    {
        $this->assertNotContains('price', $this->fields($this->return));
    }

    public function test_otra_venta_del_mismo_dni_no_comparte_historial(): void
    {
        $this->assertSame([], $this->fields($this->otherSale));
    }

    public function test_una_ida_eliminada_sigue_compartiendo_sus_ediciones(): void
    {
        $this->outbound->delete();

        $this->assertEqualsCanonicalizing(['first_name', 'last_name', 'dni'], $this->fields($this->return->fresh()));
    }

    public function test_el_menor_editado_desde_la_vuelta_se_ve_en_la_ida(): void
    {
        $this->edit($this->return, 'child_dni', '50111222', '50111223');

        $this->assertContains('child_dni', $this->fields($this->outbound->fresh()));
    }

    // ====================== Origen de cada edición ======================

    public function test_las_ediciones_compartidas_indican_desde_que_boleto_se_hicieron(): void
    {
        $groups = TicketEditHistory::groups($this->return->visibleEdits(), $this->return->id);

        $this->assertCount(1, $groups);
        $this->assertSame($this->outbound->id, $groups[0]['from_ticket_id']);
    }

    public function test_las_ediciones_propias_no_indican_origen(): void
    {
        $groups = TicketEditHistory::groups($this->outbound->visibleEdits(), $this->outbound->id);

        $this->assertCount(1, $groups);
        $this->assertNull($groups[0]['from_ticket_id']);
    }

    public function test_una_edicion_propia_y_una_compartida_del_mismo_momento_van_separadas(): void
    {
        $this->edit($this->return, 'phone_number', null, '3815550001');

        $groups = TicketEditHistory::groups($this->return->fresh()->visibleEdits(), $this->return->id);

        $this->assertCount(2, $groups);
        $this->assertEqualsCanonicalizing([null, $this->outbound->id], $groups->pluck('from_ticket_id')->all());
    }

    // ====================== Historial en pantalla ======================

    public function test_el_detalle_de_auditoria_de_la_vuelta_muestra_la_edicion_hecha_desde_la_ida(): void
    {
        $html = view('filament.resources.ticket-audit.ticket-audit-detail', ['ticket' => $this->return])->render();

        $this->assertStringContainsString('data-shared-edit', $html);
        $this->assertMatchesRegularExpression('/Editado desde el boleto\s*<a[^>]*>N° '.$this->outbound->id.'<\/a>/u', $html);
        $this->assertStringContainsString('92706221', $html);
        $this->assertStringContainsString('45468804', $html);
        $this->assertStringNotContainsString('$3.500', $html, 'El precio de la ida no es del pasajero.');
    }

    public function test_el_historial_de_la_ida_no_muestra_origen(): void
    {
        $html = view('filament.resources.ticket-audit.ticket-audit-detail', ['ticket' => $this->outbound])->render();

        $this->assertStringContainsString('45468804', $html);
        $this->assertStringNotContainsString('Editado desde el boleto', $html);
    }

    public function test_ver_boleto_de_la_vuelta_muestra_el_historial_aunque_no_tenga_ediciones_propias(): void
    {
        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(TicketResource::getUrl('view', ['record' => $this->return]))
            ->assertOk()
            ->assertSee('Historial de ediciones')
            ->assertSee('Editado desde el boleto');
    }

    // ====================== Auditoría ======================

    public function test_la_vuelta_editada_desde_la_ida_aparece_en_la_auditoria(): void
    {
        $ids = TicketAuditResource::getEloquentQuery()->pluck('tickets.id')->all();

        $this->assertEqualsCanonicalizing([$this->outbound->id, $this->return->id], $ids);
    }

    public function test_la_vuelta_aparece_con_el_filtro_editados_y_con_estado_editado(): void
    {
        $this->actingAsAuditor();

        $component = Livewire::test(ListTicketAudit::class)->filterTable('audit_state', 'edited');

        $this->assertEqualsCanonicalizing([$this->outbound->id, $this->return->id], $this->auditIds($component));
        $component->assertTableColumnStateSet('estado', 'Editado', $this->return);
    }

    public function test_la_ultima_actividad_de_la_vuelta_es_la_edicion_desde_la_ida(): void
    {
        $this->actingAsAuditor();

        Livewire::test(ListTicketAudit::class)
            ->assertTableColumnFormattedStateSet('ultima_actividad', '04/11/2026 17:45', $this->return)
            ->sortTable('ultima_actividad', 'desc')
            ->assertCanSeeTableRecords([$this->outbound, $this->return]);
    }

    // ====================== Filtros de edición ======================

    public function test_filtro_campo_cambiado_dni_incluye_la_vuelta_y_excluye_lo_que_no_cambio_dni(): void
    {
        $this->actingAsAuditor();
        $this->edit($this->otherSale, 'price', '35000', '30000');

        $this->assertEqualsCanonicalizing([$this->outbound->id, $this->return->id], $this->filterEdits(['fields' => ['dni']]));
    }

    public function test_filtro_campo_cambiado_precio_no_incluye_la_vuelta(): void
    {
        $this->actingAsAuditor();

        $this->assertSame([$this->outbound->id], $this->filterEdits(['fields' => ['price']]));
    }

    public function test_filtro_editado_por_usa_quien_edito_y_no_quien_vendio(): void
    {
        $this->actingAsAuditor();
        $carolina = User::factory()->vendedor()->create(['name' => 'Carolina']);
        $this->edit($this->otherSale, 'dni', '92706221', '30111222', $carolina);

        $this->assertSame([$this->otherSale->id], $this->filterEdits(['user_id' => $carolina->id]));
        $this->assertEqualsCanonicalizing([$this->outbound->id, $this->return->id], $this->filterEdits(['user_id' => $this->editor->id]));
    }

    public function test_filtro_fecha_de_edicion_es_inclusivo(): void
    {
        $this->actingAsAuditor();
        Carbon::setTestNow('2026-11-03 10:00:00');
        $this->edit($this->otherSale, 'price', '35000', '30000');
        Carbon::setTestNow('2026-11-05 10:00:00');
        $dayAfter = Ticket::find($this->otherSale->id);
        $this->edit($dayAfter, 'pet_count', null, '1');

        $this->assertEqualsCanonicalizing([$this->outbound->id, $this->return->id], $this->filterEdits(['from' => '2026-11-04', 'to' => '2026-11-04']));
        $this->assertSame([$this->otherSale->id], $this->filterEdits(['from' => '2026-11-05']));
        $this->assertSame([$this->otherSale->id], $this->filterEdits(['to' => '2026-11-03']));
    }

    public function test_los_filtros_de_edicion_se_cumplen_sobre_la_misma_edicion(): void
    {
        $this->actingAsAuditor();
        $carolina = User::factory()->vendedor()->create(['name' => 'Carolina']);
        // Otra venta: DNI cambiado por Carolina y precio por Julian, el mismo día.
        $this->edit($this->otherSale, 'dni', '92706221', '30111222', $carolina);
        $this->edit($this->otherSale, 'price', '35000', '30000');

        $ids = $this->filterEdits(['fields' => ['dni'], 'user_id' => $this->editor->id, 'from' => '2026-11-04', 'to' => '2026-11-04']);

        $this->assertNotContains($this->otherSale->id, $ids, 'Ninguna edición de esa venta es un DNI cambiado por Julian.');
        $this->assertEqualsCanonicalizing([$this->outbound->id, $this->return->id], $ids);
    }

    public function test_los_filtros_de_edicion_muestran_sus_indicadores(): void
    {
        $this->actingAsAuditor();

        Livewire::test(ListTicketAudit::class)
            ->filterTable('edit_search', ['fields' => ['dni', 'first_name'], 'user_id' => $this->editor->id, 'from' => '2026-11-04', 'to' => '2026-11-04'])
            ->assertSee('Campo: DNI, Nombre')
            ->assertSee('Editado por: Julian')
            ->assertSee('Editado: 04/11/2026 – 04/11/2026');
    }

    public function test_sin_datos_el_filtro_de_edicion_no_filtra(): void
    {
        $this->actingAsAuditor();
        $deleted = $this->otherSale;
        $deleted->delete();

        $this->assertContains($deleted->id, $this->filterEdits([]));
    }

    // ====================== Helpers ======================

    /**
     * IDs de la Auditoría con el filtro de ediciones aplicado.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function filterEdits(array $data): array
    {
        $ids = $this->auditIds(Livewire::test(ListTicketAudit::class)->filterTable('edit_search', $data));
        sort($ids);

        return $ids;
    }

    private function actingAsAuditor(): void
    {
        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    /**
     * @return list<int>
     */
    private function auditIds(\Livewire\Features\SupportTesting\Testable $component): array
    {
        return $component->instance()->getFilteredTableQuery()->pluck('tickets.id')->map(fn ($id): int => (int) $id)->all();
    }

    private function edit(Ticket $ticket, string $field, ?string $old, ?string $new, ?User $user = null): void
    {
        TicketEdit::create([
            'ticket_id' => $ticket->id,
            'field' => $field,
            'field_label' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'user_id' => ($user ?? $this->editor)->id,
        ]);
    }

    /**
     * @return list<string>
     */
    private function fields(Ticket $ticket): array
    {
        return $ticket->visibleEdits()->pluck('field')->values()->all();
    }
}
