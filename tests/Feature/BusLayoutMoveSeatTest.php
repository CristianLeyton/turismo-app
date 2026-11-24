<?php

namespace Tests\Feature;

use App\Filament\Resources\Buses\Pages\DesignBusLayout;
use App\Models\Bus;
use App\Models\BusLayoutArea;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Support\Permissions;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Modo "Mover" del diseñador de colectivos: reubicar e intercambiar
 * asientos cambia solo dónde se dibujan, sin tocar número, estado ni
 * boletos vendidos.
 */
class BusLayoutMoveSeatTest extends TestCase
{
    use RefreshDatabase;

    private Bus $bus;

    private Seat $seat12;

    private Seat $seat30;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->bus = Bus::create(['name' => 'Mover', 'plate' => 'MOV111', 'seat_count' => 2, 'floors' => 1]);
        $this->seat12 = Seat::create(['bus_id' => $this->bus->id, 'seat_number' => '12', 'floor' => 1, 'row' => 2, 'column' => 0, 'is_active' => true]);
        $this->seat30 = Seat::create(['bus_id' => $this->bus->id, 'seat_number' => '30', 'floor' => 1, 'row' => 7, 'column' => 3, 'is_active' => true]);
    }

    public function test_mover_un_asiento_a_una_celda_vacia(): void
    {
        $component = $this->editor()->call('moveSeat', 2, 0, 5, 3);

        $this->assertSame([5, 3], $this->positionOf($component, '12'));
        $this->assertNull($component->instance()->seatAt(2, 0));
    }

    public function test_mover_sobre_otro_asiento_los_intercambia(): void
    {
        $component = $this->editor()->call('moveSeat', 2, 0, 7, 3);

        $this->assertSame([7, 3], $this->positionOf($component, '12'));
        $this->assertSame([2, 0], $this->positionOf($component, '30'));
    }

    public function test_no_se_puede_mover_un_asiento_sobre_una_zona(): void
    {
        BusLayoutArea::create([
            'bus_id' => $this->bus->id,
            'floor' => 1,
            'area_type' => 'bathroom',
            'label' => 'BAÑO',
            'row_start' => 4,
            'row_end' => 4,
            'column_start' => 1,
            'column_end' => 1,
        ]);

        $component = $this->editor()
            ->call('moveSeat', 2, 0, 4, 1)
            ->assertNotified('No se puede mover el asiento');

        $this->assertSame([2, 0], $this->positionOf($component, '12'));
    }

    public function test_el_aviso_nombra_la_zona_aunque_no_tenga_etiqueta(): void
    {
        BusLayoutArea::create([
            'bus_id' => $this->bus->id,
            'floor' => 1,
            'area_type' => 'pasillo',
            'label' => '',
            'row_start' => 2,
            'row_end' => 7,
            'column_start' => 2,
            'column_end' => 2,
        ]);

        $component = $this->editor()
            ->call('moveSeat', 2, 0, 3, 2)
            ->assertNotified(
                Notification::make()
                    ->title('No se puede mover el asiento')
                    ->body('La celda de destino es parte de la zona "PASILLO". Elegí una celda libre u otro asiento.')
                    ->warning(),
            );

        $this->assertSame([2, 0], $this->positionOf($component, '12'));
    }

    public function test_mover_a_la_misma_celda_no_cambia_nada(): void
    {
        $component = $this->editor()->call('moveSeat', 2, 0, 2, 0);

        $this->assertSame([2, 0], $this->positionOf($component, '12'));
        $this->assertSame([7, 3], $this->positionOf($component, '30'));
    }

    public function test_mover_un_asiento_vendido_conserva_su_boleto_y_numero(): void
    {
        $ticket = $this->sellTicketFor($this->seat12);

        $this->editor()
            ->call('moveSeat', 2, 0, 5, 3)
            ->call('save')
            ->assertNotified('Layout guardado');

        $seat = $this->seat12->fresh();
        $this->assertSame('12', (string) $seat->seat_number);
        $this->assertSame(5, (int) $seat->row);
        $this->assertSame(3, (int) $seat->column);
        $this->assertSame($this->seat12->id, $ticket->fresh()->seat_id);
    }

    public function test_intercambiar_asientos_vendidos_se_guarda(): void
    {
        $ticket12 = $this->sellTicketFor($this->seat12);

        $this->editor()
            ->call('moveSeat', 2, 0, 7, 3)
            ->call('save')
            ->assertNotified('Layout guardado');

        $this->assertSame([7, 3], [(int) $this->seat12->fresh()->row, (int) $this->seat12->fresh()->column]);
        $this->assertSame([2, 0], [(int) $this->seat30->fresh()->row, (int) $this->seat30->fresh()->column]);
        $this->assertSame($this->seat12->id, $ticket12->fresh()->seat_id);
    }

    public function test_mover_un_asiento_inactivo_lo_deja_inactivo(): void
    {
        $this->seat30->update(['is_active' => false]);

        $this->editor()
            ->call('moveSeat', 7, 3, 0, 0)
            ->call('save')
            ->assertNotified('Layout guardado');

        $seat = $this->seat30->fresh();
        $this->assertSame([0, 0], [(int) $seat->row, (int) $seat->column]);
        $this->assertFalse((bool) $seat->is_active);
    }

    public function test_descartar_cambios_devuelve_el_asiento_a_su_lugar(): void
    {
        $component = $this->editor()
            ->call('moveSeat', 2, 0, 5, 3)
            ->call('discard');

        $this->assertSame([2, 0], $this->positionOf($component, '12'));
        $this->assertSame([2, 0], [(int) $this->seat12->fresh()->row, (int) $this->seat12->fresh()->column]);
    }

    public function test_el_editor_ofrece_el_modo_mover(): void
    {
        $this->editor()
            ->assertSeeHtml("setMode('move')")
            ->assertSee('Clic en un asiento para elegirlo');
    }

    private function editor(): Testable
    {
        return Livewire::test(DesignBusLayout::class, ['record' => $this->bus->id]);
    }

    /**
     * Fila y columna del asiento en el borrador del editor.
     *
     * @return array{0: int, 1: int}
     */
    private function positionOf(Testable $component, string $seatNumber): array
    {
        $seat = collect($component->get('seats'))->firstWhere('seat_number', $seatNumber);

        return [(int) $seat['row'], (int) $seat['column']];
    }

    private function sellTicketFor(Seat $seat): Ticket
    {
        $oran = Location::create(['name' => 'Orán M', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta M', 'is_active' => true]);

        $route = Route::create(['name' => 'Orán - Salta M', 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana M',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::findOrCreateForBooking($schedule->id, '2026-10-03', $oran->id, $salta->id)['trip'];
        $sale = Sale::createNew(User::factory()->create()->id);
        $passenger = Passenger::create(['first_name' => 'Ana', 'last_name' => 'García', 'dni' => '40000000']);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'passenger_id' => $passenger->id,
            'seat_id' => $seat->id,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
        ]);
    }
}
