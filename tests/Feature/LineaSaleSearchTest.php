<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Schemas\TicketForm;
use App\Models\Bus;
use App\Models\Linea;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class LineaSaleSearchTest extends TestCase
{
    use RefreshDatabase;

    private Location $oran;

    private Location $pichanal;

    private Location $salta;

    private Bus $regular;

    private Bus $refuerzo;

    private Route $regularIda;

    private Route $refuerzoIda;

    private Schedule $refuerzoSchedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $this->pichanal = Location::create(['name' => 'Pichanal', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $linea = Linea::create(['name' => 'Oran - Salta']);

        $this->regular = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 10, 'floors' => 1, 'linea_id' => $linea->id]);
        $this->refuerzo = Bus::create(['name' => 'Oran - Salta (Refuerzo)', 'plate' => 'BBB222', 'seat_count' => 10, 'floors' => 1, 'linea_id' => $linea->id, 'is_reinforcement' => true]);

        $this->regularIda = $this->createRoute($this->regular, 'Oran - Salta', [$this->oran, $this->pichanal, $this->salta]);
        $this->createRoute($this->regular, 'Salta - Oran', [$this->salta, $this->pichanal, $this->oran]);
        $this->refuerzoIda = $this->createRoute($this->refuerzo, 'Oran - Salta (Refuerzo)', [$this->oran, $this->pichanal, $this->salta]);
        $this->createRoute($this->refuerzo, 'Salta - Oran (Refuerzo)', [$this->salta, $this->pichanal, $this->oran]);

        Schedule::create(['route_id' => $this->regularIda->id, 'name' => 'Mañana', 'departure_time' => '08:00', 'arrival_time' => '12:00', 'is_active' => true]);
        $this->refuerzoSchedule = Schedule::create(['route_id' => $this->refuerzoIda->id, 'name' => 'Refuerzo', 'departure_time' => '15:00', 'arrival_time' => '19:00', 'is_active' => true]);
    }

    /**
     * @param  array<int, Location>  $locations
     */
    private function createRoute(Bus $bus, string $name, array $locations): Route
    {
        $route = Route::create(['bus_id' => $bus->id, 'name' => $name, 'is_active' => true]);

        foreach ($locations as $index => $location) {
            RouteStop::create(['route_id' => $route->id, 'location_id' => $location->id, 'stop_order' => $index + 1]);
        }

        return $route;
    }

    private function callPrivate(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(TicketForm::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, ...$arguments);
    }

    public function test_los_colectivos_de_la_linea_incluyen_al_refuerzo(): void
    {
        $ids = $this->callPrivate('lineBusIds', $this->regular->id);

        $this->assertEqualsCanonicalizing([$this->regular->id, $this->refuerzo->id], $ids);
        $this->assertEqualsCanonicalizing([$this->regular->id, $this->refuerzo->id], $this->callPrivate('lineBusIds', $this->refuerzo->id));
    }

    public function test_un_colectivo_sin_linea_busca_solo_por_si_mismo(): void
    {
        $suelto = Bus::create(['name' => 'Suelto', 'plate' => 'CCC333', 'seat_count' => 10, 'floors' => 1]);

        $this->assertSame([$suelto->id], $this->callPrivate('lineBusIds', $suelto->id));
        $this->assertSame([], $this->callPrivate('lineBusIds', null));
    }

    public function test_el_selector_de_colectivo_oculta_los_refuerzos(): void
    {
        $options = $this->callPrivate('saleBusOptions', null);

        $this->assertArrayHasKey($this->regular->id, $options);
        $this->assertArrayNotHasKey($this->refuerzo->id, $options);
    }

    public function test_el_selector_conserva_un_refuerzo_ya_guardado_en_una_venta(): void
    {
        $options = $this->callPrivate('saleBusOptions', $this->refuerzo->id);

        $this->assertArrayHasKey($this->refuerzo->id, $options);
        $this->assertArrayHasKey($this->regular->id, $options);
    }

    public function test_el_origen_muestra_las_paradas_de_la_linea_sin_repetirlas(): void
    {
        $options = $this->callPrivate('originOptions', $this->regular->id);

        $this->assertSame([
            $this->oran->id => '1 · Orán',
            $this->pichanal->id => '2 · Pichanal',
            $this->salta->id => '3 · Salta',
        ], $options);
    }

    public function test_un_colectivo_sin_linea_muestra_solo_sus_paradas(): void
    {
        $suelto = Bus::create(['name' => 'Suelto', 'plate' => 'CCC333', 'seat_count' => 10, 'floors' => 1]);
        $this->createRoute($suelto, 'Oran - Pichanal', [$this->oran, $this->pichanal]);

        $options = $this->callPrivate('originOptions', $suelto->id);

        $this->assertSame([
            $this->oran->id => '1 · Orán',
            $this->pichanal->id => '2 · Pichanal',
        ], $options);
    }

    public function test_destino_desde_una_parada_intermedia_se_agrupa_por_sentido(): void
    {
        $options = $this->callPrivate('destinationOptions', $this->regular->id, $this->pichanal->id);

        $this->assertSame([
            'Hacia Salta' => [$this->salta->id => '3 · Salta'],
            'Hacia Orán' => [$this->oran->id => '3 · Orán'],
        ], $options);
    }

    public function test_destino_desde_el_inicio_del_recorrido_tiene_un_solo_grupo(): void
    {
        $options = $this->callPrivate('destinationOptions', $this->regular->id, $this->oran->id);

        $this->assertSame([
            'Hacia Salta' => [
                $this->pichanal->id => '2 · Pichanal',
                $this->salta->id => '3 · Salta',
            ],
        ], $options);
    }

    public function test_el_refuerzo_no_duplica_destinos_en_el_grupo_de_su_sentido(): void
    {
        $options = $this->callPrivate('destinationOptions', $this->refuerzo->id, $this->oran->id);

        $this->assertCount(1, $options);
        $this->assertCount(2, $options['Hacia Salta']);
    }

    public function test_la_etiqueta_del_horario_incluye_el_colectivo(): void
    {
        $schedule = Schedule::with('route.bus')->findOrFail($this->refuerzoSchedule->id);

        $this->assertStringContainsString('· Oran - Salta (Refuerzo)', $this->callPrivate('scheduleOptionLabel', $schedule));
    }

    public function test_el_colectivo_mostrado_es_el_del_horario_elegido(): void
    {
        $this->assertSame('Oran - Salta (Refuerzo)', $this->callPrivate('busNameFor', $this->refuerzoSchedule->id, $this->regular->id));
        $this->assertSame('Oran - Salta', $this->callPrivate('busNameFor', null, $this->regular->id));
        $this->assertSame('—', $this->callPrivate('busNameFor', null, null));
    }

    public function test_el_viaje_de_un_horario_de_refuerzo_queda_en_el_refuerzo(): void
    {
        $result = Trip::findOrCreateForBooking(
            $this->refuerzoSchedule->id,
            now()->addDays(3)->format('Y-m-d'),
            $this->oran->id,
            $this->salta->id,
        );

        $this->assertNotNull($result['trip']);
        $this->assertSame($this->refuerzo->id, $result['trip']->bus_id);
    }
}
