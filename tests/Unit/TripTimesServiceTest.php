<?php

namespace Tests\Unit;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\Trip;
use App\Services\TripTimesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTimesServiceTest extends TestCase
{
    use RefreshDatabase;

    private TripTimesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TripTimesService;
    }

    /**
     * Mundo: Orán (salida 08:00) -> Güemes (pasa 09:00, llega 08:55) -> Salta (llega 12:00).
     */
    private function createWorld(): array
    {
        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $guemes = Location::create(['name' => 'Güemes', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create([
            'name' => 'Linea 1',
            'plate' => 'AAA111',
            'seat_count' => 4,
            'floors' => 1,
        ]);

        $route = Route::create(['name' => 'Orán - Salta', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create([
            'route_id' => $route->id,
            'location_id' => $guemes->id,
            'stop_order' => 2,
            'departure_offset_minutes' => 60,
            'arrival_offset_minutes' => 55,
        ]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 3]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-01',
        ]);

        // Ruta de vuelta real (inversa) con su propio horario, como en el dominio
        $routeVuelta = Route::create(['name' => 'Salta - Orán', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $salta->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $oran->id, 'stop_order' => 2]);

        $scheduleVuelta = Schedule::create([
            'route_id' => $routeVuelta->id,
            'name' => 'Tarde vuelta',
            'departure_time' => '17:00',
            'arrival_time' => '21:00',
            'is_active' => true,
        ]);

        $tripVuelta = Trip::create([
            'route_id' => $routeVuelta->id,
            'schedule_id' => $scheduleVuelta->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-03',
        ]);

        return compact('oran', 'guemes', 'salta', 'bus', 'route', 'schedule', 'trip', 'routeVuelta', 'scheduleVuelta', 'tripVuelta');
    }

    public function test_calcula_horarios_desde_las_paradas_del_tramo(): void
    {
        extract($this->createWorld());

        // Ida: embarque en Orán, descenso en Salta (última parada -> arrival del schedule)
        $ida = $this->service->getLegTimes($trip, $schedule, $oran->id, $salta->id);
        $this->assertSame('08:00', $ida['departure']?->format('H:i'));
        $this->assertSame('12:00', $ida['arrival']?->format('H:i'));

        // Vuelta: ruta inversa con su propio horario (embarque en el destino de la venta)
        $vuelta = $this->service->getLegTimes($tripVuelta, $scheduleVuelta, $salta->id, $oran->id);
        $this->assertSame('17:00', $vuelta['departure']?->format('H:i'));
        $this->assertSame('21:00', $vuelta['arrival']?->format('H:i'));

        // Parada intermedia respeta sus offsets
        $intermedia = $this->service->getLegTimes($trip, $schedule, $guemes->id, $guemes->id);
        $this->assertSame('09:00', $intermedia['departure']?->format('H:i'));
        $this->assertSame('08:55', $intermedia['arrival']?->format('H:i'));
    }

    public function test_resuelve_la_ruta_desde_el_schedule_cuando_no_hay_trip(): void
    {
        extract($this->createWorld());

        $times = $this->service->getLegTimes(null, $schedule, $oran->id, $salta->id);

        $this->assertSame('08:00', $times['departure']?->format('H:i'));
        $this->assertSame('12:00', $times['arrival']?->format('H:i'));
    }

    public function test_devuelve_nulos_sin_trip_ni_schedule(): void
    {
        $times = $this->service->getLegTimes(null, null, 1, 2);

        $this->assertNull($times['departure']);
        $this->assertNull($times['arrival']);
    }

    public function test_usa_horarios_del_schedule_si_la_parada_no_pertenece_a_la_ruta(): void
    {
        extract($this->createWorld());

        $otra = Location::create(['name' => 'Embarcación', 'is_active' => true]);

        $times = $this->service->getLegTimes($trip, $schedule, $otra->id, $otra->id);

        $this->assertSame('08:00', $times['departure']?->format('H:i'));
        $this->assertSame('12:00', $times['arrival']?->format('H:i'));
    }
}
