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
use Illuminate\Support\Arr;
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

    /**
     * Crea un boleto del cliente ya con fecha (y opcionalmente horario).
     *
     * @return array<string, mixed> fixture de createPendingTicket con 'ticket' fechado
     */
    private function createDatedTicket(string $dni, string $date, string $time = '08:00', int $n = 1): array
    {
        $f = $this->createPendingTicket($dni, $n);

        $f['schedule']->update(['departure_time' => $time]);

        $trip = Trip::create([
            'route_id' => $f['route']->id,
            'schedule_id' => $f['schedule']->id,
            'bus_id' => $f['bus']->id,
            'trip_date' => $date,
        ]);

        $f['ticket']->update([
            'trip_id' => $trip->id,
            'seat_id' => Seat::where('bus_id', $f['bus']->id)->first()->id,
        ]);

        $f['ticket'] = $f['ticket']->fresh();

        return $f;
    }

    public function test_cada_boleto_cae_en_una_sola_pestana(): void
    {
        $pendiente = $this->createPendingTicket('11223344', 1);
        $proximo = $this->createDatedTicket('11223344', '2026-10-10', n: 2);
        $hoy = $this->createDatedTicket('11223344', '2026-10-01', n: 3);
        $pasado = $this->createDatedTicket('11223344', '2026-09-20', n: 4);

        $this->identify('11223344');

        $ids = fn ($query) => $query->pluck('tickets.id')->all();

        $this->assertSame([$pendiente['ticket']->id], $ids(ClientTicketAssignmentService::pendingTicketsForCurrentClient()));
        $this->assertEqualsCanonicalizing(
            [$proximo['ticket']->id, $hoy['ticket']->id],
            $ids(ClientTicketAssignmentService::upcomingTicketsForCurrentClient()),
        );
        $this->assertSame([$pasado['ticket']->id], $ids(ClientTicketAssignmentService::pastTicketsForCurrentClient()));
    }

    public function test_un_boleto_eliminado_no_cuenta_en_ninguna_pestana(): void
    {
        $pendiente = $this->createPendingTicket('11223344', 1);
        $proximo = $this->createDatedTicket('11223344', '2026-10-10', n: 2);
        $pasado = $this->createDatedTicket('11223344', '2026-09-20', n: 3);

        foreach ([$pendiente, $proximo, $pasado] as $f) {
            $f['ticket']->delete();
        }

        $this->identify('11223344');

        $this->assertSame(0, ClientTicketAssignmentService::pendingTicketsForCurrentClient()->count());
        $this->assertSame(0, ClientTicketAssignmentService::upcomingTicketsForCurrentClient()->count());
        $this->assertSame(0, ClientTicketAssignmentService::pastTicketsForCurrentClient()->count());
        $this->assertNull(ClientTicketAssignmentService::nextTripTicketForCurrentClient());
    }

    public function test_las_pestanas_no_cuentan_boletos_de_otro_cliente(): void
    {
        $this->createPendingTicket('11223344');
        $this->createDatedTicket('55667788', '2026-10-10', n: 2);
        $this->createDatedTicket('55667788', '2026-09-20', n: 3);

        $this->identify('11223344');

        $this->assertSame(1, ClientTicketAssignmentService::pendingTicketsForCurrentClient()->count());
        $this->assertSame(0, ClientTicketAssignmentService::upcomingTicketsForCurrentClient()->count());
        $this->assertSame(0, ClientTicketAssignmentService::pastTicketsForCurrentClient()->count());
    }

    public function test_el_proximo_viaje_es_el_de_fecha_y_horario_mas_cercanos(): void
    {
        $this->createDatedTicket('11223344', '2026-10-20', '21:00', n: 1);
        $cercano = $this->createDatedTicket('11223344', '2026-10-15', '08:30', n: 2);
        $this->createDatedTicket('11223344', '2026-10-15', '18:00', n: 3);

        $this->identify('11223344');

        $this->assertSame(
            $cercano['ticket']->id,
            ClientTicketAssignmentService::nextTripTicketForCurrentClient()->id,
        );
    }

    public function test_el_proximo_viaje_desempata_por_menor_numero_y_ignora_pendientes(): void
    {
        $this->createPendingTicket('11223344', 1);
        $primero = $this->createDatedTicket('11223344', '2026-10-15', '08:00', n: 2);
        $this->createDatedTicket('11223344', '2026-10-15', '08:00', n: 3);

        $this->identify('11223344');

        $this->assertSame(
            $primero['ticket']->id,
            ClientTicketAssignmentService::nextTripTicketForCurrentClient()->id,
        );
    }

    public function test_sin_viajes_futuros_no_hay_proximo_viaje(): void
    {
        $this->createPendingTicket('11223344', 1);
        $this->createDatedTicket('11223344', '2026-09-20', n: 2);

        $this->identify('11223344');

        $this->assertNull(ClientTicketAssignmentService::nextTripTicketForCurrentClient());
    }

    public function test_el_boleto_pendiente_mas_antiguo_es_el_de_menor_numero(): void
    {
        $primero = $this->createPendingTicket('11223344', 1);
        $this->createPendingTicket('11223344', 2);

        $this->identify('11223344');

        $this->assertSame(
            $primero['ticket']->id,
            ClientTicketAssignmentService::oldestPendingTicketForCurrentClient()->id,
        );
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
        $this->assertSame(TicketDateChange::ACTOR_CLIENT, $change->actor_type);
        $this->assertSame($f['client']->id, $change->client_id);
        $this->assertNull($change->from_trip_id);
        $this->assertSame($ticket->trip_id, $change->to_trip_id);
    }

    public function test_la_reprogramacion_de_un_vendedor_queda_a_nombre_del_usuario(): void
    {
        $f = $this->createPendingTicket('11223344');
        $seat = Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first();
        $vendedor = User::factory()->vendedor()->create();

        app(\App\Services\TicketRescheduleService::class)->rescheduleForActor($f['ticket'], [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $f['schedule']->id,
            'seat_id' => $seat->id,
        ], $vendedor->id);

        $change = TicketDateChange::query()->where('ticket_id', $f['ticket']->id)->firstOrFail();

        $this->assertSame(TicketDateChange::ACTOR_USER, $change->actor_type);
        $this->assertSame($vendedor->id, $change->user_id);
        $this->assertNull($change->client_id);
    }

    public function test_borrar_del_todo_al_usuario_deja_su_reprogramacion_como_de_usuario(): void
    {
        $f = $this->createPendingTicket('11223344');
        $seat = Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first();
        $vendedor = User::factory()->vendedor()->create();

        app(\App\Services\TicketRescheduleService::class)->rescheduleForActor($f['ticket'], [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $f['schedule']->id,
            'seat_id' => $seat->id,
        ], $vendedor->id);

        $vendedor->forceDelete();

        $change = TicketDateChange::query()->where('ticket_id', $f['ticket']->id)->firstOrFail();

        $this->assertNull($change->user_id);
        $this->assertSame(TicketDateChange::ACTOR_USER, $change->actor_type, 'No pasa a ser un cambio de cliente.');
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
    // Anticipación mínima (Parámetros > Clientes). "Ahora": 01/10/2026 09:00;
    // el horario del fixture sale a las 08:00.
    // ------------------------------------------------------------------

    /** Intenta asignar la fecha por el servicio y devuelve el error de `schedule_id`, o null si se asignó. */
    private function assignOn(array $f, string $date): ?string
    {
        try {
            app(ClientTicketAssignmentService::class)->assign($f['ticket'], [
                'date' => $date,
                'schedule_id' => $f['schedule']->id,
                'seat_id' => Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first()->id,
            ]);
        } catch (ValidationException $e) {
            return $e->errors()['schedule_id'][0] ?? 'otro error: '.implode(' ', Arr::flatten($e->errors()));
        }

        return null;
    }

    public function test_el_servidor_rechaza_un_horario_dentro_de_las_24hs(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        // 02/10 08:00 sale a menos de 24hs de las 09:00 del 01/10.
        $this->assertSame('Elegí un horario con al menos 24hs de anticipación.', $this->assignOn($f, '2026-10-02'));
        $this->assertNull($f['ticket']->fresh()->trip_id, 'El boleto sigue sin fecha.');
    }

    public function test_el_servidor_acepta_un_horario_con_mas_de_24hs(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $this->assertNull($this->assignOn($f, '2026-10-03'));
        $this->assertNotNull($f['ticket']->fresh()->trip_id);
    }

    public function test_el_mensaje_dice_las_horas_configuradas(): void
    {
        Setting::set(Setting::CLIENT_ADVANCE_NOTICE_HOURS, '48');

        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        // 03/10 08:00 está a menos de 48hs de las 09:00 del 01/10.
        $this->assertSame('Elegí un horario con al menos 48hs de anticipación.', $this->assignOn($f, '2026-10-03'));
        $this->assertNull($f['ticket']->fresh()->trip_id);
    }

    public function test_con_cero_horas_no_se_exige_anticipacion(): void
    {
        Setting::set(Setting::CLIENT_ADVANCE_NOTICE_HOURS, '0');

        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $this->assertNull($this->assignOn($f, '2026-10-02'));
    }

    public function test_el_selector_no_ofrece_un_horario_fuera_de_plazo(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $data = [
            'schedule_id' => $f['schedule']->id,
            'seat_ids' => [Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first()->id],
        ];

        Livewire::test(MyTickets::class)
            ->callAction(TestAction::make('asignar_fecha')->table($f['ticket']), data: ['date' => '2026-10-02'] + $data)
            ->assertHasActionErrors(['schedule_id']);

        $this->assertNull($f['ticket']->fresh()->trip_id);

        Livewire::test(MyTickets::class)
            ->callAction(TestAction::make('asignar_fecha')->table($f['ticket']), data: ['date' => '2026-10-05'] + $data)
            ->assertHasNoActionErrors();

        $this->assertNotNull($f['ticket']->fresh()->trip_id);
    }

    public function test_si_el_dia_no_tiene_horarios_con_anticipacion_el_selector_lo_explica(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $component = Livewire::test(MyTickets::class)
            ->mountTableAction('asignar_fecha', (string) $f['ticket']->id)
            ->setTableActionData(['date' => '2026-10-02']);

        // El esquema se renderiza fuera de la vista de Livewire: hay que darle $errors.
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag);

        $render = function () use ($component): string {
            $schema = (new \ReflectionMethod($component->instance(), 'getMountedActionSchema'))->invoke($component->instance());

            return (string) preg_replace('/\s+/', ' ', $schema->toHtml());
        };

        $this->assertStringContainsString('No hay horarios con 24hs de anticipación para ese día. Elegí otra fecha.', $render());

        // Un día con horarios en plazo muestra el texto normal.
        $component->setTableActionData(['date' => '2026-10-05']);

        $this->assertStringNotContainsString('No hay horarios con', $render());
        $this->assertStringContainsString('Elegí un horario', $render());
    }

    // ------------------------------------------------------------------
    // Reconfirmación: un segundo paso avisa que la fecha no se podrá cambiar
    // ------------------------------------------------------------------

    /** Datos de un viaje elegido para el ticket del fixture. */
    private function chosenTrip(array $f, bool $withSeat = true): array
    {
        return [
            'date' => '2026-10-05',
            'schedule_id' => $f['schedule']->id,
            'seat_ids' => $withSeat
                ? [Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first()->id]
                : [],
        ];
    }

    /** HTML del modal montado, como lo ve el cliente (los espacios colapsados). */
    private function mountedModalText(object $component): string
    {
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag);
        \Illuminate\Support\Facades\View::share('__livewire', $component->instance());

        $schema = (new \ReflectionMethod($component->instance(), 'getMountedActionSchema'))->invoke($component->instance());

        return (string) preg_replace('/\s+/', ' ', strip_tags($schema->toHtml()));
    }

    public function test_elegir_fecha_tiene_dos_pasos_el_segundo_para_reconfirmar(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        Livewire::test(MyTickets::class)
            ->mountTableAction('asignar_fecha', (string) $f['ticket']->id)
            ->assertWizardStepExists(1)
            ->assertWizardStepExists(2)
            ->assertWizardCurrentStep(1);
    }

    public function test_el_segundo_paso_pregunta_si_esta_seguro_y_avisa_que_no_se_podra_cambiar(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $component = Livewire::test(MyTickets::class)
            ->mountTableAction('asignar_fecha', (string) $f['ticket']->id)
            ->setTableActionData($this->chosenTrip($f))
            ->goToNextWizardStep()
            ->assertDispatched('next-wizard-step')
            ->assertWizardCurrentStep(2);

        $text = $this->mountedModalText($component);

        $this->assertStringContainsString('¿Estás seguro de confirmar esta fecha?', $text);
        $this->assertStringContainsString('no vas a poder cambiarla de nuevo', $text);
        $this->assertStringContainsString('comunicarte con boletería', $text);
    }

    public function test_el_segundo_paso_resume_lo_elegido(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $component = Livewire::test(MyTickets::class)
            ->mountTableAction('asignar_fecha', (string) $f['ticket']->id)
            ->setTableActionData($this->chosenTrip($f))
            ->goToNextWizardStep();

        $text = $this->mountedModalText($component);

        $this->assertStringContainsString($f['origin']->name.' → '.$f['destination']->name, $text);
        $this->assertStringContainsString('05/10/2026', $text);
        $this->assertStringContainsString($f['schedule']->display_name, $text);
        $this->assertMatchesRegularExpression('/Asiento 1\b/', $text);
    }

    public function test_sin_asiento_no_se_pasa_a_reconfirmar(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        Livewire::test(MyTickets::class)
            ->mountTableAction('asignar_fecha', (string) $f['ticket']->id)
            ->setTableActionData($this->chosenTrip($f, withSeat: false))
            ->goToNextWizardStep()
            ->assertNotified('Falta elegir el asiento')
            // El evento que mueve la pantalla al paso 2 no se dispara: se queda en el paso 1.
            ->assertNotDispatched('next-wizard-step');
    }

    public function test_confirmar_en_el_segundo_paso_asigna_la_fecha_y_ya_no_se_puede_cambiar(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        Livewire::test(MyTickets::class)
            ->callAction(TestAction::make('asignar_fecha')->table($f['ticket']), data: $this->chosenTrip($f))
            ->assertHasNoActionErrors();

        $this->assertNotNull($f['ticket']->fresh()->trip_id);

        // Con fecha asignada, "Elegir fecha" ya no se ofrece.
        Livewire::test(MyTickets::class)
            ->set('activeTab', MyTickets::TAB_UPCOMING)
            ->assertTableActionHidden('asignar_fecha', $f['ticket']->fresh());
    }

    public function test_el_boton_de_confirmar_dice_si_confirmar_fecha(): void
    {
        $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $action = collect(Livewire::test(MyTickets::class)->instance()->getTable()->getRecordActions())
            ->first(fn ($a) => ! $a instanceof \Filament\Actions\ActionGroup && $a->getName() === 'asignar_fecha');

        $this->assertSame('Sí, confirmar fecha', $action->getModalSubmitActionLabel());
    }

    public function test_el_administrador_reprograma_sin_anticipacion_minima(): void
    {
        $f = $this->createPendingTicket('11223344');
        $vendedor = User::factory()->vendedor()->create();

        // Sale en menos de 24hs: el panel de clientes lo rechazaría, la reprogramación del admin no.
        app(\App\Services\TicketRescheduleService::class)->rescheduleForActor($f['ticket'], [
            'scope' => 'outbound',
            'date' => '2026-10-02',
            'schedule_id' => $f['schedule']->id,
            'seat_id' => Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first()->id,
        ], $vendedor->id);

        $this->assertNotNull($f['ticket']->fresh()->trip_id);
    }

    public function test_un_dia_sin_servicio_sigue_rechazado_ademas_de_la_anticipacion(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        // Domingo 04/10: sin servicio por defecto. Falla por el día, no por el horario.
        $this->assertStringStartsWith('otro error:', (string) $this->assignOn($f, '2026-10-04'));
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

    public function test_la_anticipacion_tambien_se_exige_al_tramo_de_vuelta(): void
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

        // Pasan cuatro días: la vuelta de las 20:00 del 06/10 sale a menos de 24hs.
        $this->travelTo(\Carbon\Carbon::parse('2026-10-05 21:00:00'));

        try {
            $service->assign($f['return'], [
                'date' => '2026-10-06',
                'schedule_id' => $f['scheduleReturn']->id,
                'seat_id' => $seat1->id,
            ]);
            $this->fail('La vuelta dentro de las 24hs tendría que rechazarse.');
        } catch (ValidationException $e) {
            $this->assertSame('Elegí un horario con al menos 24hs de anticipación.', $e->errors()['schedule_id'][0]);
        }

        $this->assertNull($f['return']->fresh()->trip_id);

        $service->assign($f['return'], [
            'date' => '2026-10-07',
            'schedule_id' => $f['scheduleReturn']->id,
            'seat_id' => $seat1->id,
        ]);

        $this->assertNotNull($f['return']->fresh()->trip_id);
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

    public function test_las_pestanas_muestran_cada_grupo_de_boletos(): void
    {
        $pendiente = $this->createPendingTicket('11223344', 1);
        $proximo = $this->createDatedTicket('11223344', '2026-10-10', n: 2);
        $pasado = $this->createDatedTicket('11223344', '2026-09-20', n: 3);
        $ajeno = $this->createDatedTicket('55667788', '2026-10-12', n: 4);

        $this->identify('11223344');

        // Con boletos sin fecha, abre en "Pendientes".
        Livewire::test(MyTickets::class)
            ->assertSet('activeTab', MyTickets::TAB_PENDING)
            ->assertCanSeeTableRecords([$pendiente['ticket']])
            ->assertCanNotSeeTableRecords([$proximo['ticket'], $pasado['ticket'], $ajeno['ticket']])
            ->set('activeTab', MyTickets::TAB_UPCOMING)
            ->assertCanSeeTableRecords([$proximo['ticket']])
            ->assertCanNotSeeTableRecords([$pendiente['ticket'], $pasado['ticket'], $ajeno['ticket']])
            ->set('activeTab', MyTickets::TAB_HISTORY)
            ->assertCanSeeTableRecords([$pasado['ticket']])
            ->assertCanNotSeeTableRecords([$pendiente['ticket'], $proximo['ticket'], $ajeno['ticket']]);
    }

    public function test_sin_pendientes_abre_en_proximos(): void
    {
        $this->createDatedTicket('11223344', '2026-10-10');

        $this->identify('11223344');

        Livewire::test(MyTickets::class)
            ->assertSet('activeTab', MyTickets::TAB_UPCOMING);
    }

    public function test_las_pestanas_muestran_su_conteo(): void
    {
        $this->createPendingTicket('11223344', 1);
        $this->createPendingTicket('11223344', 2);
        $this->createDatedTicket('11223344', '2026-10-10', n: 3);
        $this->createDatedTicket('11223344', '2026-10-11', n: 4);
        $this->createDatedTicket('11223344', '2026-10-12', n: 5);
        $this->createDatedTicket('11223344', '2026-09-20', n: 6);

        $this->identify('11223344');

        $component = Livewire::test(MyTickets::class);

        $tabs = collect($component->instance()->getSchema('content')->getComponents())
            ->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Tabs)
            ->getDefaultChildComponents();

        $this->assertSame(
            ['Pendientes' => 2, 'Próximos' => 3, 'Historial' => 1],
            collect($tabs)->mapWithKeys(fn ($tab) => [$tab->getLabel() => $tab->getBadge()])->all(),
        );
    }

    public function test_cada_pestana_vacia_muestra_su_mensaje(): void
    {
        $this->createClient('11223344');
        $this->identify('11223344');

        Livewire::test(MyTickets::class)
            ->set('activeTab', MyTickets::TAB_PENDING)
            ->assertSeeText('No tenés boletos pendientes de fecha')
            ->set('activeTab', MyTickets::TAB_UPCOMING)
            ->assertSeeText('No tenés viajes próximos')
            ->set('activeTab', MyTickets::TAB_HISTORY)
            ->assertSeeText('Todavía no tenés viajes realizados');
    }

    public function test_elegir_fecha_solo_se_ofrece_en_boletos_sin_fecha(): void
    {
        $pendiente = $this->createPendingTicket('11223344', 1);
        $proximo = $this->createDatedTicket('11223344', '2026-10-10', n: 2);

        $this->identify('11223344');

        Livewire::test(MyTickets::class)
            ->assertTableActionVisible('asignar_fecha', $pendiente['ticket'])
            ->set('activeTab', MyTickets::TAB_UPCOMING)
            ->assertTableActionHidden('asignar_fecha', $proximo['ticket'])
            ->assertTableActionVisible('boleto_pdf', $proximo['ticket']);
    }

    public function test_las_acciones_de_fila_tienen_menu_para_celular_y_botones_para_escritorio(): void
    {
        $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $actions = Livewire::test(MyTickets::class)->instance()->getTable()->getRecordActions();

        $menus = array_filter($actions, fn ($a) => $a instanceof \Filament\Actions\ActionGroup);
        $this->assertCount(1, $menus);
        $this->assertStringContainsString('md:hidden', (string) array_values($menus)[0]->getExtraAttributeBag()->get('class'));

        $desktop = collect($actions)
            ->reject(fn ($a) => $a instanceof \Filament\Actions\ActionGroup)
            ->map(fn ($a) => $a->getName())
            ->all();
        $this->assertEqualsCanonicalizing(['asignar_fecha', 'boleto_pdf', 'descargar_pdf'], $desktop);

        foreach (collect($actions)->reject(fn ($a) => $a instanceof \Filament\Actions\ActionGroup) as $action) {
            $this->assertStringContainsString('hidden md:inline-flex', (string) $action->getExtraAttributeBag()->get('class'));
        }

        $menuItems = array_values($menus)[0]->getActions();
        $this->assertEqualsCanonicalizing(
            ['asignar_fecha', 'boleto_pdf', 'descargar_pdf'],
            array_values(array_map(fn ($a) => $a->getName(), $menuItems)),
        );
    }

    public function test_descargar_apunta_al_pdf_del_boleto_propio_y_baja_el_archivo(): void
    {
        $f = $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $component = Livewire::test(MyTickets::class);

        foreach ($component->instance()->getTable()->getRecordActions() as $action) {
            if ($action instanceof \Filament\Actions\ActionGroup || $action->getName() !== 'descargar_pdf') {
                continue;
            }

            $action->record($f['ticket']);

            $this->assertSame(route('clientes.boletos.pdf', ['ticket' => $f['ticket']->id]), $action->getUrl());
            $this->assertArrayHasKey('download', $action->getExtraAttributeBag()->getAttributes(), 'El botón baja el archivo.');
            $this->assertSame('Descargar boleto', $action->getExtraAttributeBag()->get('title'));
        }

        $this->get(route('clientes.boletos.pdf', ['ticket' => $f['ticket']->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_los_badges_de_la_tabla_usan_los_colores_del_panel_de_administracion(): void
    {
        $f = $this->createPendingTicket('11223344', 1);
        $dated = $this->createDatedTicket('11223344', '2026-10-10', n: 2);

        $this->identify('11223344');

        $table = Livewire::test(MyTickets::class)->instance()->getTable();

        $badge = function (string $name, $ticket) use ($table): array {
            $column = $table->getColumn($name);
            $column->record($ticket);

            return [$column->isBadge(), $column->getColor($column->getState())];
        };

        $this->assertSame([true, 'gray'], $badge('id', $f['ticket']));
        $this->assertSame([true, 'warning'], $badge('tramo', $f['ticket']));
        $this->assertSame([true, 'primary'], $badge('seat.seat_number', $dated['ticket']));

        // Fecha y horario: amarillo mientras no hay fecha, azul una vez confirmada.
        $this->assertSame([true, 'warning'], $badge('viaje', $f['ticket']));
        $this->assertSame([true, 'info'], $badge('viaje', $dated['ticket']));

        $this->assertSame([true, 'warning'], $badge('estado', $f['ticket']));
        $this->assertSame([true, 'success'], $badge('estado', $dated['ticket']));
    }

    public function test_una_falla_inesperada_no_muestra_la_excepcion_y_se_registra(): void
    {
        $f = $this->createPendingTicket('11223344');
        $seat = Seat::where('bus_id', $f['bus']->id)->where('seat_number', 1)->first();

        $this->identify('11223344');

        $this->mock(ClientTicketAssignmentService::class, function ($mock): void {
            $mock->shouldReceive('assign')->andThrow(new \RuntimeException('detalle-tecnico-secreto'));
        });

        \Illuminate\Support\Facades\Exceptions::fake();

        Livewire::test(MyTickets::class)
            ->callAction(TestAction::make('asignar_fecha')->table($f['ticket']), data: [
                'date' => '2026-10-05',
                'schedule_id' => $f['schedule']->id,
                'seat_ids' => [$seat->id],
            ])
            ->assertNotified(
                \Filament\Notifications\Notification::make()
                    ->title('No se pudo asignar la fecha')
                    ->body('Ocurrió un problema inesperado. Volvé a intentar en unos minutos o comunicate con boletería.')
                    ->danger()
                    ->persistent(),
            )
            ->assertDontSeeText('detalle-tecnico-secreto');

        \Illuminate\Support\Facades\Exceptions::assertReported(
            fn (\RuntimeException $e): bool => $e->getMessage() === 'detalle-tecnico-secreto',
        );
        $this->assertNull($f['ticket']->fresh()->trip_id);
    }

    // ------------------------------------------------------------------
    // Aviso de pendientes y próximo viaje
    // ------------------------------------------------------------------

    public function test_con_un_solo_pendiente_el_aviso_lo_nombra_y_ofrece_elegir_fecha(): void
    {
        $f = $this->createPendingTicket('11223344', 1);
        $this->identify('11223344');

        Livewire::test(MyTickets::class)
            ->assertSeeText('Tenés 1 boleto sin fecha:')
            ->assertSeeText($f['origin']->name.' → '.$f['destination']->name)
            ->assertSeeText('N°'.$f['ticket']->id)
            ->assertSeeHtml('wire:click="chooseDateOfOldestPending"')
            ->assertDontSeeHtml('wire:click="showPendingTickets"');
    }

    public function test_con_varios_pendientes_el_aviso_dice_cuantos_y_ofrece_ver_pendientes(): void
    {
        $this->createPendingTicket('11223344', 1);
        $this->createPendingTicket('11223344', 2);
        $this->identify('11223344');

        // Con "Próximos" abierta: el botón lleva a "Pendientes".
        Livewire::test(MyTickets::class)
            ->set('activeTab', MyTickets::TAB_UPCOMING)
            ->assertSeeText('Tenés 2 boletos sin fecha.')
            ->assertSeeText('Ver pendientes')
            ->assertSeeHtml('wire:click="showPendingTickets"')
            // No elige por el cliente: el aviso no abre ningún boleto.
            ->assertDontSeeHtml('wire:click="chooseDateOfOldestPending"');
    }

    public function test_ya_en_pendientes_el_aviso_no_ofrece_ver_pendientes(): void
    {
        $this->createPendingTicket('11223344', 1);
        $this->createPendingTicket('11223344', 2);
        $this->identify('11223344');

        // Con pendientes, la pestaña inicial ya es "Pendientes".
        Livewire::test(MyTickets::class)
            ->assertSet('activeTab', MyTickets::TAB_PENDING)
            ->assertSeeText('Tenés 2 boletos sin fecha.')
            ->assertDontSeeText('Ver pendientes')
            ->assertDontSeeHtml('wire:click="showPendingTickets"')
            ->assertSeeText('con su botón «Elegir fecha»')
            // Y vuelve a aparecer al salir de "Pendientes".
            ->set('activeTab', MyTickets::TAB_HISTORY)
            ->assertSeeText('Ver pendientes');
    }

    public function test_ver_pendientes_lleva_a_la_pestana_sin_abrir_ningun_formulario(): void
    {
        $this->createPendingTicket('11223344', 1);
        $this->createPendingTicket('11223344', 2);
        $this->createDatedTicket('11223344', '2026-10-10', n: 3);
        $this->identify('11223344');

        Livewire::test(MyTickets::class)
            ->set('activeTab', MyTickets::TAB_UPCOMING)
            ->call('showPendingTickets')
            ->assertSet('activeTab', MyTickets::TAB_PENDING)
            ->assertSet('mountedActions', []);
    }

    public function test_el_aviso_de_pendientes_se_ve_en_cualquier_pestana(): void
    {
        $this->createPendingTicket('11223344', 1);
        $this->createPendingTicket('11223344', 2);
        $this->identify('11223344');

        $component = Livewire::test(MyTickets::class);

        foreach ([MyTickets::TAB_PENDING, MyTickets::TAB_UPCOMING, MyTickets::TAB_HISTORY] as $tab) {
            $component->set('activeTab', $tab)->assertSeeText('Tenés 2 boletos sin fecha.');
        }
    }

    public function test_elegir_fecha_es_un_boton_relleno_de_color_warning(): void
    {
        $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $component = Livewire::test(MyTickets::class);

        // Las dos variantes de la fila (menú del celular y botón de escritorio).
        $assignActions = collect($component->instance()->getTable()->getRecordActions())
            ->flatMap(fn ($action) => $action instanceof \Filament\Actions\ActionGroup ? $action->getActions() : [$action])
            ->filter(fn ($action) => $action->getName() === 'asignar_fecha');

        $this->assertCount(2, $assignActions);

        foreach ($assignActions as $action) {
            $this->assertFalse($action->isOutlined(), 'Con relleno.');
            $this->assertSame('warning', $action->getColor());
        }

        // El botón del aviso de pendientes es el mismo botón: relleno y warning.
        $component->assertDontSeeHtml('fi-outlined');
    }

    public function test_sin_pendientes_no_hay_aviso(): void
    {
        $this->createDatedTicket('11223344', '2026-10-10');
        $this->identify('11223344');

        Livewire::test(MyTickets::class)->assertDontSeeText('sin fecha.');
    }

    public function test_el_boton_del_aviso_con_un_solo_pendiente_abre_su_formulario(): void
    {
        $primero = $this->createPendingTicket('11223344', 1);
        $this->createDatedTicket('11223344', '2026-10-10', n: 3);

        $this->identify('11223344');

        // Aun parado en "Próximos", el botón vuelve a "Pendientes" y abre el formulario.
        Livewire::test(MyTickets::class)
            ->set('activeTab', MyTickets::TAB_UPCOMING)
            ->call('chooseDateOfOldestPending')
            ->assertSet('activeTab', MyTickets::TAB_PENDING)
            ->assertSet('mountedActions.0.name', 'asignar_fecha')
            ->assertSet('mountedActions.0.context.recordKey', (string) $primero['ticket']->id);
    }

    public function test_la_tarjeta_muestra_el_proximo_viaje(): void
    {
        $this->createDatedTicket('11223344', '2026-10-20', '21:00', n: 1);
        $this->createDatedTicket('11223344', '2026-10-15', '08:30', n: 2);

        $this->identify('11223344');

        Livewire::test(MyTickets::class)
            ->assertSeeText('Próximo viaje')
            ->assertSeeText('15/10/2026')
            ->assertSeeText('08:30');
    }

    public function test_sin_viajes_futuros_no_hay_tarjeta(): void
    {
        $this->createPendingTicket('11223344', 1);
        $this->createDatedTicket('11223344', '2026-09-20', n: 2);

        $this->identify('11223344');

        Livewire::test(MyTickets::class)->assertDontSeeText('Próximo viaje');
    }

    public function test_la_tarjeta_salta_el_boleto_eliminado(): void
    {
        $eliminado = $this->createDatedTicket('11223344', '2026-10-05', n: 1);
        $this->createDatedTicket('11223344', '2026-10-15', n: 2);
        $eliminado['ticket']->delete();

        $this->identify('11223344');

        Livewire::test(MyTickets::class)
            ->assertSeeText('15/10/2026')
            ->assertDontSeeText('05/10/2026');
    }

    // ------------------------------------------------------------------
    // Ayuda: líneas de contacto con llamada y WhatsApp
    // ------------------------------------------------------------------

    public function test_la_ayuda_muestra_cada_linea_con_llamar_y_whatsapp(): void
    {
        Setting::set(Setting::CONTACT_LINES, json_encode([
            ['name' => 'Orán', 'phone' => '3878-804506'],
            ['name' => '', 'phone' => '+54 9 (3873) 309-893'],
        ]));

        $this->createPendingTicket('11223344');
        $this->identify('11223344');

        $component = Livewire::test(MyTickets::class)
            ->assertSeeText('¿Necesitás ayuda?')
            ->assertSeeText('Orán')
            ->assertSeeText('3878-804506')
            ->assertSeeHtml('href="tel:3878804506"')
            ->assertSeeHtml('href="https://wa.me/5493878804506"')
            ->assertSeeHtml('href="tel:+5493873309893"')
            ->assertSeeHtml('href="https://wa.me/5493873309893"')
            // Botones solo con ícono: el nombre va en el título.
            ->assertSeeHtml('title="Llamar"')
            ->assertSeeHtml('title="WhatsApp"')
            ->assertSeeHtml('data-icon="whatsapp"');

        $this->assertDoesNotMatchRegularExpression('/>\s*(Llamar|WhatsApp)\s*</', $component->html());
    }

    public function test_sin_lineas_de_contacto_no_hay_bloque_de_ayuda(): void
    {
        $this->createPendingTicket('11223344');
        $this->identify('11223344');

        Livewire::test(MyTickets::class)->assertDontSeeText('¿Necesitás ayuda?');
    }

    public function test_el_numero_de_whatsapp_se_arma_con_codigo_de_pais(): void
    {
        $this->assertSame('5493878804506', Setting::whatsappNumber('+54 9 (3878) 804-506'));
        $this->assertSame('5493878804506', Setting::whatsappNumber('3878-804506'));
        $this->assertSame('5493878804506', Setting::whatsappNumber('03878-804506'));
        $this->assertSame('5493878804506', Setting::whatsappNumber('(03878) 15-804506'));
        $this->assertSame('5493873309893', Setting::whatsappNumber('3873 15 309893'));

        // El teléfono para llamar no cambia.
        $this->assertSame('3878804506', Setting::dialablePhone('3878-804506'));
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
