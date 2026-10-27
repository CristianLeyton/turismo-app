<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Models\Bus;
use App\Models\Clients;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Aviso de cliente baneado en TIEMPO REAL en el wizard de venta.
 *
 * Al completar el DNI del pasajero (paso "Pasajeros"), la validación corre sin
 * necesidad de avanzar al paso siguiente ni confirmar la venta.
 */
class TicketSaleDniValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Location $oran;

    protected Location $salta;

    protected Bus $bus;

    protected Route $route;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');
        Setting::flushCache();

        $this->oran = Location::create(['name' => 'Orán D', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta D', 'is_active' => true]);
        $this->bus = Bus::create(['name' => 'Linea D', 'plate' => 'DDD111', 'seat_count' => 4, 'floors' => 1]);

        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $this->route = Route::create(['name' => 'Orán - Salta D', 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $this->route->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $this->route->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_el_dni_se_valida_al_completarlo_en_el_paso_de_pasajeros(): void
    {
        $schedule = $this->makeSchedule();
        $date = '2027-01-07'; // jueves, futuro: sin bloqueos de venta fuera de término
        Trip::findOrCreateForBooking($schedule->id, $date, $this->oran->id, $this->salta->id);

        $this->actingAs(User::factory()->vendedor()->create());

        Clients::create([
            'dni' => '30111222',
            'nombre' => 'Ana',
            'apellido' => 'Perez',
            'telefono' => '3875550002',
            'can_buy' => false,
            'comments' => 'Impago reiterado',
        ]);

        $page = $this->mountPassengersStep($schedule, $date);

        // Completar el DNI y salir del campo: el aviso aparece solo.
        $page->set('data.passengers.0.dni', '30111222')
            ->assertHasErrors(['data.passengers.0.dni']);

        $this->assertStringContainsString('está baneado', (string) $page->errors()->first('data.passengers.0.dni'));
    }

    public function test_un_dni_valido_no_marca_error_en_el_paso_de_pasajeros(): void
    {
        $schedule = $this->makeSchedule();
        $date = '2027-01-07';
        Trip::findOrCreateForBooking($schedule->id, $date, $this->oran->id, $this->salta->id);

        $this->actingAs(User::factory()->vendedor()->create());

        Clients::create([
            'dni' => '30111222',
            'nombre' => 'Ana',
            'apellido' => 'Perez',
            'telefono' => '3875550002',
            'can_buy' => true, // habilitado
        ]);

        $page = $this->mountPassengersStep($schedule, $date);

        $page->set('data.passengers.0.dni', '30111222')->assertHasNoErrors(['data.passengers.0.dni']);
    }

    public function test_la_venta_sin_fecha_exige_padrón_al_completar_el_dni(): void
    {
        // El switch "sin fecha" solo es visible con el permiso cargado en la
        // matriz de roles (misma condición que el feature flag del form).
        \App\Support\Permissions::syncToDatabase();
        Role::findByName(\App\Support\Permissions::ROLE_SELLER)->givePermissionTo('tickets.vender_sin_fecha');
        \App\Support\Permissions::forgetFeatureFlagCache();

        $schedule = $this->makeSchedule();
        $date = '2027-01-07';
        Trip::findOrCreateForBooking($schedule->id, $date, $this->oran->id, $this->salta->id);

        $this->actingAs(User::factory()->vendedor()->create());

        // Un DNI que NO está en el padrón.
        $page = $this->mountPassengersStep($schedule, $date);

        $page->set('data.sell_without_date_ida', true)
            ->assertHasNoErrors(['data.passengers.0.dni']);

        $page->set('data.passengers.0.dni', '76543210')
            ->assertHasErrors(['data.passengers.0.dni']);

        $this->assertStringContainsString(
            'padrón',
            (string) $page->errors()->first('data.passengers.0.dni'),
        );
    }

    public function test_la_venta_sin_fecha_admite_un_dni_del_padron(): void    {
        \App\Support\Permissions::syncToDatabase();
        Role::findByName(\App\Support\Permissions::ROLE_SELLER)->givePermissionTo('tickets.vender_sin_fecha');
        \App\Support\Permissions::forgetFeatureFlagCache();

        $schedule = $this->makeSchedule();
        $date = '2027-01-07';
        Trip::findOrCreateForBooking($schedule->id, $date, $this->oran->id, $this->salta->id);

        $this->actingAs(User::factory()->vendedor()->create());

        Clients::create([
            'dni' => '76543210',
            'nombre' => 'Beto',
            'apellido' => 'Lopez',
            'can_buy' => true,
        ]);

        $page = $this->mountPassengersStep($schedule, $date);

        $page->set('data.sell_without_date_ida', true)
            ->set('data.passengers.0.dni', '76543210')
            ->assertHasNoErrors(['data.passengers.0.dni']);
    }

    public function test_el_dni_del_padron_autocompleta_nombre_apellido_y_telefono(): void
    {
        $schedule = $this->makeSchedule();
        $date = '2027-01-07';
        Trip::findOrCreateForBooking($schedule->id, $date, $this->oran->id, $this->salta->id);

        $this->actingAs(User::factory()->vendedor()->create());

        Clients::create([
            'dni' => '30222333',
            'nombre' => 'Ana',
            'apellido' => 'Gomez',
            'telefono' => '3875550003',
            'can_buy' => true,
        ]);

        $page = $this->mountPassengersStep($schedule, $date);

        // Al completar el DNI (>= 7 dígitos), el padrón llena apellido,
        // nombre y teléfono de ese pasajero.
        $page->set('data.passengers.0.dni', '30222333')
            ->assertFormSet([
                'passengers.0.last_name' => 'Gomez',
                'passengers.0.first_name' => 'Ana',
                'passengers.0.phone_number' => '3875550003',
            ]);
    }

    public function test_con_menos_de_7_digitos_no_se_valida_ni_se_autocompleta(): void
    {
        $schedule = $this->makeSchedule();
        $date = '2027-01-07';
        Trip::findOrCreateForBooking($schedule->id, $date, $this->oran->id, $this->salta->id);

        $this->actingAs(User::factory()->vendedor()->create());

        // El borrador del DNI (5 dígitos) coincide con el prefijo del DNI
        // del cliente, pero no debe buscar ni validar todavía.
        Clients::create([
            'dni' => '3011122',
            'nombre' => 'Ana',
            'apellido' => 'Perez',
            'can_buy' => true,
        ]);

        $page = $this->mountPassengersStep($schedule, $date);

        $page->set('data.passengers.0.dni', '30111');

        $passenger = $page->instance()->form->getRawState()['passengers'][0] ?? [];

        $this->assertSame('', (string) ($passenger['last_name'] ?? ''));
        $this->assertSame('', (string) ($passenger['first_name'] ?? ''));

        // Al completar los 7 dígitos, sí llena.
        $page->set('data.passengers.0.dni', '3011122')
            ->assertFormSet([
                'passengers.0.last_name' => 'Perez',
                'passengers.0.first_name' => 'Ana',
            ]);
    }

    /** Avanza el wizard hasta el paso "Pasajeros" con 1 pasajero y 1 asiento. */
    protected function mountPassengersStep(Schedule $schedule, string $date): Testable
    {
        $seat = Seat::where('bus_id', $this->bus->id)->orderBy('seat_number')->first();

        $page = Livewire::test(CreateTicket::class)->fillForm([
            'bus_id' => (string) $this->bus->id,
            'origin_location_id' => (string) $this->oran->id,
            'destination_location_id' => (string) $this->salta->id,
            'departure_date' => $date,
            'schedule_id' => (string) $schedule->id,
            'passengers_count' => '1',
        ]);

        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 0]);

        $page->set('data.seat_ids', [$seat->id]);
        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 1]);

        return $page;
    }

    protected function makeSchedule(): Schedule
    {
        return Schedule::create([
            'route_id' => $this->route->id,
            'name' => 'Horario D',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
    }
}
