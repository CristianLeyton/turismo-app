<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica que la vuelta permita elegir horarios de CUALQUIER colectivo
 * que tenga la ruta origen→destino (ej: ida Colectivo 2 Orán→Salta, vuelta Colectivo 1 Salta→Orán).
 */
class ReturnTripDifferentBusTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_schedules_include_schedules_from_other_buses_with_same_stops(): void
    {
        $oran = Location::create(['name' => 'Orán']);
        $salta = Location::create(['name' => 'Salta']);

        $bus1 = Bus::create([
            'name' => 'Linea 1 (Orán)',
            'plate' => 'ABC',
            'seat_count' => 55,
            'floors' => 2,
        ]);
        $bus2 = Bus::create([
            'name' => 'Linea 2 (Embarcacion)',
            'plate' => 'DEF',
            'seat_count' => 60,
            'floors' => 2,
        ]);

        $route1Ida = Route::create(['name' => 'Orán - Salta', 'bus_id' => $bus1->id]);
        RouteStop::create(['route_id' => $route1Ida->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route1Ida->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $route1Vuelta = Route::create(['name' => 'Salta - Orán', 'bus_id' => $bus1->id]);
        RouteStop::create(['route_id' => $route1Vuelta->id, 'location_id' => $salta->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route1Vuelta->id, 'location_id' => $oran->id, 'stop_order' => 2]);

        $route2Ida = Route::create(['name' => 'Orán - Salta', 'bus_id' => $bus2->id]);
        RouteStop::create(['route_id' => $route2Ida->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route2Ida->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $route2Vuelta = Route::create(['name' => 'Salta - Orán', 'bus_id' => $bus2->id]);
        RouteStop::create(['route_id' => $route2Vuelta->id, 'location_id' => $salta->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route2Vuelta->id, 'location_id' => $oran->id, 'stop_order' => 2]);

        $schedule1Vuelta = Schedule::create([
            'route_id' => $route1Vuelta->id,
            'name' => 'Tarde L1',
            'departure_time' => '15:00',
            'arrival_time' => '20:00',
            'is_active' => true,
        ]);
        $schedule2Vuelta = Schedule::create([
            'route_id' => $route2Vuelta->id,
            'name' => 'Tarde L2',
            'departure_time' => '16:00',
            'arrival_time' => '21:00',
            'is_active' => true,
        ]);

        $originVueltaId = $salta->id;
        $destinationVueltaId = $oran->id;

        $schedules = \App\Models\Schedule::query()
            ->whereHas('route.stops', fn ($q) => $q->where('location_id', $originVueltaId))
            ->whereHas('route.stops', fn ($q) => $q->where('location_id', $destinationVueltaId))
            ->where('is_active', true)
            ->orderBy('departure_time')
            ->get()
            ->filter(function ($schedule) use ($originVueltaId, $destinationVueltaId) {
                return $schedule->route->isValidSegment($originVueltaId, $destinationVueltaId);
            });

        $scheduleIds = $schedules->pluck('id')->toArray();
        $busIds = $schedules->pluck('route.bus_id')->unique()->values()->toArray();

        $this->assertCount(2, $schedules, 'Debe haber horarios de vuelta de ambos colectivos');
        $this->assertContains($schedule1Vuelta->id, $scheduleIds);
        $this->assertContains($schedule2Vuelta->id, $scheduleIds);
        $this->assertContains($bus1->id, $busIds);
        $this->assertContains($bus2->id, $busIds);
    }
}
