<?php

namespace Tests\Feature;

use App\Filament\Clusters\Locations\Resources\Routes\Pages\EditRoute;
use App\Filament\Clusters\Locations\Resources\Routes\RelationManagers\SchedulesRelationManager;
use App\Filament\Clusters\Locations\Resources\Routes\RelationManagers\StopsRelationManager;
use App\Filament\Clusters\Locations\Resources\Schedules\ScheduleResource;
use App\Models\Bus;
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
use App\Support\AffectedTickets;
use App\Support\Permissions;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class AffectedTicketsTest extends TestCase
{
    use RefreshDatabase;

    private Bus $bus;

    private Route $route;

    private Schedule $morning;

    private Schedule $afternoon;

    private Location $origin;

    private Location $destination;

    private Sale $sale;

    private int $ticketsCreated = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->origin = Location::create(['name' => 'Orán', 'is_active' => true]);
        $this->destination = Location::create(['name' => 'Salta', 'is_active' => true]);
        $this->bus = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 40, 'floors' => 1]);
        $this->route = Route::create(['name' => 'Oran - Salta', 'bus_id' => $this->bus->id, 'is_active' => true]);
        $this->morning = Schedule::create(['route_id' => $this->route->id, 'departure_time' => '08:00', 'is_active' => true]);
        $this->afternoon = Schedule::create(['route_id' => $this->route->id, 'departure_time' => '14:00', 'is_active' => true]);
        $this->sale = Sale::create(['user_id' => auth()->id(), 'sale_date' => now(), 'total_amount' => 100]);
    }

    private function trip(Schedule $schedule, string $date, ?Route $route = null): Trip
    {
        return Trip::create([
            'route_id' => ($route ?? $this->route)->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $this->bus->id,
            'trip_date' => $date,
        ]);
    }

    private function sellTicket(Trip $trip, ?Trip $returnTrip = null): Ticket
    {
        $this->ticketsCreated++;

        return Ticket::create([
            'sale_id' => $this->sale->id,
            'trip_id' => $trip->id,
            'return_trip_id' => $returnTrip?->id,
            'seat_id' => Seat::create([
                'bus_id' => $this->bus->id,
                'seat_number' => (string) $this->ticketsCreated,
                'is_active' => true,
                'floor' => '1',
            ])->id,
            'passenger_id' => Passenger::create([
                'first_name' => 'Pasajero',
                'last_name' => (string) $this->ticketsCreated,
                'dni' => (string) (30000000 + $this->ticketsCreated),
                'passenger_type' => 'adult',
            ])->id,
            'is_round_trip' => $returnTrip !== null,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'price' => 100,
        ]);
    }

    public function test_cuenta_boletos_de_viajes_que_todavia_no_partieron(): void
    {
        $this->travelTo(today()->setTime(7, 0));

        $this->sellTicket($this->trip($this->morning, today()->toDateString()));
        $this->sellTicket($this->trip($this->afternoon, today()->addDays(3)->toDateString()));

        $this->assertSame(2, AffectedTickets::forRoute($this->route));
    }

    public function test_no_cuenta_los_viajes_de_hoy_que_ya_partieron(): void
    {
        // 10:00: el viaje de las 08:00 ya salió; el de las 14:00 todavía no.
        $this->travelTo(today()->setTime(10, 0));

        $this->sellTicket($this->trip($this->morning, today()->toDateString()));
        $this->sellTicket($this->trip($this->afternoon, today()->toDateString()));

        $this->assertSame(1, AffectedTickets::forRoute($this->route));
        $this->assertSame(0, AffectedTickets::forSchedule($this->morning));
        $this->assertSame(1, AffectedTickets::forSchedule($this->afternoon));
    }

    public function test_un_viaje_que_sale_justo_ahora_ya_cuenta_como_partido(): void
    {
        $this->travelTo(today()->setTime(8, 0));

        $this->sellTicket($this->trip($this->morning, today()->toDateString()));

        $this->assertSame(0, AffectedTickets::forSchedule($this->morning));
    }

    public function test_un_horario_cuyo_viaje_de_hoy_ya_partio_se_puede_eliminar(): void
    {
        $this->travelTo(today()->setTime(10, 0));
        $this->sellTicket($this->trip($this->morning, today()->toDateString()));

        $this->horarios()
            ->assertActionHidden(TestAction::make('deactivate')->table($this->morning))
            ->assertActionVisible(TestAction::make(DeleteAction::class)->table($this->morning));
    }

    public function test_no_cuenta_viajes_pasados_ni_boletos_eliminados(): void
    {
        $this->sellTicket($this->trip($this->morning, today()->subDay()->toDateString()));
        $this->sellTicket($this->trip($this->morning, today()->addDay()->toDateString()))->delete();

        $this->assertSame(0, AffectedTickets::forRoute($this->route));
        $this->assertSame(0, AffectedTickets::forSchedule($this->morning));
    }

    public function test_por_horario_cuenta_solo_los_viajes_de_ese_horario(): void
    {
        $this->sellTicket($this->trip($this->morning, today()->addDay()->toDateString()));
        $this->sellTicket($this->trip($this->afternoon, today()->addDay()->toDateString()));
        $this->sellTicket($this->trip($this->afternoon, today()->addDays(2)->toDateString()));

        $this->assertSame(1, AffectedTickets::forSchedule($this->morning));
        $this->assertSame(2, AffectedTickets::forSchedule($this->afternoon));
    }

    public function test_cuenta_la_vuelta_de_un_boleto_de_ida_y_vuelta(): void
    {
        $otherRoute = Route::create(['name' => 'Salta - Oran', 'bus_id' => $this->bus->id, 'is_active' => true]);
        $otherSchedule = Schedule::create(['route_id' => $otherRoute->id, 'departure_time' => '18:00', 'is_active' => true]);

        // Ida ya pasó por otra ruta; la vuelta es próxima y es de esta ruta.
        $this->sellTicket(
            $this->trip($otherSchedule, today()->subDays(2)->toDateString(), $otherRoute),
            $this->trip($this->morning, today()->addDays(5)->toDateString()),
        );

        $this->assertSame(1, AffectedTickets::forRoute($this->route));
        $this->assertSame(1, AffectedTickets::forSchedule($this->morning));
        $this->assertSame(0, AffectedTickets::forRoute($otherRoute));
    }

    // ====================== Avisos en la pantalla de ruta ======================

    private function stop(string $name, int $order, int $departure): RouteStop
    {
        return RouteStop::create([
            'route_id' => $this->route->id,
            'location_id' => Location::create(['name' => $name, 'is_active' => true])->id,
            'stop_order' => $order,
            'departure_offset_minutes' => $departure,
        ]);
    }

    private function recorrido(): Testable
    {
        return Livewire::test(StopsRelationManager::class, ['ownerRecord' => $this->route, 'pageClass' => EditRoute::class]);
    }

    private function horarios(): Testable
    {
        return Livewire::test(SchedulesRelationManager::class, ['ownerRecord' => $this->route, 'pageClass' => EditRoute::class]);
    }

    public function test_editar_una_parada_con_boletos_vendidos_avisa_cuantos_son(): void
    {
        $pichanal = $this->stop('Pichanal', 1, 45);
        $this->sellTicket($this->trip($this->morning, today()->addDay()->toDateString()));
        $this->sellTicket($this->trip($this->afternoon, today()->addDay()->toDateString()));

        $this->recorrido()
            ->mountAction(TestAction::make(EditAction::class)->table($pichanal))
            ->assertMountedActionModalSee('Hay 2 boletos vendidos en viajes próximos que van a ver la hora nueva.');
    }

    public function test_eliminar_una_parada_con_boletos_vendidos_avisa_y_pide_confirmar(): void
    {
        $pichanal = $this->stop('Pichanal', 1, 45);
        $this->sellTicket($this->trip($this->morning, today()->addDay()->toDateString()));

        $this->recorrido()
            ->mountAction(TestAction::make(DeleteAction::class)->table($pichanal))
            ->assertMountedActionModalSee('Hay 1 boleto vendido en un viaje próximo que va a ver la hora nueva. ¿Querés eliminar la parada igual?')
            ->callMountedAction();

        $this->assertSoftDeleted($pichanal);
    }

    public function test_sin_boletos_vendidos_no_hay_aviso_al_editar_una_parada(): void
    {
        $pichanal = $this->stop('Pichanal', 1, 45);
        $this->sellTicket($this->trip($this->morning, today()->subDay()->toDateString()));

        $this->recorrido()
            ->mountAction(TestAction::make(EditAction::class)->table($pichanal))
            ->assertMountedActionModalDontSee('boletos vendidos')
            ->assertMountedActionModalDontSee('boleto vendido')
            ->fillForm(['departure_offset_minutes' => '08:50'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame(50, $pichanal->fresh()->departure_offset_minutes);
    }

    public function test_reordenar_con_boletos_vendidos_avisa_en_la_notificacion(): void
    {
        $yrigoyen = $this->stop('Yrigoyen', 1, 0);
        $pichanal = $this->stop('Pichanal', 2, 45);
        $this->sellTicket($this->trip($this->morning, today()->addDay()->toDateString()));

        $this->recorrido()
            ->call('reorderTable', [$pichanal->getKey(), $yrigoyen->getKey()], $pichanal->getKey())
            ->call('confirmStopOrder')
            ->assertNotified(
                Notification::make()
                    ->title('Orden de paradas actualizado')
                    ->body('Los horarios de salida y llegada se intercambiaron con el nuevo orden. Hay 1 boleto vendido en un viaje próximo que va a ver la hora nueva.')
                    ->warning()
                    ->persistent(),
            );
    }

    public function test_confirmar_el_orden_con_boletos_vendidos_pide_confirmacion_antes_de_guardar(): void
    {
        $yrigoyen = $this->stop('Yrigoyen', 1, 0);
        $pichanal = $this->stop('Pichanal', 2, 45);
        $this->sellTicket($this->trip($this->morning, today()->addDay()->toDateString()));

        $component = $this->recorrido()
            ->call('toggleTableReordering')
            ->call('reorderTable', [$pichanal->getKey(), $yrigoyen->getKey()], $pichanal->getKey())
            ->call('requestStopOrderConfirmation')
            ->assertMountedActionModalSee('Hay 1 boleto vendido en un viaje próximo que va a ver la hora nueva. ¿Querés guardar el nuevo orden igual?');

        // Mientras el modal está abierto, todavía no se guardó nada.
        $this->assertSame(2, $pichanal->fresh()->stop_order);

        $component->callMountedAction();

        $this->assertSame(1, $pichanal->fresh()->stop_order);
        $this->assertSame(0, $pichanal->fresh()->departure_offset_minutes);
    }

    public function test_reordenar_sin_boletos_vendidos_no_avisa_de_boletos(): void
    {
        $yrigoyen = $this->stop('Yrigoyen', 1, 0);
        $pichanal = $this->stop('Pichanal', 2, 45);

        $this->recorrido()
            ->call('reorderTable', [$pichanal->getKey(), $yrigoyen->getKey()], $pichanal->getKey())
            ->call('confirmStopOrder')
            ->assertNotified(
                Notification::make()
                    ->title('Orden de paradas actualizado')
                    ->body('Los horarios de salida y llegada se intercambiaron con el nuevo orden.')
                    ->success(),
            );
    }

    public function test_editar_un_horario_con_boletos_vendidos_avisa_cuantos_son(): void
    {
        $this->sellTicket($this->trip($this->morning, today()->addDay()->toDateString()));
        $this->sellTicket($this->trip($this->afternoon, today()->addDay()->toDateString()));

        $this->horarios()
            ->mountAction(TestAction::make(EditAction::class)->table($this->morning))
            ->assertMountedActionModalSee('Hay 1 boleto vendido en un viaje próximo que va a ver la hora nueva.');
    }

    // ====================== Eliminar horarios con boletos ======================

    public function test_un_horario_con_boletos_proximos_no_se_puede_eliminar(): void
    {
        $this->sellTicket($this->trip($this->morning, today()->addDay()->toDateString()));

        $this->horarios()
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($this->morning))
            ->assertActionVisible(TestAction::make('deactivate')->table($this->morning));

        $this->assertNotSoftDeleted($this->morning);
        $this->assertSame(
            'Este horario tiene 1 boleto vendido en viajes próximos, por eso no se puede eliminar. Podés desactivarlo para que no se use en nuevas ventas.',
            ScheduleResource::cannotDeleteMessage(1),
        );
    }

    public function test_desactivar_un_horario_con_boletos_proximos(): void
    {
        $this->sellTicket($this->trip($this->morning, today()->addDay()->toDateString()));

        $this->horarios()
            ->callAction(TestAction::make('deactivate')->table($this->morning))
            ->assertNotified('Horario desactivado');

        $this->assertFalse($this->morning->fresh()->is_active);
        $this->assertNotSoftDeleted($this->morning);
    }

    public function test_un_horario_sin_boletos_proximos_se_puede_eliminar(): void
    {
        $this->sellTicket($this->trip($this->morning, today()->subDays(3)->toDateString()));

        $this->horarios()
            ->assertActionHidden(TestAction::make('deactivate')->table($this->morning))
            ->callAction(TestAction::make(DeleteAction::class)->table($this->morning));

        $this->assertSoftDeleted($this->morning);
    }

    public function test_eliminar_en_lote_se_corta_si_algun_horario_tiene_boletos(): void
    {
        $this->sellTicket($this->trip($this->morning, today()->addDay()->toDateString()));

        $this->horarios()
            ->selectTableRecords([$this->morning->getKey(), $this->afternoon->getKey()])
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
            ->assertNotified('No se eliminó ningún horario');

        $this->assertNotSoftDeleted($this->morning);
        $this->assertNotSoftDeleted($this->afternoon);
    }

    public function test_el_aviso_dice_cuantos_boletos_y_no_aparece_sin_boletos(): void
    {
        $this->assertNull(AffectedTickets::warning(0));
        $this->assertSame('Hay 1 boleto vendido en un viaje próximo que va a ver la hora nueva.', AffectedTickets::warning(1));
        $this->assertSame('Hay 12 boletos vendidos en viajes próximos que van a ver la hora nueva.', AffectedTickets::warning(12));
    }
}
