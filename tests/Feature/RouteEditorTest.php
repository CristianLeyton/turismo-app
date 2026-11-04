<?php

namespace Tests\Feature;

use App\Filament\Clusters\Locations\Resources\Locations\LocationResource;
use App\Filament\Clusters\Locations\Resources\Routes\Pages\EditRoute;
use App\Filament\Clusters\Locations\Resources\Routes\Pages\ManageRoutes;
use App\Filament\Clusters\Locations\Resources\Routes\RelationManagers\SchedulesRelationManager;
use App\Filament\Clusters\Locations\Resources\Routes\RelationManagers\StopsRelationManager;
use App\Filament\Clusters\Locations\Resources\Routes\RouteResource;
use App\Filament\Clusters\Locations\Resources\RouteStops\RouteStopResource;
use App\Filament\Clusters\Locations\Resources\Schedules\ScheduleResource;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\User;
use App\Support\Permissions;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RouteEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    private function makeRoute(string $name = 'Oran - Salta'): Route
    {
        $bus = Bus::create(['name' => 'Don Esteban', 'seat_count' => 10]);

        return Route::create(['name' => $name, 'bus_id' => $bus->id, 'is_active' => true]);
    }

    private function makeSchedule(Route $route, int $hour, int $minute = 0, bool $isActive = true): Schedule
    {
        return Schedule::create([
            'route_id' => $route->id,
            'departure_time' => Carbon::createFromTime($hour, $minute),
            'is_active' => $isActive,
        ]);
    }

    private function makeStop(Route $route, string $locationName, int $order, ?int $departure, ?int $arrival = null): RouteStop
    {
        return RouteStop::create([
            'route_id' => $route->id,
            'location_id' => Location::create(['name' => $locationName, 'is_active' => true])->id,
            'stop_order' => $order,
            'departure_offset_minutes' => $departure,
            'arrival_offset_minutes' => $arrival,
        ]);
    }

    private function recorrido(Route $route): Testable
    {
        return Livewire::test(StopsRelationManager::class, [
            'ownerRecord' => $route,
            'pageClass' => EditRoute::class,
        ]);
    }

    private function createStopAction(): TestAction
    {
        return TestAction::make(CreateAction::class)->table();
    }

    public function test_crear_una_ruta_lleva_a_su_pantalla_de_edicion(): void
    {
        Livewire::test(ManageRoutes::class)
            ->callAction(CreateAction::class, data: ['name' => 'Salta - Jujuy', 'is_active' => true])
            ->assertHasNoActionErrors()
            ->assertRedirect(RouteResource::getUrl('edit', [
                'record' => Route::query()->where('name', 'Salta - Jujuy')->firstOrFail(),
            ]));
    }

    public function test_la_pantalla_de_la_ruta_renderiza_con_la_pestana_datos(): void
    {
        $route = $this->makeRoute();

        $this->get(RouteResource::getUrl('edit', ['record' => $route]))
            ->assertOk()
            ->assertSee('Datos');

        Livewire::test(EditRoute::class, ['record' => $route->getRouteKey()])
            ->assertOk()
            ->assertSchemaStateSet(['name' => 'Oran - Salta']);
    }

    public function test_el_cluster_destinos_no_muestra_menus_sueltos_de_paradas_ni_horarios(): void
    {
        $this->assertFalse(RouteStopResource::shouldRegisterNavigation());
        $this->assertFalse(ScheduleResource::shouldRegisterNavigation());
        $this->assertTrue(RouteResource::shouldRegisterNavigation());
        $this->assertTrue(LocationResource::shouldRegisterNavigation());

        $this->get(RouteResource::getUrl('index'))
            ->assertOk()
            ->assertDontSee(RouteStopResource::getUrl('index'), escape: false)
            ->assertDontSee(ScheduleResource::getUrl('index'), escape: false)
            ->assertSee(LocationResource::getUrl('index'), escape: false);
    }

    public function test_editar_los_datos_de_la_ruta_desde_su_pantalla(): void
    {
        $route = $this->makeRoute();

        Livewire::test(EditRoute::class, ['record' => $route->getRouteKey()])
            ->fillForm(['name' => 'Orán - Salta (directo)'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Orán - Salta (directo)', $route->fresh()->name);
    }

    // ====================== Recorrido ======================

    public function test_el_recorrido_lista_las_paradas_en_orden_de_paso(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $salta = $this->makeStop($route, 'Salta', 9, 300);
        $oran = $this->makeStop($route, 'Orán', 2, 0);
        $pichanal = $this->makeStop($route, 'Pichanal', 4, 45);

        $this->recorrido($route)
            ->assertOk()
            ->assertCanSeeTableRecords([$oran, $pichanal, $salta], inOrder: true);
    }

    public function test_arrastrar_en_el_recorrido_conserva_las_horas_por_posicion(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $oran = $this->makeStop($route, 'Orán', 1, 0);
        $pichanal = $this->makeStop($route, 'Pichanal', 2, 45);
        $guemes = $this->makeStop($route, 'Güemes', 3, 240);

        // Pichanal se arrastra a la posición de Güemes.
        $this->recorrido($route)
            ->call('reorderTable', [$oran->getKey(), $guemes->getKey(), $pichanal->getKey()], $pichanal->getKey())
            ->call('confirmStopOrder');

        $this->assertSame([1, 0], [$oran->fresh()->stop_order, $oran->fresh()->departure_offset_minutes]);
        $this->assertSame([2, 45], [$guemes->fresh()->stop_order, $guemes->fresh()->departure_offset_minutes]);
        $this->assertSame([3, 240], [$pichanal->fresh()->stop_order, $pichanal->fresh()->departure_offset_minutes]);
    }

    public function test_arrastrar_no_guarda_hasta_confirmar_y_muestra_el_orden_pendiente(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $oran = $this->makeStop($route, 'Oran', 1, 0);
        $pichanal = $this->makeStop($route, 'Pichanal', 2, 45);
        $guemes = $this->makeStop($route, 'Guemes', 3, 240);

        $component = $this->recorrido($route)
            ->call('toggleTableReordering')
            ->assertSee(RouteStopResource::REORDERING_HINT)
            ->assertSee('Descartar cambios')
            ->assertSee('Confirmar cambios')
            ->call('reorderTable', [$oran->getKey(), $guemes->getKey(), $pichanal->getKey()], $pichanal->getKey());

        // Nada cambió en la base todavía.
        $this->assertSame([2, 45], [$pichanal->fresh()->stop_order, $pichanal->fresh()->departure_offset_minutes]);
        $this->assertSame([3, 240], [$guemes->fresh()->stop_order, $guemes->fresh()->departure_offset_minutes]);

        // La tabla ya muestra el orden y las horas pendientes.
        $component
            ->assertCanSeeTableRecords([$oran, $guemes, $pichanal], inOrder: true)
            ->assertSeeInOrder(['Oran', '08:00', 'Guemes', '08:45', 'Pichanal', '12:00']);

        // Sin boletos vendidos, "Confirmar cambios" guarda directo.
        $component->call('requestStopOrderConfirmation')->assertSet('isTableReordering', false);

        $this->assertSame([2, 45], [$guemes->fresh()->stop_order, $guemes->fresh()->departure_offset_minutes]);
        $this->assertSame([3, 240], [$pichanal->fresh()->stop_order, $pichanal->fresh()->departure_offset_minutes]);
    }

    public function test_descartar_vuelve_al_orden_guardado(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $oran = $this->makeStop($route, 'Orán', 1, 0);
        $pichanal = $this->makeStop($route, 'Pichanal', 2, 45);

        $this->recorrido($route)
            ->call('toggleTableReordering')
            ->call('reorderTable', [$pichanal->getKey(), $oran->getKey()], $pichanal->getKey())
            ->assertSee('Hay cambios sin guardar.')
            ->call('discardStopOrder')
            ->assertSet('isTableReordering', false)
            ->assertSet('pendingStopOrder', null)
            ->assertCanSeeTableRecords([$oran, $pichanal], inOrder: true)
            ->call('confirmStopOrder');

        $this->assertSame([1, 0], [$oran->fresh()->stop_order, $oran->fresh()->departure_offset_minutes]);
        $this->assertSame([2, 45], [$pichanal->fresh()->stop_order, $pichanal->fresh()->departure_offset_minutes]);
    }

    public function test_salir_del_modo_reordenar_sin_confirmar_descarta_los_cambios(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $oran = $this->makeStop($route, 'Orán', 1, 0);
        $pichanal = $this->makeStop($route, 'Pichanal', 2, 45);

        $this->recorrido($route)
            ->call('toggleTableReordering')
            ->call('reorderTable', [$pichanal->getKey(), $oran->getKey()], $pichanal->getKey())
            ->call('toggleTableReordering')
            ->assertSet('isTableReordering', false)
            ->assertSet('pendingStopOrder', null);

        $this->assertSame(1, $oran->fresh()->stop_order);
        $this->assertSame(2, $pichanal->fresh()->stop_order);
    }

    public function test_varios_arrastres_se_guardan_juntos_al_confirmar(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $a = $this->makeStop($route, 'A', 1, 0);
        $b = $this->makeStop($route, 'B', 2, 30);
        $c = $this->makeStop($route, 'C', 3, 60);

        // C sube a la posición 2 (cambia hora con B: C=30, B=60) y después A
        // baja a la 3 (cambia hora con B, que estaba tercera: A=60, B=0). Es
        // la misma regla que si cada arrastre se guardara por separado.
        $this->recorrido($route)
            ->call('toggleTableReordering')
            ->call('reorderTable', [$a->getKey(), $c->getKey(), $b->getKey()], $c->getKey())
            ->call('reorderTable', [$c->getKey(), $b->getKey(), $a->getKey()], $a->getKey())
            ->call('confirmStopOrder');

        $this->assertSame([1, 30], [$c->fresh()->stop_order, $c->fresh()->departure_offset_minutes]);
        $this->assertSame([2, 0], [$b->fresh()->stop_order, $b->fresh()->departure_offset_minutes]);
        $this->assertSame([3, 60], [$a->fresh()->stop_order, $a->fresh()->departure_offset_minutes]);
    }

    public function test_no_guarda_un_orden_pendiente_si_el_recorrido_cambio(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $oran = $this->makeStop($route, 'Orán', 1, 0);
        $pichanal = $this->makeStop($route, 'Pichanal', 2, 45);

        $component = $this->recorrido($route)
            ->call('toggleTableReordering')
            ->call('reorderTable', [$pichanal->getKey(), $oran->getKey()], $pichanal->getKey());

        $this->makeStop($route, 'Salta', 3, 300);

        $component->call('confirmStopOrder')->assertNotified('No se guardó el nuevo orden');

        $this->assertSame(1, $oran->fresh()->stop_order);
        $this->assertSame(2, $pichanal->fresh()->stop_order);
    }

    public function test_reordenar_con_una_parada_eliminada_que_ocupa_el_primer_orden(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 1, 30);
        $this->makeStop($route, 'Embarcación', 1, 0)->delete();
        $oran = $this->makeStop($route, 'Orán', 2, 0);
        $pichanal = $this->makeStop($route, 'Pichanal', 3, 45);
        $salta = $this->makeStop($route, 'Salta', 4, 300);

        $this->recorrido($route)
            ->call('reorderTable', [$oran->getKey(), $salta->getKey(), $pichanal->getKey()], $salta->getKey())
            ->call('confirmStopOrder')
            ->assertNotified('Orden de paradas actualizado');

        $this->assertSame([1, 0], [$oran->fresh()->stop_order, $oran->fresh()->departure_offset_minutes]);
        $this->assertSame([2, 45], [$salta->fresh()->stop_order, $salta->fresh()->departure_offset_minutes]);
        $this->assertSame([3, 300], [$pichanal->fresh()->stop_order, $pichanal->fresh()->departure_offset_minutes]);
        $this->assertGreaterThan(3, RouteStop::onlyTrashed()->where('route_id', $route->id)->value('stop_order'));
    }

    public function test_reordenar_corrige_ordenes_que_quedaron_desfasados(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 1, 30);
        $this->makeStop($route, 'Embarcación', 1, 0)->delete();
        $oran = $this->makeStop($route, 'Orán', 10002, 0);
        $pichanal = $this->makeStop($route, 'Pichanal', 10003, 45);

        $this->recorrido($route)
            ->call('reorderTable', [$pichanal->getKey(), $oran->getKey()], $pichanal->getKey())
            ->call('confirmStopOrder');

        $this->assertSame([1, 0], [$pichanal->fresh()->stop_order, $pichanal->fresh()->departure_offset_minutes]);
        $this->assertSame([2, 45], [$oran->fresh()->stop_order, $oran->fresh()->departure_offset_minutes]);
    }

    public function test_una_parada_nueva_queda_ultima_sin_cargar_orden(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $this->makeStop($route, 'Orán', 2, 0);
        $this->makeStop($route, 'Pichanal', 5, 45);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $this->recorrido($route)
            ->callAction($this->createStopAction(), data: [
                'location_id' => $salta->id,
                'departure_offset_minutes' => '13:00',
            ])
            ->assertHasNoFormErrors();

        $stop = RouteStop::query()->where('location_id', $salta->id)->firstOrFail();

        $this->assertSame($route->id, $stop->route_id);
        $this->assertSame(6, $stop->stop_order);
        $this->assertSame(300, $stop->departure_offset_minutes);
    }

    public function test_las_horas_se_cargan_contra_el_primer_horario_y_se_ven_en_los_demas(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 14);
        $this->makeSchedule($route, 8);
        $this->makeStop($route, 'Orán', 1, 0);
        $pichanal = Location::create(['name' => 'Pichanal', 'is_active' => true]);

        $component = $this->recorrido($route)
            ->assertSee('Horas del horario de las 08:00')
            ->callAction($this->createStopAction(), data: [
                'location_id' => $pichanal->id,
                'departure_offset_minutes' => '08:45',
            ])
            ->assertHasNoFormErrors();

        $stop = RouteStop::query()->where('location_id', $pichanal->id)->firstOrFail();
        $this->assertSame(45, $stop->departure_offset_minutes);

        $component->assertTableColumnStateSet('other_schedules', ['Horario 14:00: sale 14:45'], $stop);
    }

    public function test_viaje_que_pasa_la_medianoche(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 22);
        $this->makeSchedule($route, 23);
        $this->makeStop($route, 'Orán', 1, 0);
        $guemes = Location::create(['name' => 'Güemes', 'is_active' => true]);

        $component = $this->recorrido($route)
            ->callAction($this->createStopAction(), data: [
                'location_id' => $guemes->id,
                'departure_offset_minutes' => '01:30',
            ])
            ->assertHasNoFormErrors();

        $stop = RouteStop::query()->where('location_id', $guemes->id)->firstOrFail();
        $this->assertSame(210, $stop->departure_offset_minutes);

        $component->assertTableColumnStateSet('other_schedules', ['Horario 23:00: sale 02:30'], $stop);
    }

    public function test_recorrido_de_una_ruta_sin_horarios_pide_cargar_uno_primero(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8, isActive: false);

        $this->recorrido($route)
            ->assertSee(RouteStopResource::NO_SCHEDULES_MESSAGE)
            ->assertActionHidden($this->createStopAction());
    }

    public function test_crear_un_destino_nuevo_desde_la_parada(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);

        $this->recorrido($route)
            ->callAction([
                $this->createStopAction(),
                TestAction::make('createOption')->schemaComponent('location_id'),
            ], data: ['name' => 'Tartagal'])
            ->assertHasNoFormErrors();

        $tartagal = Location::query()->where('name', 'Tartagal')->first();

        $this->assertNotNull($tartagal);
    }

    public function test_sin_permiso_de_crear_destinos_no_se_ofrece_crearlos(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $this->actingAs($this->userWithPermissions([
            'routes.view_any', 'routes.view', 'routes.update',
            'route_stops.view_any', 'route_stops.view', 'route_stops.create', 'route_stops.update',
            'locations.view_any',
        ]));

        $this->recorrido($route)
            ->mountAction($this->createStopAction())
            ->assertActionHidden([
                $this->createStopAction(),
                TestAction::make('createOption')->schemaComponent('location_id'),
            ]);
    }

    public function test_no_se_puede_repetir_un_destino_en_el_recorrido(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $pichanal = $this->makeStop($route, 'Pichanal', 1, 45);

        $this->recorrido($route)
            ->callAction($this->createStopAction(), data: [
                'location_id' => $pichanal->location_id,
                'departure_offset_minutes' => '09:00',
            ])
            ->assertHasFormErrors(['location_id']);

        $this->assertSame(
            'Esta localidad ya está en el recorrido.',
            RouteStopResource::duplicateLocationMessage($route->id, $pichanal->location_id),
        );
        $this->assertSame(1, RouteStop::query()->where('route_id', $route->id)->count());
    }

    public function test_un_destino_eliminado_del_recorrido_sugiere_restaurarlo(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $pichanal = $this->makeStop($route, 'Pichanal', 1, 45);
        $pichanal->delete();

        $this->recorrido($route)
            ->callAction($this->createStopAction(), data: [
                'location_id' => $pichanal->location_id,
                'departure_offset_minutes' => '09:00',
            ])
            ->assertHasFormErrors(['location_id']);

        $this->assertStringContainsString(
            'fue eliminada',
            RouteStopResource::duplicateLocationMessage($route->id, $pichanal->location_id),
        );
    }

    public function test_editar_una_parada_no_choca_con_su_propio_destino(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $pichanal = $this->makeStop($route, 'Pichanal', 1, 45);

        $this->recorrido($route)
            ->callAction(TestAction::make(EditAction::class)->table($pichanal), data: [
                'location_id' => $pichanal->location_id,
                'departure_offset_minutes' => '08:50',
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(50, $pichanal->fresh()->departure_offset_minutes);
    }

    public function test_editar_una_parada_muestra_su_hora_del_primer_horario(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 1, 30);
        $pichanal = $this->makeStop($route, 'Pichanal', 3, 45, 40);

        $this->recorrido($route)
            ->mountAction(TestAction::make(EditAction::class)->table($pichanal))
            ->assertSchemaStateSet([
                'departure_offset_minutes' => '02:15',
                'arrival_offset_minutes' => '02:10',
            ]);
    }

    public function test_rechaza_una_llegada_posterior_a_la_salida(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $pichanal = Location::create(['name' => 'Pichanal', 'is_active' => true]);

        $this->recorrido($route)
            ->callAction($this->createStopAction(), data: [
                'location_id' => $pichanal->id,
                'arrival_offset_minutes' => '08:50',
                'departure_offset_minutes' => '08:45',
            ])
            ->assertHasFormErrors(['arrival_offset_minutes']);

        $this->assertSame(0, RouteStop::query()->count());
    }

    public function test_rechaza_una_parada_que_sale_antes_que_la_anterior(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $this->makeStop($route, 'Pichanal', 1, 45);
        $urundel = Location::create(['name' => 'Urundel', 'is_active' => true]);

        $this->recorrido($route)
            ->callAction($this->createStopAction(), data: [
                'location_id' => $urundel->id,
                'departure_offset_minutes' => '08:30',
            ])
            ->assertHasFormErrors(['departure_offset_minutes']);

        $this->assertSame(
            'Esta parada no puede salir antes que la parada anterior (Pichanal, 08:45).',
            RouteStopResource::earlierThanPreviousStopMessage($route->id, '08:30'),
        );
    }

    public function test_rechaza_una_parada_anterior_a_la_anterior_aunque_de_la_vuelta_al_reloj(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $this->makeStop($route, 'Orán', 1, 0);
        $pichanal = Location::create(['name' => 'Pichanal', 'is_active' => true]);

        // 07:30 sobre una salida 08:00 daría 23 h 30 min después: es un error.
        $this->recorrido($route)
            ->callAction($this->createStopAction(), data: [
                'location_id' => $pichanal->id,
                'departure_offset_minutes' => '07:30',
            ])
            ->assertHasFormErrors(['departure_offset_minutes']);

        $this->assertSame(
            'Esta parada no puede salir antes que la parada anterior (Orán, 08:00).',
            RouteStopResource::earlierThanPreviousStopMessage($route->id, '07:30'),
        );
    }

    public function test_la_primera_parada_no_sale_antes_que_el_horario(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);

        $this->assertSame(
            'Esta parada no puede salir antes de la salida del horario (08:00).',
            RouteStopResource::earlierThanPreviousStopMessage($route->id, '07:30'),
        );
        $this->assertNull(RouteStopResource::earlierThanPreviousStopMessage($route->id, '08:00'));
    }

    public function test_acepta_una_llegada_justo_antes_de_la_salida_del_horario(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 8);
        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);

        $this->recorrido($route)
            ->callAction($this->createStopAction(), data: [
                'location_id' => $oran->id,
                'arrival_offset_minutes' => '07:50',
                'departure_offset_minutes' => '08:00',
            ])
            ->assertHasNoFormErrors();
    }

    public function test_acepta_llegada_y_salida_validas_que_cruzan_la_medianoche(): void
    {
        $route = $this->makeRoute();
        $this->makeSchedule($route, 22);
        $this->makeStop($route, 'Orán', 1, 0);
        $this->makeStop($route, 'Pichanal', 2, 90);
        $guemes = Location::create(['name' => 'Güemes', 'is_active' => true]);

        $this->recorrido($route)
            ->callAction($this->createStopAction(), data: [
                'location_id' => $guemes->id,
                'arrival_offset_minutes' => '01:20',
                'departure_offset_minutes' => '01:30',
            ])
            ->assertHasNoFormErrors();

        $stop = RouteStop::query()->where('location_id', $guemes->id)->firstOrFail();
        $this->assertSame([200, 210], [$stop->arrival_offset_minutes, $stop->departure_offset_minutes]);
    }

    // ====================== Horarios ======================

    public function test_crear_un_horario_desde_la_ruta(): void
    {
        $route = $this->makeRoute();

        Livewire::test(SchedulesRelationManager::class, ['ownerRecord' => $route, 'pageClass' => EditRoute::class])
            ->assertOk()
            ->callAction(TestAction::make(CreateAction::class)->table(), data: [
                'name' => 'Mañana',
                'departure_time' => '08:00',
                'arrival_time' => '12:30',
                'is_active' => true,
            ])
            ->assertHasNoFormErrors();

        $schedule = Schedule::query()->where('route_id', $route->id)->firstOrFail();
        $this->assertSame('08:00', $schedule->departure_time->format('H:i'));
        $this->assertSame('Mañana', $schedule->name);
    }

    public function test_editar_un_horario_desde_la_ruta(): void
    {
        $route = $this->makeRoute();
        $schedule = $this->makeSchedule($route, 8);

        Livewire::test(SchedulesRelationManager::class, ['ownerRecord' => $route, 'pageClass' => EditRoute::class])
            ->callAction(TestAction::make(EditAction::class)->table($schedule), data: [
                'departure_time' => '08:30',
            ])
            ->assertHasNoFormErrors();

        $this->assertSame('08:30', $schedule->fresh()->departure_time->format('H:i'));
    }

    public function test_cada_pestana_respeta_los_permisos_de_su_modulo(): void
    {
        $route = $this->makeRoute();
        $this->actingAs($this->userWithPermissions([
            'routes.view_any', 'routes.view', 'routes.update',
            'route_stops.view_any', 'route_stops.view',
        ]));

        $this->assertTrue(StopsRelationManager::canViewForRecord($route, EditRoute::class));
        $this->assertFalse(SchedulesRelationManager::canViewForRecord($route, EditRoute::class));

        $this->get(RouteResource::getUrl('edit', ['record' => $route]))
            ->assertOk()
            ->assertSee('Recorrido')
            ->assertDontSee('Agregar horario');
    }

    public function test_un_rol_con_permisos_de_paradas_sin_editar_rutas_no_accede_a_la_ruta(): void
    {
        $route = $this->makeRoute();
        $this->actingAs($this->userWithPermissions([
            'routes.view_any', 'routes.view',
            'route_stops.view_any', 'route_stops.view', 'route_stops.create', 'route_stops.update', 'route_stops.delete',
        ]));

        // Ya no hay menú suelto de paradas: sin routes.update no se llega al
        // recorrido. Hay que habilitarle los permisos de rutas desde la matriz.
        $this->get(RouteResource::getUrl('edit', ['record' => $route]))->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'Rol de prueba '.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
