<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\BusLayoutTemplate;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Services\BusLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plantillas de layout (Fase 2 del plan UX): guardar el diseño de un
 * colectivo como plantilla y aplicarla a otro, más validaciones básicas
 * del editor (renumeración incluida).
 */
class BusLayoutTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_puede_guardar_el_layout_de_un_colectivo_como_plantilla(): void
    {
        $this->actingAs(\App\Models\User::factory()->superAdmin()->create());

        $bus = Bus::create(['name' => 'Orig', 'floors' => 2, 'seat_count' => 0]);

        app(BusLayoutService::class)->saveLayout($bus, [
            'seats' => [
                ['id' => null, 'seat_number' => '1', 'floor' => 1, 'row' => 0, 'column' => 0, 'position' => null, 'seat_type' => 'normal', 'is_active' => true],
                ['id' => null, 'seat_number' => '2', 'floor' => 1, 'row' => 0, 'column' => 1, 'position' => null, 'seat_type' => 'normal', 'is_active' => true],
            ],
            'areas' => [
                ['floor' => 1, 'area_type' => 'bathroom', 'label' => 'BAÑO', 'row_start' => 1, 'row_end' => 1, 'col_start' => 2, 'col_end' => 3],
            ],
        ]);

        $service = app(BusLayoutService::class);
        $template = $service->saveAsTemplate($bus, 'Dos pisos con baño');

        $this->assertDatabaseHas('bus_layout_templates', ['name' => 'Dos pisos con baño']);
        $this->assertSame(2, $template->floors);
        $this->assertCount(2, $template->layout['seats']);
        $this->assertCount(1, $template->layout['areas']);
        // La plantilla no debe incluir ids de asientos del colectivo origen.
        $this->assertNull($template->layout['seats'][0]['id'] ?? null);
    }

    public function test_no_se_permite_duplicar_nombres_de_plantilla(): void
    {
        $this->actingAs(\App\Models\User::factory()->superAdmin()->create());

        BusLayoutTemplate::create(['name' => 'Plantilla A', 'floors' => 1, 'layout' => ['seats' => [], 'areas' => []]]);

        $this->assertTrue(
            BusLayoutTemplate::withTrashed()->where('name', 'Plantilla A')->exists(),
        );
    }

    public function test_aplicar_plantilla_crea_los_asientos_y_areas_y_ajusta_los_pisos(): void
    {
        $this->actingAs(\App\Models\User::factory()->superAdmin()->create());

        BusLayoutTemplate::create([
            'name' => 'Plantilla 2 pisos',
            'floors' => 2,
            'layout' => [
                'seats' => [
                    ['seat_number' => '1', 'floor' => 1, 'row' => 0, 'column' => 0, 'position' => null, 'seat_type' => 'normal', 'is_active' => true],
                    ['seat_number' => '2', 'floor' => 1, 'row' => 0, 'column' => 1, 'position' => null, 'seat_type' => 'normal', 'is_active' => true],
                ],
                'areas' => [
                    ['area_type' => 'luggage', 'label' => 'EQUIPAJE', 'floor' => 2, 'row_start' => 3, 'row_end' => 4, 'col_start' => 2, 'col_end' => 4],
                ],
            ],
        ]);

        $bus = Bus::create(['name' => 'Destino', 'floors' => 1, 'seat_count' => 0]);

        $applied = app(BusLayoutService::class)->applyTemplate($bus, BusLayoutTemplate::first());

        $this->assertSame(2, $applied);
        $this->assertSame(2, $bus->fresh()->floors);
        $this->assertDatabaseHas('seats', ['bus_id' => $bus->id, 'seat_number' => 1]);
        $this->assertDatabaseHas('seats', ['bus_id' => $bus->id, 'seat_number' => 2]);
        $this->assertDatabaseHas('bus_layout_areas', ['bus_id' => $bus->id, 'label' => 'EQUIPAJE']);
        // seat_count recalculado por el servicio.
        $this->assertSame(2, (int) $bus->fresh()->seat_count);
    }

    public function test_aplicar_plantilla_a_colectivo_con_asientos_vendidos_falla(): void
    {
        $this->actingAs(\App\Models\User::factory()->superAdmin()->create());

        $bus = Bus::create(['name' => 'Con ventas', 'floors' => 1, 'seat_count' => 1]);
        $seat = Seat::create(['bus_id' => $bus->id, 'seat_number' => 1, 'row' => 0, 'column' => 0, 'is_active' => true]);

        $fixture = $this->makeSaleFixture($bus, $seat);

        Ticket::create([
            'sale_id' => $fixture['sale']->id,
            'trip_id' => $fixture['trip']->id,
            'passenger_id' => $fixture['passenger']->id,
            'seat_id' => $seat->id,
            'origin_location_id' => $fixture['origin']->id,
            'destination_location_id' => $fixture['destination']->id,
            'price' => 100,
        ]);

        BusLayoutTemplate::create([
            'name' => 'Nueva config',
            'floors' => 1,
            'layout' => ['seats' => [
                ['seat_number' => 'A', 'floor' => 1, 'row' => 0, 'column' => 0, 'position' => null, 'seat_type' => 'normal', 'is_active' => true],
            ], 'areas' => []],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(BusLayoutService::class)->applyTemplate($bus, BusLayoutTemplate::first());
    }

    /**
     * Fixture mínimo para tener un Ticket vinculado al asiento (patrón
     * igual al de ClientsTableTest::makeSaleFixture).
     */
    private function makeSaleFixture(Bus $bus, Seat $seat): array
    {
        $oran = Location::create(['name' => 'Orán T', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta T', 'is_active' => true]);

        $route = Route::create(['name' => 'Orán - Salta T', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana T',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::findOrCreateForBooking($schedule->id, '2026-10-03', $oran->id, $salta->id)['trip'];
        $sale = Sale::createNew(\App\Models\User::factory()->create()->id);

        $passenger = Passenger::create([
            'first_name' => 'Ana',
            'last_name' => 'García',
            'dni' => '40000000',
        ]);

        return [
            'origin' => $oran,
            'destination' => $salta,
            'trip' => $trip,
            'sale' => $sale,
            'passenger' => $passenger,
        ];
    }
}
