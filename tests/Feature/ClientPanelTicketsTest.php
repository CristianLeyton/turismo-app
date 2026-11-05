<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\Auth\ClientLogin;
use App\Filament\Client\Pages\MyTickets;
use App\Models\Bus;
use App\Models\Clients;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route as BusRoute;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDateChange;
use App\Models\Trip;
use App\Models\User;
use App\Services\ClientTicketAssignmentService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel público de clientes (/clientes):
 * - ingreso con DNI y contraseña (login de Filament, guard client),
 * - boletos delimitados por DNI (sólo los propios),
 * - asignación de primera fecha/horario/asiento a boletos pendientes,
 * - bloqueo tras asignar (sólo el personal reprograma),
 * - PDF sólo del propio boleto,
 * - auditoría en TicketDateChange con user_id null (no hubo usuario admin).
 */
class ClientPanelTicketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        Cache::flush(); // El rate limiter de identificación usa la caché

        // El panel de clientes viene apagado por defecto.
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
    }

    private function createClient(string $dni, string $nombre = 'Ana'): Clients
    {
        // Cuenta con contraseña ya elegida (estado post-primer ingreso). El
        // flujo de la clave inicial (DNI) tiene sus tests dedicados en
        // ClientPanelProfileTest.
        return Clients::firstOrCreate(['dni' => $dni], [
            'dni' => $dni,
            'nombre' => $nombre,
            'apellido' => 'García',
            'telefono' => '3811111111',
            'password' => 'clave-prueba', // cast 'hashed': se guarda bcrypt
            'must_change_password' => false, // ya eligió su propia clave
        ]);
    }

    /** Grema: cliente garantizado del panel, como tras elegir su password. */
    private function createPendingTicket(string $dni, int $n = 1): array
    {
        // Locations, bus y rutas tienen nombre/plate únicos: un test puede
        // crear dos clientes y sus fixtures no deben chocar entre sí.
        $tartagal = Location::firstOrCreate(['name' => 'Tartagal '.$dni], ['is_active' => true]);
        $urundel = Location::firstOrCreate(['name' => 'Urundel '.$dni], ['is_active' => true]);

        $bus = Bus::create(['name' => "Linea SF {$dni}-{$n}", 'plate' => 'SF'.abs(crc32($dni.$n)), 'seat_count' => 4, 'floors' => 1]);

        $route = BusRoute::create(['name' => "Ruta {$dni}-{$n}", 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $tartagal->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $urundel->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana',
            'departure_time' => '08:00',
            'arrival_time' => '09:00',
            'is_active' => true,
        ]);

        foreach ([1, 2, 3] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => $n, 'floor' => '1', 'is_active' => true]);
        }

        $client = $this->createClient($dni);

        $passenger = Passenger::create([
            'first_name' => 'Pasajero',
            'last_name' => 'García',
            'dni' => $dni,
            'passenger_type' => 'adult',
        ]);

        $user = User::factory()->vendedor()->create();

        $sale = Sale::create([
            'user_id' => $user->id,
            'sale_date' => now(),
            'total_amount' => 15000,
        ]);

        $ticket = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => null,
            'seat_id' => null,
            'return_trip_id' => null,
            'bus_id' => $bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $tartagal->id,
            'destination_location_id' => $urundel->id,
            'price' => 15000,
        ]);

        return [
            'client' => $client,
            'ticket' => $ticket,
            'schedule' => $schedule,
            'route' => $route,
            'bus' => $bus,
            'origin' => $tartagal,
            'destination' => $urundel,
        ];
    }

    private function identify(string $dni): void
    {
        $this->actingAs(Clients::query()->where('dni', $dni)->firstOrFail(), 'client');
    }

    // ------------------------------------------------------------------
    // Ingreso (login de Filament, guard client)
    // ------------------------------------------------------------------

    public function test_sin_sesion_el_panel_redirige_al_ingreso(): void
    {
        $this->get('/clientes')->assertRedirect('/clientes/login');
        $this->get('/clientes/login')->assertOk()->assertSeeText('DNI');
    }

    public function test_la_direccion_anterior_redirige_al_login(): void
    {
        $this->get('/clientes/ingresar')->assertRedirect('/clientes/login');
    }

    public function test_dni_y_contraseña_correctos_inician_sesion_y_van_a_mis_boletos(): void
    {
        $client = $this->createClient('11223344');

        $this->clientLogin()
            ->fillForm(['dni' => '11.223.344', 'password' => 'clave-prueba']) // normaliza puntos
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(MyTickets::getUrl(panel: 'clientes'));

        $this->assertAuthenticatedAs($client, 'client');
        $this->assertGuest('web');
    }

    public function test_dni_desconocido_y_contraseña_incorrecta_dan_el_mismo_error(): void
    {
        $this->createClient('11223344');

        $this->clientLogin()
            ->fillForm(['dni' => '99999999', 'password' => 'clave-prueba'])
            ->call('authenticate')
            ->assertHasFormErrors(['dni' => 'El DNI o la contraseña son incorrectos.']);

        $this->clientLogin()
            ->fillForm(['dni' => '11223344', 'password' => 'otra-clave'])
            ->call('authenticate')
            ->assertHasFormErrors(['dni' => 'El DNI o la contraseña son incorrectos.']);

        $this->assertGuest('client');
    }

    public function test_sin_contraseña_no_inicia_sesion(): void
    {
        $this->createClient('11223344');

        $this->clientLogin()
            ->fillForm(['dni' => '11223344', 'password' => ''])
            ->call('authenticate')
            ->assertHasFormErrors(['password' => 'required']);

        $this->assertGuest('client');
    }

    public function test_el_limite_de_intentos_bloquea_incluso_la_contraseña_correcta(): void
    {
        $this->createClient('11223344');

        for ($i = 0; $i < 5; $i++) {
            $this->clientLogin()
                ->fillForm(['dni' => '11223344', 'password' => 'clave-errada-'.$i])
                ->call('authenticate');
        }

        $this->clientLogin()
            ->fillForm(['dni' => '11223344', 'password' => 'clave-prueba'])
            ->call('authenticate')
            ->assertNotified();

        $this->assertGuest('client');
    }

    public function test_un_cliente_dado_de_baja_no_inicia_sesion(): void
    {
        $this->createClient('11223344')->delete();

        $this->clientLogin()
            ->fillForm(['dni' => '11223344', 'password' => 'clave-prueba'])
            ->call('authenticate')
            ->assertHasFormErrors(['dni']);

        $this->assertGuest('client');
    }

    private function clientLogin(): \Livewire\Features\SupportTesting\Testable
    {
        Filament::setCurrentPanel('clientes');

        return Livewire::test(ClientLogin::class);
    }

    // Scoping por DNI
    // ------------------------------------------------------------------

    public function test_el_cliente_ve_solo_sus_boletos(): void
    {
        $a = $this->createPendingTicket('11223344');
        $b = $this->createPendingTicket('55667788');

        $this->identify($a['client']->dni);

        $ids = ClientTicketAssignmentService::ticketsForCurrentClient()->pluck('id');

        $this->assertTrue($ids->contains($a['ticket']->id));
        $this->assertFalse($ids->contains($b['ticket']->id));

        // El dashboard renderiza con el cliente identificado.
        Livewire::test(MyTickets::class)->assertSuccessful();
    }

    public function test_sin_sesion_la_query_de_boletos_esta_vacia(): void
    {
        $this->createPendingTicket('11223344');

        $this->assertSame(0, ClientTicketAssignmentService::ticketsForCurrentClient()->count());
    }

    // ------------------------------------------------------------------
    // Asignación de fecha (ida simple)
    // ------------------------------------------------------------------

    public function test_asignar_fecha_guarda_viaje_asiento_y_historial(): void
    {
        $f = $this->createPendingTicket('11223344');
        $seat = Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first();

        $this->identify($f['client']->dni);

        app(ClientTicketAssignmentService::class)->assign($f['ticket'], [
            'date' => '2026-10-05',
            'schedule_id' => $f['schedule']->id,
            'seat_id' => $seat->id,
        ]);

        $ticket = $f['ticket']->fresh();

        $this->assertNotNull($ticket->trip_id);
        $this->assertSame($seat->id, $ticket->seat_id);
        $this->assertSame('2026-10-05', $ticket->trip->trip_date->format('Y-m-d'));
        $this->assertSame($f['schedule']->id, $ticket->trip->schedule_id);

        $change = TicketDateChange::query()->where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($change);
        $this->assertSame(TicketDateChange::LEG_OUTBOUND, $change->leg);
        $this->assertNull($change->user_id); // asignó un cliente, no un admin
        $this->assertNull($change->from_trip_id);
        $this->assertSame($ticket->trip_id, $change->to_trip_id);
    }

    public function test_tras_asignar_el_cliente_queda_bloqueado(): void
    {
        $f = $this->createPendingTicket('11223344');
        $seat = Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first();

        $this->identify($f['client']->dni);

        app(ClientTicketAssignmentService::class)->assign($f['ticket'], [
            'date' => '2026-10-05',
            'schedule_id' => $f['schedule']->id,
            'seat_id' => $seat->id,
        ]);

        // El estado previo al bloqueo: el boleto ya NO es pendiente.
        $this->assertNotNull($f['ticket']->fresh()->trip_id);

        $this->expectException(ValidationException::class);

        app(ClientTicketAssignmentService::class)->assign($f['ticket'], [
            'date' => '2026-10-06',
            'schedule_id' => $f['schedule']->id,
            'seat_id' => $seat->id,
        ]);
    }

    public function test_un_cliente_no_puede_asignar_el_boleto_de_otro(): void
    {
        $a = $this->createPendingTicket('11223344');
        $b = $this->createPendingTicket('55667788');

        $this->identify($b['client']->dni);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Boleto no encontrado');

        app(ClientTicketAssignmentService::class)->assign($a['ticket'], [
            'date' => '2026-10-05',
            'schedule_id' => $b['schedule']->id,
            'seat_id' => Seat::where('bus_id', $b['bus']->id)->first()->id,
        ]);
    }

    public function test_no_se_permite_un_dia_sin_servicio_aunque_haya_un_superadmin_logueado(): void
    {
        $f = $this->createPendingTicket('11223344');

        $this->identify($f['client']->dni);

        // Un superadmin del panel de administración en el mismo navegador:
        // su permiso no aplica en el panel de clientes.
        $this->actingAs(\App\Models\User::factory()->superAdmin()->create(), 'web');
        $this->assertTrue(\App\Support\ServiceDays::userCanBypass());

        try {
            // Domingo 04/10/2026: sin servicio con la configuración inicial.
            app(ClientTicketAssignmentService::class)->assign($f['ticket'], [
                'date' => '2026-10-04',
                'schedule_id' => $f['schedule']->id,
                'seat_id' => Seat::where('bus_id', $f['bus']->id)->first()->id,
            ]);
            $this->fail('Debió rechazar el día sin servicio.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('04/10/2026 es un día sin servicio', $e->errors()['date'][0]);
        }

        $this->assertNull($f['ticket']->fresh()->trip_id);
    }

    public function test_el_calendario_del_cliente_siempre_deshabilita_los_dias_sin_servicio(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->actingAs(\App\Models\User::factory()->superAdmin()->create());

        $disabled = collect(\App\Filament\Client\Schemas\ClientTicketAssignForm::make($f['ticket']))
            ->first(fn ($component) => $component->getName() === 'date')
            ->getDisabledDates();

        $this->assertContains('2026-10-04', $disabled);
        $this->assertNotContains('2026-10-05', $disabled);
    }

    public function test_el_cliente_no_puede_elegir_un_viaje_donde_ya_tiene_boleto(): void
    {
        $f = $this->createPendingTicket('11223344');

        $trip = Trip::create([
            'route_id' => $f['route']->id,
            'schedule_id' => $f['schedule']->id,
            'bus_id' => $f['bus']->id,
            'trip_date' => '2026-10-05',
        ]);
        $otherPassenger = Passenger::create(['first_name' => 'Pasajero', 'last_name' => 'García', 'dni' => '11223344', 'passenger_type' => 'adult']);
        Ticket::create([
            'sale_id' => $f['ticket']->sale_id,
            'trip_id' => $trip->id,
            'seat_id' => Seat::where('bus_id', $f['bus']->id)->where('seat_number', 3)->first()->id,
            'bus_id' => $f['bus']->id,
            'passenger_id' => $otherPassenger->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $f['origin']->id,
            'destination_location_id' => $f['destination']->id,
            'price' => 15000,
        ]);

        $this->identify($f['client']->dni);

        Livewire::test(MyTickets::class)
            ->callAction(TestAction::make('asignar_fecha')->table($f['ticket']), data: [
                'date' => '2026-10-05',
                'schedule_id' => $f['schedule']->id,
                'seat_ids' => [Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first()->id],
            ])
            ->assertNotified(
                \Filament\Notifications\Notification::make()
                    ->title('No se pudo asignar la fecha')
                    ->body(\App\Exceptions\DuplicatePassengerOnTripException::MESSAGE)
                    ->danger()
                    ->persistent(),
            );

        $this->assertNull($f['ticket']->fresh()->trip_id, 'El boleto sigue sin fecha.');
    }

    public function test_no_se_permite_fecha_pasada(): void
    {
        $f = $this->createPendingTicket('11223344');

        $this->identify($f['client']->dni);

        $this->expectException(ValidationException::class);

        app(ClientTicketAssignmentService::class)->assign($f['ticket'], [
            'date' => '2026-09-30',
            'schedule_id' => $f['schedule']->id,
            'seat_id' => Seat::where('bus_id', $f['bus']->id)->first()->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Ida y vuelta: ida y vuelta pendientes, se asignan por separado
    // ------------------------------------------------------------------

    /** Cliente con ida Y vuelta pendientes (dos tickets de la misma venta). */
    private function createRoundTripFailing(): array
    {
        $tartagal = Location::create(['name' => 'Tartagal-RT', 'is_active' => true]);
        $urundel = Location::create(['name' => 'Urundel-RT', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea RT', 'plate' => 'RTRT11', 'seat_count' => 4, 'floors' => 1]);

        $ida = BusRoute::create(['name' => 'Ida RT', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $ida->id, 'location_id' => $tartagal->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $ida->id, 'location_id' => $urundel->id, 'stop_order' => 2]);

        $vuelta = BusRoute::create(['name' => 'Vuelta RT', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $vuelta->id, 'location_id' => $urundel->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $vuelta->id, 'location_id' => $tartagal->id, 'stop_order' => 2]);

        $scheduleIda = Schedule::create([
            'route_id' => $ida->id, 'name' => 'Ida 08', 'departure_time' => '08:00',
            'arrival_time' => '09:00', 'is_active' => true,
        ]);
        $scheduleVuelta = Schedule::create([
            'route_id' => $vuelta->id, 'name' => 'Vuelta 20', 'departure_time' => '20:00',
            'arrival_time' => '21:00', 'is_active' => true,
        ]);

        foreach ([1, 2] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => $n, 'floor' => '1', 'is_active' => true]);
        }

        $client = $this->createClient('44556677', 'Luis');

        $passenger = Passenger::create([
            'first_name' => 'Luis',
            'last_name' => 'Pérez',
            'dni' => '44556677',
            'passenger_type' => 'adult',
        ]);

        $user = User::factory()->vendedor()->create();

        $sale = Sale::create(['user_id' => $user->id, 'sale_date' => now(), 'total_amount' => 20000]);

        $outbound = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => null,
            'seat_id' => null,
            'return_trip_id' => null,
            'bus_id' => $bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => false,
            'origin_location_id' => $tartagal->id,
            'destination_location_id' => $urundel->id,
            'price' => 20000,
        ]);

        $return = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => null,
            'seat_id' => null,
            'return_trip_id' => null,
            'bus_id' => $bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => true,
            'origin_location_id' => $urundel->id,
            'destination_location_id' => $tartagal->id,
            'price' => 0,
        ]);

        return [
            'client' => $client, 'outbound' => $outbound, 'return' => $return,
            'scheduleOutbound' => $scheduleIda, 'scheduleReturn' => $scheduleVuelta, 'bus' => $bus,
        ];
    }

    public function test_vuelta_pendiente_se_asigna_despues_de_la_ida(): void
    {
        $f = $this->createRoundTripFailing();

        $this->identify($f['client']->dni);

        $seat1 = Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first();

        $service = app(ClientTicketAssignmentService::class);

        $service->assign($f['outbound'], [
            'date' => '2026-10-05',
            'schedule_id' => $f['scheduleOutbound']->id,
            'seat_id' => $seat1->id,
        ]);

        $service->assign($f['return'], [
            'date' => '2026-10-06',
            'schedule_id' => $f['scheduleReturn']->id,
            'seat_id' => $seat1->id,
        ]);

        $outbound = $f['outbound']->fresh();
        $return = $f['return']->fresh();

        $this->assertNotNull($return->trip_id);
        $this->assertNotNull($outbound->return_trip_id);
        $this->assertSame($return->trip_id, $outbound->return_trip_id);

        // La vuelta también fue auditada, con leg = return.
        $this->assertTrue(TicketDateChange::query()
            ->where('ticket_id', $return->id)
            ->where('leg', TicketDateChange::LEG_RETURN)
            ->exists());

        // Y el boleto de vuelta asignado queda bloqueado para el cliente.
        $this->expectException(ValidationException::class);

        $service->assign($f['return'], [
            'date' => '2026-10-07',
            'schedule_id' => $f['scheduleReturn']->id,
            'seat_id' => $seat1->id,
        ]);
    }

    public function test_los_diferidos_se_identifican_con_su_tramo(): void
    {
        $f = $this->createRoundTripFailing();

        $this->identify($f['client']->dni);

        // La ida: badge "Ida". La vuelta: "Vuelta de N°X" con el boleto de ida.
        Livewire::test(MyTickets::class)
            ->assertSeeText('Ida')
            ->assertSeeText('Vuelta de N°'.$f['outbound']->id);
    }

    public function test_bajo_el_precio_de_la_vuelta_no_hay_aclaracion(): void
    {
        $f = $this->createRoundTripFailing();

        $this->identify($f['client']->dni);

        Livewire::test(MyTickets::class)
            ->assertSuccessful()
            ->assertDontSeeText('El precio se ve en el boleto');
    }

    public function test_un_boleto_simple_no_se_marca_con_tramo(): void
    {
        $f = $this->createPendingTicket('11223344');

        $this->identify($f['client']->dni);

        Livewire::test(MyTickets::class)
            ->assertDontSeeText('Vuelta de N°')
            ->assertSuccessful();
    }

    // ------------------------------------------------------------------
    // PDF
    // ------------------------------------------------------------------

    public function test_pdf_solo_del_boleto_propio(): void
    {
        $a = $this->createPendingTicket('11223344');

        // Sin sesión: prohibido.
        $this->get('/clientes/boletos/'.$a['ticket']->id.'/pdf')->assertForbidden();

        // Propio: PDF válido.
        $this->identify($a['client']->dni);
        $response = $this->get('/clientes/boletos/'.$a['ticket']->id.'/pdf');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        // Ajeno: 404, infalible para otro cliente.
        $b = $this->createPendingTicket('55667788');
        $this->identify($b['client']->dni);
        $this->get('/clientes/boletos/'.$a['ticket']->id.'/pdf')->assertNotFound();
    }

    // ------------------------------------------------------------------
    // "Ver boleto": link compartible o PDF según DESCARGA_AUTOMATICA_PDF
    // ------------------------------------------------------------------

    public function test_ver_boleto_con_el_parametro_desactivado_abre_el_link(): void
    {
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        $a = $this->createPendingTicket('11223344');
        $this->identify($a['client']->dni);

        $response = $this->get(route('clientes.boletos.show', ['ticket' => $a['ticket']->id]));

        $shareUrl = $a['ticket']->fresh()->shareUrl();
        $response->assertRedirect($shareUrl);
        $this->assertStringContainsString('/boletos/t_', $shareUrl);
    }

    public function test_ver_boleto_con_el_parametro_activado_abre_el_pdf(): void
    {
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '1');
        $a = $this->createPendingTicket('11223344');
        $this->identify($a['client']->dni);

        $this->get(route('clientes.boletos.show', ['ticket' => $a['ticket']->id]))
            ->assertRedirect(route('clientes.boletos.pdf', ['ticket' => $a['ticket']->id]));

        $this->assertNull($a['ticket']->fresh()->share_token, 'Con PDF no hace falta generar el token.');
    }

    public function test_ver_boleto_con_el_parametro_sin_configurar_abre_el_pdf(): void
    {
        $a = $this->createPendingTicket('11223344');
        $this->identify($a['client']->dni);

        $this->get(route('clientes.boletos.show', ['ticket' => $a['ticket']->id]))
            ->assertRedirect(route('clientes.boletos.pdf', ['ticket' => $a['ticket']->id]));
    }

    public function test_ver_boleto_ajeno_o_sin_sesion_no_muestra_nada(): void
    {
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        $a = $this->createPendingTicket('11223344');
        $b = $this->createPendingTicket('55667788');

        $this->get(route('clientes.boletos.show', ['ticket' => $a['ticket']->id]))->assertForbidden();

        $this->identify($b['client']->dni);
        $this->get(route('clientes.boletos.show', ['ticket' => $a['ticket']->id]))->assertNotFound();

        $this->assertNull($a['ticket']->fresh()->share_token, 'Ni sin sesión ni con un boleto ajeno se genera el link.');
    }

    public function test_ver_boleto_de_la_tabla_usa_la_ruta_del_panel_sin_generar_tokens(): void
    {
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        $a = $this->createPendingTicket('11223344');
        $this->identify($a['client']->dni);

        Livewire::test(MyTickets::class)
            ->assertActionHasUrl(
                TestAction::make('boleto_pdf')->table($a['ticket']),
                route('clientes.boletos.show', ['ticket' => $a['ticket']->id]),
            );

        $this->assertFalse(Ticket::query()->whereNotNull('share_token')->exists(), 'Listar no genera links.');
    }

    public function test_por_defecto_ve_solo_pendientes_y_el_switch_incluye_fechados(): void
    {
        $f = $this->createPendingTicket('11223344');

        // Fecha el primer boleto directamente (queda "Confirmado").
        $trip = Trip::create([
            'route_id' => $f['route']->id,
            'schedule_id' => $f['schedule']->id,
            'bus_id' => $f['bus']->id,
            'trip_date' => '2026-10-10',
        ]);
        $f['ticket']->update(['trip_id' => $trip->id, 'seat_id' => Seat::where('bus_id', $f['bus']->id)->first()->id]);

        // Y le agrega un segundo boleto pendiente.
        $other = $this->createPendingTicket('11223344', n: 2);

        $this->identify($f['client']->dni);

        // Por defecto: sólo pendientes (el fechado no aparece).
        Livewire::test(MyTickets::class)
            ->assertDontSeeText('Confirmado')
            // El switch los incluye.
            ->set('mostrarFechados', true)
            ->assertSeeText('Confirmado')
            ->assertSeeText('10/10/2026');

        // El pendiente sigue visible en ambos casos.
        Livewire::test(MyTickets::class)
            ->assertSeeText('Pendiente');
    }

    public function test_mis_boletos_no_tiene_un_salir_propio(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify($f['client']->dni);

        // "Salir" vive sólo en el menú de usuario.
        Livewire::test(MyTickets::class)->assertActionDoesNotExist('salir');
    }

    public function test_el_salir_del_menu_de_usuario_cierra_solo_la_sesion_del_cliente(): void
    {
        $f = $this->createPendingTicket('11223344');
        $admin = User::factory()->create();
        $this->actingAs($admin, 'web');
        $this->identify($f['client']->dni);

        $this->post('/clientes/salir')->assertRedirect('/clientes/login');

        $this->assertGuest('client');
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_el_menu_de_usuario_muestra_el_nombre_el_perfil_y_salir(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify($f['client']->dni);

        $this->get('/clientes')
            ->assertOk()
            ->assertSee('Ana García')
            ->assertSee(route('clientes.logout'), false)
            ->assertDontSee(route('filament.clientes.auth.logout'), false)
            ->assertSee('/clientes/perfil', false);
    }
}
