<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\PaymentMethod;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\SeatReservation;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Services\TicketRescheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SellWithoutDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los tests que venden usan método de pago: uno activo en la tabla.
        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        \Illuminate\Support\Facades\Cache::forget('payment_methods.options');
        \Illuminate\Support\Facades\Cache::forget('payment_methods.all');
    }

    // ====================== Permiso ======================

    public function test_el_permiso_esta_en_el_catalogo(): void
    {
        $this->assertContains('tickets.vender_sin_fecha', \App\Support\Permissions::all());
    }

    public function test_los_roles_admin_y_vendedor_no_traen_el_permiso_por_defecto(): void
    {
        \App\Support\Permissions::syncToDatabase();

        // ADMIN y VENDEDOR NO lo traen (es habilitable por rol desde la matriz).
        foreach ([\App\Support\Permissions::ROLE_ADMIN, \App\Support\Permissions::ROLE_SELLER] as $roleName) {
            $role = Role::findByName($roleName);

            $this->assertFalse(
                $role->hasPermissionTo('tickets.vender_sin_fecha'),
                "El rol {$roleName} no debe traer vender_sin_fecha por defecto.",
            );
        }

        // SUPER trae TODO el catálogo por diseño (bypass).
        $super = Role::findByName(\App\Support\Permissions::ROLE_SUPER);
        $this->assertTrue($super->hasPermissionTo('tickets.vender_sin_fecha'));
    }

    public function test_sync_es_idempotente_y_un_rol_custom_puede_habilitarlo(): void
    {
        // El permiso existe desde la migración de seed; el sync no lo duplica.
        $before = \Spatie\Permission\Models\Permission::where('name', 'tickets.vender_sin_fecha')->count();
        $this->assertSame(1, $before);

        Artisan::call('permissions:sync');

        $this->assertSame(1, \Spatie\Permission\Models\Permission::where('name', 'tickets.vender_sin_fecha')->count());

        $role = Role::create(['name' => 'Taquilla', 'guard_name' => 'web']);
        $role->syncPermissions(['tickets.view_any', 'tickets.create', 'tickets.vender_sin_fecha']);

        $user = User::factory()->create();
        $user->syncRoles($role);

        $this->assertTrue($user->can('tickets.vender_sin_fecha'));
    }

    // ====================== Guard server-side ======================

    public function test_vendedor_sin_permiso_no_puede_forzar_la_venta_sin_fecha(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        // El vendedor del sistema NO trae el permiso (ni ninguno otro).
        $vendedor = User::factory()->vendedor()->create();
        $this->actingAs($vendedor);
        $this->assertFalse($vendedor->can('tickets.vender_sin_fecha'));

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $data = $this->pendingSaleData(originId: $oran->id, destinationId: $salta->id);

        try {
            $this->invokeHandleRecordCreation($data);
            $this->fail('La venta sin fecha sin permiso debió ser rechazada.');
        } catch (\Filament\Support\Exceptions\Halt $e) {
            // OK: el guard hace $this->halt().
        }

        $this->assertSame(0, Sale::count(), 'La venta no debió crearse.');
        $this->assertSame(0, Ticket::count(), 'Ningún boleto debió emitirse.');
        $this->assertSame(0, Passenger::count(), 'Ningún pasajero debió crearse.');
    }

    public function test_usuario_con_permiso_puede_vender_sin_fecha(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);
        $this->assertTrue($seller->can('tickets.vender_sin_fecha'));

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        // Colectivo obligatorio en toda venta (no hace falta ruta: no hay viaje).
        $bus = Bus::create(['name' => 'Linea U', 'plate' => 'UUU111', 'seat_count' => 4, 'floors' => 1]);

        $ticket = $this->invokeHandleRecordCreation($this->pendingSaleData(
            originId: $oran->id,
            destinationId: $salta->id,
            busId: $bus->id,
        ));

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertNotNull($ticket->id);
        $this->assertSame($bus->id, $ticket->bus_id, 'El boleto pendiente debe acarrear el colectivo elegido.');
    }

    // ====================== Feature flag: visibilidad de los switches ======================

    public function test_los_switches_sin_fecha_solo_se_activan_si_algun_rol_tiene_el_permiso(): void
    {
        \App\Support\Permissions::syncToDatabase(); // ningún rol del sistema lo trae por defecto
        \App\Support\Permissions::forgetFeatureFlagCache();

        // Permiso creado pero asignado a ningún rol => feature apagada.
        $this->assertFalse(\App\Support\Permissions::permissionGrantedToAnyRole('tickets.vender_sin_fecha'));

        // En cuanto un rol lo recibe desde la matriz, la feature se enciende.
        $admin = Role::findByName(\App\Support\Permissions::ROLE_ADMIN);
        $admin->givePermissionTo('tickets.vender_sin_fecha');
        \App\Support\Permissions::forgetFeatureFlagCache();

        $this->assertTrue(\App\Support\Permissions::permissionGrantedToAnyRole('tickets.vender_sin_fecha'));
    }

    // ====================== Colectivo obligatorio ======================

    public function test_la_venta_exige_colectivo_incluso_sin_fecha(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        // Sin bus_id: el wizard ya no lo permite y el server-side lo rechaza.
        $data = $this->pendingSaleData(originId: $oran->id, destinationId: $salta->id);

        try {
            $this->invokeHandleRecordCreation($data);
            $this->fail('La venta sin colectivo debió ser rechazada.');
        } catch (\Filament\Support\Exceptions\Halt $e) {
            // OK: el guard hace $this->halt().
        }

        $this->assertSame(0, Sale::count(), 'La venta no debió crearse sin colectivo.');
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, Passenger::count());
    }

    public function test_al_asignar_fecha_no_se_aceptan_horarios_de_otro_colectivo(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        // Colectivo A (el de la venta) y colectivo B (competidor con la misma ruta).
        $busA = Bus::create(['name' => 'Linea S', 'plate' => 'SSS111', 'seat_count' => 4, 'floors' => 1]);
        $busB = Bus::create(['name' => 'Linea X', 'plate' => 'XXX111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([$busA, $busB] as $bus) {
            foreach ([1, 2, 3, 4] as $n) {
                Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
            }
        }

        $routeA = Route::create(['name' => 'Orán - Salta S', 'bus_id' => $busA->id, 'is_active' => true]);
        $routeB = Route::create(['name' => 'Orán - Salta X', 'bus_id' => $busB->id, 'is_active' => true]);
        foreach ([$routeA, $routeB] as $route) {
            RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
            RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);
        }

        $scheduleA = Schedule::create([
            'route_id' => $routeA->id,
            'name' => 'Mañana S',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
        $scheduleB = Schedule::create([
            'route_id' => $routeB->id,
            'name' => 'Mañana X',
            'departure_time' => '09:00',
            'arrival_time' => '13:00',
            'is_active' => true,
        ]);

        $ticket = $this->invokeHandleRecordCreation($this->pendingSaleData(
            originId: $oran->id,
            destinationId: $salta->id,
            busId: $busA->id,
        ));
        $this->assertNull($ticket->trip_id);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $seatA = Seat::where('bus_id', $busA->id)->where('seat_number', '1')->first();

        // El horario del colectivo B no es válido para un boleto del colectivo A.
        try {
            app(TicketRescheduleService::class)->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $scheduleB->id,
                'seat_id' => $seatA->id,
            ]);
            $this->fail('El horario de otro colectivo debió ser rechazado.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('schedule_id', $e->errors());
        }

        $ticket->refresh();
        $this->assertNull($ticket->trip_id, 'El boleto debe seguir pendiente tras el rechazo.');

        // Con un horario del propio colectivo, la asignación progresa.
        app(TicketRescheduleService::class)->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $scheduleA->id,
            'seat_id' => $seatA->id,
        ]);

        $ticket->refresh();
        $this->assertNotNull($ticket->trip_id);
        $this->assertSame($busA->id, $ticket->trip->bus_id);
    }

    // ====================== Venta sólo ida sin fecha ======================

    public function test_venta_solo_ida_sin_fecha_crea_boleto_pendiente(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        // Colectivo obligatorio en toda venta (no hace falta ruta: no hay viaje).
        $bus = Bus::create(['name' => 'Linea T', 'plate' => 'TTT111', 'seat_count' => 4, 'floors' => 1]);

        $ticket = $this->invokeHandleRecordCreation($this->pendingSaleData(
            originId: $oran->id,
            destinationId: $salta->id,
            busId: $bus->id,
        ));

        // Boleto pendiente: sin viaje y sin asiento.
        $this->assertNull($ticket->trip_id);
        $this->assertNull($ticket->seat_id);
        $this->assertFalse((bool) $ticket->is_round_trip);
        $this->assertFalse((bool) $ticket->is_return_leg);
        $this->assertSame($bus->id, $ticket->bus_id);
        $this->assertTrue($ticket->isPendingDate());
        $this->assertFalse($ticket->isReturnLeg());

        // Origen/destino quedaron guardados para la asignación posterior.
        $this->assertSame($oran->id, $ticket->origin_location_id);
        $this->assertSame($salta->id, $ticket->destination_location_id);
        $this->assertEquals(15000.0, (float) $ticket->price);
        $this->assertSame('efectivo', $ticket->payment_method);

        // Venta y pasajero coherentes.
        $sale = $ticket->sale;
        $this->assertEquals(15000.0, (float) $sale->total_amount);
        $this->assertSame(1, $sale->tickets()->count());
        $this->assertSame(1, Passenger::count());
        $this->assertSame('Pérez', $ticket->passenger->last_name);

        // No se crearon viajes ni reservas.
        $this->assertSame(0, Trip::count());
        $this->assertSame(0, SeatReservation::count());
    }

    // ====================== Modo mixto ======================

    public function test_venta_mixta_ida_con_fecha_y_vuelta_sin_fecha(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea M', 'plate' => 'MMM111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta M', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana M',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $idaDate = '2026-10-02';
        $trip = Trip::findOrCreateForBooking($schedule->id, $idaDate, $oran->id, $salta->id)['trip'];
        $this->assertNotNull($trip);
        $seat1 = Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first();

        $data = $this->pendingSaleData(originId: $oran->id, destinationId: $salta->id, busId: $bus->id);
        $data['sell_without_date_ida'] = null;            // Ida CON fecha
        $data['sell_without_date_vuelta'] = true;         // Vuelta sin fecha
        $data['is_round_trip'] = true;
        $data['trip_id'] = $trip->id;
        $data['seat_ids'] = [$seat1->id];
        $data['return_trip_id'] = null;
        $data['return_seat_ids'] = [];

        $outbound = $this->invokeHandleRecordCreation($data);

        $sale = $outbound->sale;
        $tickets = $sale->tickets()->orderBy('id')->get();
        $this->assertCount(2, $tickets);

        $ida = $tickets->first();
        $vuelta = $tickets->last();

        // Ida: flujo normal, viaje y asiento reales, precio propio.
        $this->assertSame($trip->id, $ida->trip_id);
        $this->assertNotNull($ida->seat_id);
        $this->assertTrue((bool) $ida->is_round_trip);
        $this->assertNull($ida->return_trip_id);
        $this->assertFalse((bool) $ida->is_return_leg);
        $this->assertEquals(15000.0, (float) $ida->price);

        // Vuelta: pendiente, marcada explícita como tramo de vuelta.
        $this->assertNull($vuelta->trip_id);
        $this->assertNull($vuelta->seat_id);
        $this->assertTrue((bool) $vuelta->is_round_trip);
        $this->assertTrue((bool) $vuelta->is_return_leg);
        $this->assertTrue($vuelta->isReturnLeg());
        $this->assertTrue($vuelta->isPendingDate());
        $this->assertEquals(0.0, (float) $vuelta->price);
        // Dirección invertida (regreso).
        $this->assertSame($salta->id, $vuelta->origin_location_id);
        $this->assertSame($oran->id, $vuelta->destination_location_id);

        // El precio de la vuelta se muestra vía el boleto de ida.
        $this->assertEquals(15000.0, $vuelta->display_price);
        $this->assertEquals(15000.0, (float) $sale->total_amount);
    }

    // ====================== Asignación posterior ======================

    public function test_asignar_fecha_al_pendiente_lo_mueve_al_viaje_y_exige_asiento(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea N', 'plate' => 'NNN111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta N', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana N',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $ticket = $this->invokeHandleRecordCreation($this->pendingSaleData(
            originId: $oran->id,
            destinationId: $salta->id,
            busId: $bus->id,
        ));
        $this->assertNull($ticket->trip_id);

        // La asignación posterior la ejecuta un administrador (tickets.reschedule).
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $seat2 = Seat::where('bus_id', $bus->id)->where('seat_number', '2')->first();

        // Sin asiento NO se asigna fecha (asiento obligatorio para pendientes).
        try {
            app(TicketRescheduleService::class)->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $schedule->id,
                'seat_id' => null,
            ]);
            $this->fail('La asignación sin asiento debió fallar.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('seat_id', $e->errors());
        }

        // Con asiento se asigna fecha, horario y asiento; queda auditoría.
        $result = app(TicketRescheduleService::class)->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $schedule->id,
            'seat_id' => $seat2->id,
        ]);

        $ticket->refresh();
        $newTrip = Trip::query()->whereDate('trip_date', '2026-10-05')->where('schedule_id', $schedule->id)->first();

        $this->assertNotNull($newTrip);
        $this->assertSame($newTrip->id, $ticket->trip_id);
        $this->assertSame($seat2->id, $ticket->seat_id);
        $this->assertFalse($ticket->isPendingDate());
        $this->assertSame(1, $ticket->dateChanges()->count());
        $this->assertSame(1, $result['ticket']->id);

        // La fila de auditoría registra el viaje de origen NULL (pendiente).
        $change = $ticket->dateChanges()->first();
        $this->assertNull($change->from_trip_id);
        $this->assertSame($newTrip->id, $change->to_trip_id);
    }

    public function test_asignacion_posterior_exige_permiso_tickets_reschedule(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea O', 'plate' => 'OOO111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta O', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana O',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $ticket = $this->invokeHandleRecordCreation($this->pendingSaleData(
            originId: $oran->id,
            destinationId: $salta->id,
            busId: $bus->id,
        ));

        $seat1 = Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first();

        // Un vendedor sin tickets.reschedule NO puede asignar la fecha.
        try {
            app(TicketRescheduleService::class)->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $schedule->id,
                'seat_id' => $seat1->id,
            ]);
            $this->fail('Un vendedor sin tickets.reschedule no debió poder asignar la fecha.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('user', $e->errors());
        }

        $ticket->refresh();
        $this->assertNull($ticket->trip_id, 'El boleto debe seguir pendiente.');
    }

    // ====================== Regresión: venta con fecha intacta ======================

    public function test_la_venta_con_fecha_sigue_funcionando_sin_permiso_extra(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        // Vendedor SIN el permiso nuevo: la venta con fecha debe seguir andando.
        $seller = User::factory()->vendedor()->create();
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea R', 'plate' => 'RRR111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta R', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana R',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::findOrCreateForBooking($schedule->id, '2026-10-02', $oran->id, $salta->id)['trip'];
        $this->assertNotNull($trip);
        $seat1 = Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first();

        $data = $this->pendingSaleData(originId: $oran->id, destinationId: $salta->id, busId: $bus->id);
        $data['sell_without_date_ida'] = null;
        $data['sell_without_date_vuelta'] = null;
        $data['trip_id'] = $trip->id;
        $data['seat_ids'] = [$seat1->id];

        $ticket = $this->invokeHandleRecordCreation($data);

        $this->assertSame($trip->id, $ticket->trip_id);
        $this->assertSame($seat1->id, $ticket->seat_id);
        $this->assertFalse($ticket->isPendingDate());
        $this->assertEquals(15000.0, (float) $ticket->sale->total_amount);
    }

    public function test_el_toggle_apagado_false_vende_con_fecha_sin_permiso_extra(): void
    {
        // Regresión: Livewire deshidrata el toggle apagado como `false`, y
        // filled(false) es true en Laravel (los booleans nunca son "blank"):
        // con casts incorrectos, ventas normales se emitían SIN fecha.
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        // Vendedor SIN el permiso nuevo: con el switch en false no debe pedirlo.
        $seller = User::factory()->vendedor()->create();
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea F', 'plate' => 'FFF111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta F', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana F',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::findOrCreateForBooking($schedule->id, '2026-10-02', $oran->id, $salta->id)['trip'];
        $this->assertNotNull($trip);
        $seat1 = Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first();

        $data = $this->pendingSaleData(originId: $oran->id, destinationId: $salta->id, busId: $bus->id);
        $data['sell_without_date_ida'] = false;   // Toggle apagado, como lo deshidrata Livewire.
        $data['sell_without_date_vuelta'] = false;
        $data['trip_id'] = $trip->id;
        $data['seat_ids'] = [$seat1->id];

        $ticket = $this->invokeHandleRecordCreation($data);

        $this->assertSame($trip->id, $ticket->trip_id);
        $this->assertSame($seat1->id, $ticket->seat_id);
        $this->assertFalse($ticket->isPendingDate());
        $this->assertEquals(15000.0, (float) $ticket->sale->total_amount);
    }

    // ====================== Venta mixta invertida: ida sin fecha + vuelta con fecha ======================

    public function test_venta_mixta_ida_sin_fecha_y_vuelta_con_fecha_liga_el_tramo_de_vuelta(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea H', 'plate' => 'HHH111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        // Cada dirección con su propia ruta/horario (el segmento inverso no es
        // válido en la ruta de ida) sobre el mismo colectivo.
        $routeIda = Route::create(['name' => 'Orán - Salta H', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeIda->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeIda->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $routeVuelta = Route::create(['name' => 'Salta - Orán H', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $salta->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $oran->id, 'stop_order' => 2]);

        $scheduleIda = Schedule::create([
            'route_id' => $routeIda->id,
            'name' => 'Mañana H',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
        $scheduleVuelta = Schedule::create([
            'route_id' => $routeVuelta->id,
            'name' => 'Tarde H',
            'departure_time' => '14:00',
            'arrival_time' => '18:00',
            'is_active' => true,
        ]);

        $vueltaDate = '2026-10-10';
        $returnTrip = Trip::findOrCreateForBooking($scheduleVuelta->id, $vueltaDate, $salta->id, $oran->id)['trip'];
        $this->assertNotNull($returnTrip);
        $returnSeat = Seat::where('bus_id', $bus->id)->where('seat_number', '2')->first();

        $data = $this->pendingSaleData(originId: $oran->id, destinationId: $salta->id, busId: $bus->id);
        $data['sell_without_date_ida'] = true;      // Ida sin fecha
        $data['sell_without_date_vuelta'] = null;   // Vuelta CON fecha
        $data['is_round_trip'] = true;
        $data['trip_id'] = null;
        $data['seat_ids'] = [];
        $data['return_trip_id'] = $returnTrip->id;
        $data['return_seat_ids'] = [$returnSeat->id];

        $first = $this->invokeHandleRecordCreation($data);

        $sale = $first->sale;
        $tickets = $sale->tickets()->orderBy('id')->get();
        $this->assertCount(2, $tickets);

        $ida = $tickets->first();
        $vuelta = $tickets->last();

        // Ida pendiente PERO ligada al viaje de vuelta (bug corregido).
        $this->assertNull($ida->trip_id);
        $this->assertTrue((bool) $ida->is_round_trip);
        $this->assertFalse((bool) $ida->is_return_leg);
        $this->assertSame($returnTrip->id, (int) $ida->return_trip_id);
        $this->assertNotNull($ida->returnTrip);
        $this->assertSame($vueltaDate, $ida->returnTrip->trip_date->format('Y-m-d'));
        $this->assertEquals(15000.0, (float) $ida->price);

        // Vuelta con su viaje y asiento reales.
        $this->assertSame($returnTrip->id, $vuelta->trip_id);
        $this->assertSame($returnSeat->id, $vuelta->seat_id);
        $this->assertTrue((bool) $vuelta->is_return_leg);
        $this->assertFalse($vuelta->isPendingDate());
        $this->assertEquals(0.0, (float) $vuelta->price);
        $this->assertSame($salta->id, $vuelta->origin_location_id);
        $this->assertSame($oran->id, $vuelta->destination_location_id);

        // El viaje de ida queda disponible en el form de reprogramación (scope both).
        $this->assertTrue((bool) $ida->is_round_trip && ! is_null($ida->return_trip_id));
    }

    public function test_reprogramar_la_ida_no_puede_quedar_posterior_a_la_vuelta(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea G', 'plate' => 'GGG111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $routeIda = Route::create(['name' => 'Orán - Salta G', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeIda->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeIda->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $routeVuelta = Route::create(['name' => 'Salta - Orán G', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $salta->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $oran->id, 'stop_order' => 2]);

        $scheduleIda = Schedule::create([
            'route_id' => $routeIda->id,
            'name' => 'Mañana G',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
        $scheduleVuelta = Schedule::create([
            'route_id' => $routeVuelta->id,
            'name' => 'Tarde G',
            'departure_time' => '14:00',
            'arrival_time' => '18:00',
            'is_active' => true,
        ]);

        $vueltaDate = '2026-10-10';
        $returnTrip = Trip::findOrCreateForBooking($scheduleVuelta->id, $vueltaDate, $salta->id, $oran->id)['trip'];
        $returnSeat = Seat::where('bus_id', $bus->id)->where('seat_number', '2')->first();

        $data = $this->pendingSaleData(originId: $oran->id, destinationId: $salta->id, busId: $bus->id);
        $data['sell_without_date_ida'] = true;
        $data['sell_without_date_vuelta'] = null;
        $data['is_round_trip'] = true;
        $data['trip_id'] = null;
        $data['seat_ids'] = [];
        $data['return_trip_id'] = $returnTrip->id;
        $data['return_seat_ids'] = [$returnSeat->id];

        $ida = $this->invokeHandleRecordCreation($data);
        $this->assertNull($ida->trip_id);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $idaSeat = Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first();

        // Fecha de ida POSTERIOR a la vuelta: se rechaza por orden.
        try {
            app(TicketRescheduleService::class)->reschedule($ida, [
                'scope' => 'outbound',
                'date' => '2026-10-15',
                'schedule_id' => $scheduleIda->id,
                'seat_id' => $idaSeat->id,
            ]);
            $this->fail('La fecha de ida posterior a la vuelta debió ser rechazada.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('return_schedule_id', $e->errors());
        }

        $ida->refresh();
        $this->assertNull($ida->trip_id, 'La ida debe seguir pendiente tras el rechazo.');

        // Fecha de ida ANTERIOR a la vuelta: se asigna y el link se mantiene.
        app(TicketRescheduleService::class)->reschedule($ida, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $scheduleIda->id,
            'seat_id' => $idaSeat->id,
        ]);

        $ida->refresh();
        $this->assertNotNull($ida->trip_id);
        $this->assertSame('2026-10-05', $ida->trip->trip_date->format('Y-m-d'));
        $this->assertSame($returnTrip->id, (int) $ida->return_trip_id);
    }

    public function test_el_comando_repara_el_link_de_una_venta_mixta_ya_emitida(): void
    {
        // Emula datos emitidos ANTES del fix: ida pendiente con return_trip_id
        // NULL y su hermana de vuelta ya con viaje asignado.
        $seller = User::factory()->vendedor()->create();
        $this->actingAs($seller);

        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea P', 'plate' => 'PPP111', 'seat_count' => 4, 'floors' => 1]);

        $routeVuelta = Route::create(['name' => 'Salta - Orán P', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $salta->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeVuelta->id, 'location_id' => $oran->id, 'stop_order' => 2]);

        $scheduleVuelta = Schedule::create([
            'route_id' => $routeVuelta->id,
            'name' => 'Tarde P',
            'departure_time' => '14:00',
            'arrival_time' => '18:00',
            'is_active' => true,
        ]);

        $returnTrip = Trip::findOrCreateForBooking($scheduleVuelta->id, '2026-10-10', $salta->id, $oran->id)['trip'];

        $sale = Sale::createNew($seller->id);
        $passenger = Passenger::create([
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'dni' => '30111222',
            'passenger_type' => 'adult',
        ]);

        $ida = $sale->addTicket([
            'trip_id' => null,
            'return_trip_id' => null, // El bug: nunca se escribió.
            'passenger_id' => $passenger->id,
            'bus_id' => $bus->id,
            'is_round_trip' => true,
            'is_return_leg' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 15000,
        ]);

        $returnLeg = $sale->addTicket([
            'trip_id' => $returnTrip->id,
            'return_trip_id' => null,
            'passenger_id' => $passenger->id,
            'bus_id' => $bus->id,
            'is_round_trip' => true,
            'is_return_leg' => true,
            'origin_location_id' => $salta->id,
            'destination_location_id' => $oran->id,
            'price' => 0,
        ]);

        $this->assertNull($ida->return_trip_id);

        Artisan::call('tickets:repair-round-trip-links');

        $ida->refresh();
        $this->assertSame($returnTrip->id, (int) $ida->return_trip_id);
        $this->assertSame($returnTrip->id, $returnLeg->fresh()->trip_id, 'El tramo de vuelta no se toca.');

        // Idempotente: una segunda corrida no cambia nada.
        Artisan::call('tickets:repair-round-trip-links');
        $ida->refresh();
        $this->assertSame($returnTrip->id, (int) $ida->return_trip_id);
    }

    // ====================== Helpers ======================

    /**
     * Invoca CreateTicket::handleRecordCreation (protected) con el payload dado,
     * simulando el submit del wizard sin montar todo el formulario Livewire.
     */
    private function invokeHandleRecordCreation(array $data): Ticket
    {
        $page = app(CreateTicket::class);

        $method = new \ReflectionMethod(CreateTicket::class, 'handleRecordCreation');
        $method->setAccessible(true);

        return $method->invoke($page, $data);
    }

    /**
     * Payload mínimo del wizard para una venta de 1 adulto sin fecha de ida.
     * El colectivo ahora es obligatorio en toda venta (bus_id requerido).
     */
    private function pendingSaleData(?int $originId = null, ?int $destinationId = null, ?int $busId = null): array
    {
        return [
            'bus_id' => $busId,
            'sell_without_date_ida' => true,
            'sell_without_date_vuelta' => null,
            'passengers_count' => '1',
            'passengers' => [
                [
                    'first_name' => 'Juan',
                    'last_name' => 'Pérez',
                    'dni' => '12345678',
                    'phone_number' => '3881234567',
                    'email' => null,
                    'travels_with_child' => false,
                    'travels_with_pets' => false,
                    'price' => 15000,
                    'payment_method' => 'efectivo',
                ],
            ],
            'origin_location_id' => $originId,
            'destination_location_id' => $destinationId,
            'is_round_trip' => false,
            'trip_id' => null,
            'seat_ids' => [],
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ];
    }
}
