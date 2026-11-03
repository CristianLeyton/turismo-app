<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Models\Bus;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Support\ServiceDays;
use Carbon\Carbon;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Días de servicio en la venta: calendario, avance del paso 1 y control
 * final al emitir. El fixture usa el jueves 01/10/2026 configurado como
 * excepción "sin servicio" (motivo "Paro").
 */
class ServiceDaysSaleTest extends TestCase
{
    use RefreshDatabase;

    private const NO_SERVICE_DATE = '2026-10-01';

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');

        Setting::flushCache();

        $this->travelTo(Carbon::parse('2026-10-01 07:00:00'));
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    // ====================== Calendario ======================

    public function test_sin_permiso_el_calendario_deshabilita_el_dia_sin_servicio(): void
    {
        $this->markNoService();
        $this->actingAs(User::factory()->vendedor()->create());

        $disabled = ServiceDays::disabledTravelDatesForCurrentUser();

        $this->assertContains(self::NO_SERVICE_DATE, $disabled);
        $this->assertContains('2026-10-04', $disabled, 'Los domingos siguen sin servicio.');
        $this->assertNotContains('2026-10-02', $disabled);
    }

    public function test_con_permiso_el_calendario_no_deshabilita_nada(): void
    {
        $this->markNoService();
        $this->actingAs($this->sellerWithPermission());

        $this->assertSame([], ServiceDays::disabledTravelDatesForCurrentUser());
    }

    // ====================== Wizard: paso 1 ======================

    public function test_sin_permiso_el_paso_1_no_avanza_con_fecha_sin_servicio(): void
    {
        $f = $this->makeFixture();
        $this->markNoService();
        $this->actingAs(User::factory()->vendedor()->create());

        $page = $this->mountWizard($f);
        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 0]);

        $page->assertNotDispatched('next-wizard-step');
        $page->assertHasFormErrors(['departure_date']);
        $this->assertStringContainsString(
            '01/10/2026 es un día sin servicio (Paro)',
            implode(' ', $page->errors()->get('data.departure_date')),
        );
    }

    public function test_con_permiso_el_paso_1_avanza_y_avisa_el_dia_sin_servicio(): void
    {
        $f = $this->makeFixture();
        $this->markNoService();
        $this->actingAs($this->sellerWithPermission());

        $page = $this->mountWizard($f);

        $page->assertSee('es un día sin servicio (Paro)');

        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 0]);

        $page->assertDispatched('next-wizard-step');
    }

    public function test_dia_con_servicio_avanza_sin_aviso(): void
    {
        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        $page = $this->mountWizard($f);

        $page->assertDontSee('es un día sin servicio');

        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 0]);

        $page->assertDispatched('next-wizard-step');
    }

    // ====================== Emisión ======================

    public function test_sin_permiso_la_emision_en_dia_sin_servicio_se_rechaza(): void
    {
        $f = $this->makeFixture();
        $this->markNoService();
        $this->actingAs(User::factory()->vendedor()->create());

        try {
            $this->invokeHandleRecordCreation($this->salePayload($f));
            $this->fail('La emisión en un día sin servicio debió rechazarse.');
        } catch (Halt) {
            // OK: el guard frena la emisión.
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Ticket::count());
    }

    public function test_con_permiso_la_emision_en_dia_sin_servicio_se_realiza(): void
    {
        $f = $this->makeFixture();
        $this->markNoService();
        $this->actingAs($this->sellerWithPermission());

        $ticket = $this->invokeHandleRecordCreation($this->salePayload($f));

        $this->assertSame($f['trip']->id, $ticket->trip_id);
        $this->assertSame(1, Sale::count());
    }

    public function test_un_tramo_sin_fecha_no_se_evalua(): void
    {
        $f = $this->makeFixture();
        $this->markNoService();
        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        // La venta sin fecha exige pasajeros del padrón.
        \App\Models\Clients::create(['dni' => '12345678', 'nombre' => 'Juan', 'apellido' => 'Pérez', 'can_buy' => true]);

        $payload = array_merge($this->salePayload($f), [
            'sell_without_date_ida' => true,
            'trip_id' => null,
            'seat_ids' => [],
        ]);

        $ticket = $this->invokeHandleRecordCreation($payload);

        $this->assertNull($ticket->trip_id);
        $this->assertSame(1, Sale::count());
    }

    // ====================== Helpers ======================

    private function markNoService(): void
    {
        Setting::set(Setting::SERVICE_EXCEPTIONS, json_encode([
            ['date' => self::NO_SERVICE_DATE, 'has_service' => false, 'reason' => 'Paro'],
        ]));
    }

    private function sellerWithPermission(): User
    {
        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo(ServiceDays::PERMISSION);

        return $seller;
    }

    private function mountWizard(array $f): Testable
    {
        return Livewire::test(CreateTicket::class)
            ->fillForm([
                'bus_id' => (string) $f['bus']->id,
                'origin_location_id' => (string) $f['origin']->id,
                'destination_location_id' => (string) $f['destination']->id,
                'departure_date' => self::NO_SERVICE_DATE,
                'schedule_id' => (string) $f['schedule']->id,
                'passengers_count' => '1',
            ]);
    }

    private function invokeHandleRecordCreation(array $data): Ticket
    {
        $method = new \ReflectionMethod(CreateTicket::class, 'handleRecordCreation');

        return $method->invoke(app(CreateTicket::class), $data);
    }

    /**
     * @return array{origin: Location, destination: Location, bus: Bus, schedule: Schedule, trip: Trip, seat: Seat}
     */
    private function makeFixture(): array
    {
        $oran = Location::create(['name' => 'Orán S', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta S', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea S', 'plate' => 'SSS111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta S', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana S',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::findOrCreateForBooking($schedule->id, self::NO_SERVICE_DATE, $oran->id, $salta->id)['trip'];
        $this->assertNotNull($trip);

        return [
            'origin' => $oran,
            'destination' => $salta,
            'bus' => $bus,
            'schedule' => $schedule,
            'trip' => $trip,
            'seat' => Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first(),
        ];
    }

    private function salePayload(array $fixture): array
    {
        return [
            'bus_id' => $fixture['bus']->id,
            'sell_without_date_ida' => false,
            'sell_without_date_vuelta' => false,
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
            'origin_location_id' => $fixture['origin']->id,
            'destination_location_id' => $fixture['destination']->id,
            'is_round_trip' => false,
            'trip_id' => $fixture['trip']->id,
            'seat_ids' => [$fixture['seat']->id],
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ];
    }
}
