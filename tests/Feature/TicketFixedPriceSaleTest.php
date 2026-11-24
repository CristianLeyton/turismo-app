<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Models\Bus;
use App\Models\Clients;
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
use App\Support\FixedTicketPrice;
use Carbon\Carbon;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Precio fijo en la venta: el precio sale de los parámetros, se recalcula en
 * el servidor al emitir y la venta se rechaza si la configuración cambió
 * desde que se armó.
 */
class TicketFixedPriceSaleTest extends TestCase
{
    use ConfiguresFixedPrice;
    use RefreshDatabase;

    private Location $oran;

    private Location $salta;

    private Bus $bus;

    private Trip $outboundTrip;

    private Trip $returnTrip;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();

        // Primero el día de la venta: el período del helper rige desde "hoy".
        $this->travelTo(Carbon::parse('2026-10-01 07:00:00'));
        $this->configureFixedPrice(true, 5000, 800);

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        Clients::create(['dni' => '12345678', 'nombre' => 'Juan', 'apellido' => 'Pérez', 'can_buy' => true]);

        $this->oran = Location::create(['name' => 'Orán P', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta P', 'is_active' => true]);

        $this->bus = Bus::create(['name' => 'Linea P', 'plate' => 'PPP111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $this->outboundTrip = $this->makeTrip($this->oran, $this->salta, '08:00', '2026-10-01');
        $this->returnTrip = $this->makeTrip($this->salta, $this->oran, '17:00', '2026-10-02');
    }

    // ====================== Formulario ======================

    public function test_con_precio_fijo_el_precio_se_muestra_calculado_y_no_se_edita(): void
    {
        Livewire::test(CreateTicket::class)
            ->assertSet('data.fixed_price_snapshot', '1|5000|800')
            ->fillForm(['passengers' => [$this->formPassenger(withChild: false)]])
            ->assertFormFieldHidden('passengers.0.price')
            ->assertSee('$5.000,00');
    }

    public function test_el_precio_calculado_se_ve_destacado_como_en_el_resumen(): void
    {
        $html = Livewire::test(CreateTicket::class)
            ->fillForm(['passengers' => [$this->formPassenger(withChild: false)]])
            ->html();

        // Grande, en seminegrita y con el color de la empresa, como en el paso "Resumen".
        $this->assertMatchesRegularExpression(
            '/<[^>]*class="[^"]*fi-color-primary[^"]*fi-size-lg[^"]*fi-font-semibold[^"]*"[^>]*>\s*\$5\.000,00/',
            $html,
        );
    }

    public function test_el_precio_mostrado_se_actualiza_con_el_menor_y_la_ida_y_vuelta(): void
    {
        $component = Livewire::test(CreateTicket::class)
            ->fillForm(['passengers' => [$this->formPassenger(withChild: true)]])
            ->assertSee('$5.800');

        // Sin viajes elegidos el formulario apaga el diferido: se cargan con él.
        $component->fillForm([
            'bus_id' => $this->bus->id,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'trip_id' => $this->outboundTrip->id,
            'trip_search_status' => 'available',
            'return_trip_id' => $this->returnTrip->id,
            'return_trip_search_status' => 'available',
            'is_round_trip' => true,
            'passengers' => [$this->formPassenger(withChild: true)],
        ])
            ->assertSee('$11.600');
    }

    public function test_con_precio_fijo_apagado_el_precio_se_carga_a_mano(): void
    {
        $this->configureFixedPrice(false, 5000, 800);

        Livewire::test(CreateTicket::class)
            ->fillForm(['passengers' => [$this->formPassenger(withChild: false)]])
            ->assertFormFieldVisible('passengers.0.price');
    }

    // ====================== Cálculo al emitir ======================

    public function test_solo_ida_sin_menor_vale_el_precio_del_boleto(): void
    {
        $this->emit($this->oneWayPayload());

        $this->assertEquals(5000, Ticket::sole()->price);
        $this->assertEquals(5000, Sale::sole()->total_amount);
    }

    public function test_solo_ida_con_menor_suma_el_seguro(): void
    {
        $this->emit($this->oneWayPayload(withChild: true));

        $this->assertEquals(5800, Ticket::sole()->price);
    }

    public function test_ida_y_vuelta_con_menor_cobra_todo_en_la_ida(): void
    {
        $this->emit($this->roundTripPayload(withChild: true));

        $this->assertEquals(11600, Ticket::where('is_return_leg', false)->sole()->price);
        $this->assertEquals(0, Ticket::where('is_return_leg', true)->sole()->price);
        $this->assertEquals(11600, Sale::sole()->total_amount);
    }

    public function test_la_mascota_no_suma(): void
    {
        $payload = $this->oneWayPayload();
        $payload['passengers'][0]['travels_with_pets'] = true;
        $payload['passengers'][0]['pet_data'] = ['pet_names' => 'Perro', 'pet_count' => '1'];

        $this->emit($payload);

        $this->assertEquals(5000, Ticket::sole()->price);
    }

    public function test_vuelta_sin_fecha_cobra_la_ida_y_vuelta_en_la_ida(): void
    {
        $this->emit($this->roundTripPayload(overrides: [
            'sell_without_date_vuelta' => true,
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ]));

        $this->assertEquals(10000, Ticket::where('is_return_leg', false)->sole()->price);
        $this->assertEquals(0, Ticket::where('is_return_leg', true)->sole()->price);
    }

    public function test_ambos_tramos_sin_fecha_con_menor(): void
    {
        $this->emit($this->roundTripPayload(withChild: true, overrides: [
            'sell_without_date_ida' => true,
            'sell_without_date_vuelta' => true,
            'trip_id' => null,
            'seat_ids' => [],
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ]));

        $this->assertEquals(11600, Ticket::where('is_return_leg', false)->sole()->price);
        $this->assertEquals(0, Ticket::where('is_return_leg', true)->sole()->price);
    }

    public function test_un_precio_manipulado_se_ignora(): void
    {
        $payload = $this->oneWayPayload();
        $payload['passengers'][0]['price'] = 1;

        $this->emit($payload);

        $this->assertEquals(5000, Ticket::sole()->price);
    }

    public function test_con_precio_fijo_apagado_se_respeta_el_precio_a_mano(): void
    {
        $this->configureFixedPrice(false, 5000, 800);

        $payload = $this->oneWayPayload();
        $payload['passengers'][0]['price'] = 1500;

        $this->emit($payload);

        $this->assertEquals(1500, Ticket::sole()->price);
    }

    // ====================== Cambio de configuración con la venta abierta ======================

    public function test_si_cambio_el_precio_la_venta_se_rechaza_y_muestra_el_nuevo(): void
    {
        $page = app(CreateTicket::class);
        $page->data = ['fixed_price_snapshot' => '1|5000|800'];

        $this->configureFixedPrice(true, 6000, 800);

        $this->assertEmissionRejected($this->oneWayPayload(snapshot: '1|5000|800'), $page);

        // La venta abierta toma la configuración nueva: el precio pasa a 6000.
        $this->assertSame('1|6000|800', $page->data['fixed_price_snapshot']);
        $this->assertSame(6000, FixedTicketPrice::fromSnapshot($page->data['fixed_price_snapshot'], false, false));
    }

    public function test_si_se_prendio_el_precio_fijo_la_venta_se_rechaza(): void
    {
        $this->assertEmissionRejected($this->oneWayPayload(snapshot: '0|5000|800'));
    }

    public function test_si_se_apago_el_precio_fijo_la_venta_se_rechaza(): void
    {
        $this->configureFixedPrice(false, 5000, 800);

        $this->assertEmissionRejected($this->oneWayPayload(snapshot: '1|5000|800'));
    }

    public function test_con_precio_fijo_apagado_y_sin_cambios_se_emite_normal(): void
    {
        $this->configureFixedPrice(false, 5000, 800);

        $payload = $this->oneWayPayload(snapshot: FixedTicketPrice::snapshot());
        $payload['passengers'][0]['price'] = 1500;

        $this->emit($payload);

        $this->assertEquals(1500, Ticket::sole()->price);
    }

    // ====================== Precio vigente según la fecha de venta ======================

    public function test_venta_dentro_de_un_periodo(): void
    {
        $this->sellOn('2026-11-25 07:00', tripDate: '2026-11-25');

        $this->assertEquals(70000, Ticket::sole()->price);
    }

    public function test_el_primer_dia_del_periodo_siguiente_rige_el_nuevo(): void
    {
        $this->sellOn('2026-12-02 07:00', tripDate: '2026-12-02', withChild: true);

        $this->assertEquals(168000, Ticket::sole()->price);
    }

    public function test_despues_del_ultimo_periodo_sigue_rigiendo_su_monto(): void
    {
        $this->sellOn('2027-03-15 07:00', tripDate: '2027-03-15');

        $this->assertEquals(160000, Ticket::sole()->price);
    }

    public function test_un_viaje_en_otro_periodo_cobra_el_de_la_fecha_de_venta(): void
    {
        $this->sellOn('2026-11-25 07:00', tripDate: '2026-12-21');

        $this->assertEquals(70000, Ticket::sole()->price);
    }

    public function test_ida_y_vuelta_con_vuelta_sin_fecha_cobra_el_periodo_de_la_venta(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 07:00'));
        $this->seasonPeriods();
        $this->outboundTrip = $this->makeTrip($this->oran, $this->salta, '08:00', '2026-11-25');

        $this->emit($this->roundTripPayload(overrides: [
            'trip_id' => $this->outboundTrip->id,
            'sell_without_date_vuelta' => true,
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ]));

        $this->assertEquals(140000, Ticket::where('is_return_leg', false)->sole()->price);
        $this->assertEquals(0, Ticket::where('is_return_leg', true)->sole()->price);
    }

    // ====================== Venta sin período vigente ======================

    public function test_sin_periodo_vigente_la_venta_se_rechaza(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 07:00'));
        // Guardado por fuera de la página: un único período que todavía no empezó.
        $this->setFixedPricePeriods([['desde' => '2026-12-01', 'boleto' => 70000, 'seguro' => 5000]]);
        $this->outboundTrip = $this->makeTrip($this->oran, $this->salta, '08:00', '2026-11-25');

        $this->assertEmissionRejected(
            $this->oneWayPayload(),
            message: 'No hay un precio vigente para hoy. Pedile a un administrador que cargue los precios.',
        );
    }

    // ====================== Cruce de medianoche ======================

    public function test_si_la_venta_cruzo_a_otro_periodo_se_rechaza_y_muestra_el_nuevo(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 23:55'));
        $this->seasonPeriods();
        $snapshot = FixedTicketPrice::snapshot();
        $page = app(CreateTicket::class);
        $page->data = ['fixed_price_snapshot' => $snapshot];

        $this->travelTo(Carbon::parse('2026-12-02 00:05'));
        $this->outboundTrip = $this->makeTrip($this->oran, $this->salta, '08:00', '2026-12-02');

        $this->assertEmissionRejected($this->oneWayPayload(snapshot: $snapshot), $page);

        $this->assertSame(160000, FixedTicketPrice::fromSnapshot($page->data['fixed_price_snapshot'], false, false));
    }

    public function test_si_la_venta_cruzo_el_dia_sin_cambiar_de_periodo_se_emite(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 23:55'));
        Setting::set(Setting::PRECIO_FIJO_ACTIVO, '1');
        $this->setFixedPricePeriods([['desde' => '2026-11-20', 'boleto' => 70000, 'seguro' => 5000]]);
        $snapshot = FixedTicketPrice::snapshot();

        $this->travelTo(Carbon::parse('2026-11-26 00:05'));
        $this->outboundTrip = $this->makeTrip($this->oran, $this->salta, '08:00', '2026-11-26');

        $this->emit($this->oneWayPayload(snapshot: $snapshot));

        $this->assertEquals(70000, Ticket::sole()->price);
    }

    // ====================== Helpers ======================

    /** Períodos de temporada: 70.000 desde el 20/11 y 160.000 desde el 02/12. */
    private function seasonPeriods(): void
    {
        Setting::set(Setting::PRECIO_FIJO_ACTIVO, '1');
        $this->setFixedPricePeriods([
            ['desde' => '2026-11-20', 'boleto' => 70000, 'seguro' => 5000],
            ['desde' => '2026-12-02', 'boleto' => 160000, 'seguro' => 8000],
        ]);
    }

    /** Vende solo ida en `$at` (día y hora de la emisión) para un viaje de `$tripDate`. */
    private function sellOn(string $at, string $tripDate, bool $withChild = false): void
    {
        $this->travelTo(Carbon::parse($at));
        $this->seasonPeriods();
        $this->outboundTrip = $this->makeTrip($this->oran, $this->salta, '08:00', $tripDate);

        $this->emit($this->oneWayPayload(withChild: $withChild));
    }

    private function assertEmissionRejected(array $payload, ?CreateTicket $page = null, string $message = 'Cambió la configuración de precios. Revisá los precios de la venta y volvé a emitir.'): void
    {
        try {
            $this->emit($payload, $page);
            $this->fail('La venta debió rechazarse.');
        } catch (Halt) {
            // OK: se frena antes de crear nada.
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Ticket::count());

        $bodies = collect(session('filament.notifications', []))->pluck('body')->implode(' ');
        $this->assertStringContainsString($message, $bodies);
    }

    private function emit(array $data, ?CreateTicket $page = null): Ticket
    {
        $method = new \ReflectionMethod(CreateTicket::class, 'handleRecordCreation');

        return $method->invoke($page ?? app(CreateTicket::class), $data);
    }

    private function seat(string $number): Seat
    {
        return Seat::where('bus_id', $this->bus->id)->where('seat_number', $number)->first();
    }

    private function makeTrip(Location $from, Location $to, string $time, string $date): Trip
    {
        $route = Route::create(['name' => "{$from->name} - {$to->name}", 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $from->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $to->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => "Horario {$time}",
            'departure_time' => $time,
            'arrival_time' => '23:00',
            'is_active' => true,
        ]);

        return Trip::findOrCreateForBooking($schedule->id, $date, $from->id, $to->id)['trip'];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPassenger(bool $withChild): array
    {
        return [
            'passenger_number' => 1,
            'dni' => '12345678',
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'travels_with_child' => $withChild,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function passenger(bool $withChild): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'dni' => '12345678',
            'phone_number' => '3881234567',
            'email' => null,
            'travels_with_child' => $withChild,
            'travels_with_pets' => false,
            'payment_method' => 'efectivo',
        ], $withChild ? ['child_data' => ['first_name' => 'Tomi', 'last_name' => 'Pérez', 'dni' => '55555555']] : []);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function roundTripPayload(bool $withChild = false, array $overrides = [], ?string $snapshot = null): array
    {
        return array_merge([
            'bus_id' => $this->bus->id,
            'sell_without_date_ida' => false,
            'sell_without_date_vuelta' => false,
            'passengers_count' => '1',
            'passengers' => [$this->passenger($withChild)],
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'is_round_trip' => true,
            'trip_id' => $this->outboundTrip->id,
            'seat_ids' => [$this->seat('1')->id],
            'return_trip_id' => $this->returnTrip->id,
            'return_seat_ids' => [$this->seat('1')->id],
            'fixed_price_snapshot' => $snapshot ?? FixedTicketPrice::snapshot(),
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function oneWayPayload(bool $withChild = false, ?string $snapshot = null): array
    {
        return $this->roundTripPayload($withChild, [
            'is_round_trip' => false,
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ], $snapshot);
    }
}
