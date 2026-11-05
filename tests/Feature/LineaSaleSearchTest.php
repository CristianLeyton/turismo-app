<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Schemas\TicketForm;
use App\Models\Bus;
use App\Models\Linea;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
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
        $options = $this->callPrivate('originOptions', $this->regular->id, true);

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

        $options = $this->callPrivate('originOptions', $suelto->id, true);

        $this->assertSame([
            $this->oran->id => '1 · Orán',
            $this->pichanal->id => '2 · Pichanal',
        ], $options);
    }

    public function test_destino_desde_una_parada_intermedia_se_agrupa_por_sentido(): void
    {
        $options = $this->callPrivate('destinationOptions', $this->regular->id, $this->pichanal->id, true);

        $this->assertSame([
            'Hacia Salta' => [$this->salta->id => '3 · Salta'],
            'Hacia Orán' => [$this->oran->id => '3 · Orán'],
        ], $options);
    }

    public function test_destino_desde_el_inicio_del_recorrido_tiene_un_solo_grupo(): void
    {
        $options = $this->callPrivate('destinationOptions', $this->regular->id, $this->oran->id, true);

        $this->assertSame([
            'Hacia Salta' => [
                $this->pichanal->id => '2 · Pichanal',
                $this->salta->id => '3 · Salta',
            ],
        ], $options);
    }

    public function test_el_refuerzo_no_duplica_destinos_en_el_grupo_de_su_sentido(): void
    {
        $options = $this->callPrivate('destinationOptions', $this->refuerzo->id, $this->oran->id, true);

        $this->assertCount(1, $options);
        $this->assertCount(2, $options['Hacia Salta']);
    }

    // ====================== Selectores simples (parámetro apagado) ======================

    public function test_el_origen_simple_muestra_las_paradas_de_la_linea_sin_numerar(): void
    {
        $options = $this->callPrivate('originOptions', $this->regular->id, false);

        $this->assertSame([
            $this->oran->id => 'Orán',
            $this->pichanal->id => 'Pichanal',
            $this->salta->id => 'Salta',
        ], $options);
    }

    public function test_el_origen_simple_incluye_las_paradas_que_solo_tiene_el_refuerzo(): void
    {
        $tartagal = Location::create(['name' => 'Tartagal', 'is_active' => true]);
        $this->createRoute($this->refuerzo, 'Tartagal - Salta (Refuerzo)', [$tartagal, $this->salta]);

        $options = $this->callPrivate('originOptions', $this->regular->id, false);

        $this->assertSame('Tartagal', $options[$tartagal->id] ?? null);
    }

    public function test_el_destino_simple_es_una_lista_plana_sin_grupos_ni_repetidos(): void
    {
        $options = $this->callPrivate('destinationOptions', $this->regular->id, $this->pichanal->id, false);

        $this->assertSame([
            $this->salta->id => 'Salta',
            $this->oran->id => 'Orán',
        ], $options);
    }

    public function test_el_destino_simple_se_ordena_por_la_posicion_de_la_parada(): void
    {
        $options = $this->callPrivate('destinationOptions', $this->refuerzo->id, $this->oran->id, false);

        $this->assertSame([
            $this->pichanal->id => 'Pichanal',
            $this->salta->id => 'Salta',
        ], $options);
    }

    public function test_los_dos_estilos_ofrecen_las_mismas_localidades(): void
    {
        foreach ([$this->oran, $this->pichanal, $this->salta] as $origin) {
            $detailed = $this->callPrivate('destinationOptions', $this->regular->id, $origin->id, true);
            $simple = $this->callPrivate('destinationOptions', $this->regular->id, $origin->id, false);

            $detailedIds = collect($detailed)->flatMap(fn (array $group): array => array_keys($group))->unique()->sort()->values()->all();
            $simpleIds = collect(array_keys($simple))->sort()->values()->all();

            $this->assertSame($detailedIds, $simpleIds, "Origen {$origin->name}");
        }

        $this->assertSame(
            array_keys($this->callPrivate('originOptions', $this->regular->id, true)),
            array_keys($this->callPrivate('originOptions', $this->regular->id, false)),
        );
    }

    // ====================== Buscador en Origen y Destino ======================

    public function test_el_buscador_aparece_solo_con_mas_de_diez_opciones(): void
    {
        $this->assertFalse($this->callPrivate('hasEnoughOptionsToSearch', []));
        $this->assertFalse($this->callPrivate('hasEnoughOptionsToSearch', array_fill(1, 10, 'Parada')));
        $this->assertTrue($this->callPrivate('hasEnoughOptionsToSearch', array_fill(1, 11, 'Parada')));
    }

    public function test_el_buscador_cuenta_las_paradas_dentro_de_los_grupos_y_no_los_grupos(): void
    {
        $this->assertTrue($this->callPrivate('hasEnoughOptionsToSearch', [
            'Hacia Salta' => array_fill(1, 6, 'Parada'),
            'Hacia Orán' => array_fill(100, 5, 'Parada'),
        ]));

        $this->assertFalse($this->callPrivate('hasEnoughOptionsToSearch', [
            'Hacia Salta' => array_fill(1, 5, 'Parada'),
            'Hacia Orán' => array_fill(100, 5, 'Parada'),
        ]));
    }

    public function test_el_destino_detallado_con_mas_de_diez_paradas_es_buscable(): void
    {
        $locations = collect(range(1, 12))
            ->map(fn (int $i): Location => Location::create(['name' => "Parada {$i}", 'is_active' => true]))
            ->all();
        $largo = Bus::create(['name' => 'Largo', 'plate' => 'DDD444', 'seat_count' => 10, 'floors' => 1]);
        $this->createRoute($largo, 'Recorrido largo', $locations);

        $options = $this->callPrivate('destinationOptions', $largo->id, $locations[0]->id, true);

        // Un solo grupo con 11 paradas: antes se contaba 1 (el grupo) y no buscaba.
        $this->assertCount(1, $options);
        $this->assertTrue($this->callPrivate('hasEnoughOptionsToSearch', $options));
    }

    public function test_el_estilo_de_los_selectores_sigue_el_parametro(): void
    {
        $this->assertFalse($this->callPrivate('detailedSelectors'));

        Setting::set(Setting::SELECTORES_DETALLADOS, '1');

        $this->assertTrue($this->callPrivate('detailedSelectors'));
    }

    public function test_con_el_parametro_apagado_se_preselecciona_el_horario_unico(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 06:00:00'));
        $this->actingAs(User::factory()->vendedor()->create());

        $suelto = Bus::create(['name' => 'Suelto', 'plate' => 'CCC333', 'seat_count' => 10, 'floors' => 1]);
        $route = $this->createRoute($suelto, 'Oran - Pichanal', [$this->oran, $this->pichanal]);
        $schedule = Schedule::create(['route_id' => $route->id, 'name' => 'Única', 'departure_time' => '09:00', 'arrival_time' => '11:00', 'is_active' => true]);

        $this->assertFalse(Setting::selectoresDetallados());

        Livewire::test(CreateTicket::class)
            ->set('data.bus_id', (string) $suelto->id)
            ->set('data.departure_date', '2026-10-01')
            ->set('data.origin_location_id', (string) $this->oran->id)
            ->set('data.destination_location_id', (string) $this->pichanal->id)
            ->assertSet('data.schedule_id', $schedule->id);
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
