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
use Carbon\Carbon;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Observación interna de la venta: se carga en el paso "Resumen", se valida
 * (máx. 280) y se copia a todos los boletos que crea la venta.
 */
class TicketObservationSaleTest extends TestCase
{
    use RefreshDatabase;

    private Location $oran;

    private Location $salta;

    private Bus $bus;

    private Trip $outboundTrip;

    private Trip $returnTrip;

    protected function tearDown(): void
    {
        // La caché de Setting es estática: sin esto el parámetro prendido
        // pasaría a otros tests del mismo proceso.
        Setting::flushCache();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Las observaciones arrancan apagadas: estos tests las prenden y
        // los casos con el parámetro apagado lo apagan explícitamente.
        Setting::flushCache();
        Setting::set(Setting::OBSERVACIONES_VENTA, '1');

        $this->travelTo(Carbon::parse('2026-10-01 07:00:00'));

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        // La venta sin fecha exige pasajeros del padrón.
        Clients::create(['dni' => '12345678', 'nombre' => 'Juan', 'apellido' => 'Pérez', 'can_buy' => true]);
        Clients::create(['dni' => '23456789', 'nombre' => 'Ana', 'apellido' => 'Gómez', 'can_buy' => true]);

        $this->oran = Location::create(['name' => 'Orán O', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta O', 'is_active' => true]);

        $this->bus = Bus::create(['name' => 'Linea O', 'plate' => 'OOO111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $this->outboundTrip = $this->makeTrip($this->oran, $this->salta, '08:00', '2026-10-01');
        $this->returnTrip = $this->makeTrip($this->salta, $this->oran, '17:00', '2026-10-02');
    }

    // ====================== Modelo ======================

    public function test_el_modelo_recorta_la_observacion_y_guarda_null_si_queda_vacia(): void
    {
        $ticket = new Ticket;

        $ticket->observation = '   ';
        $this->assertNull($ticket->observation);

        $ticket->observation = '  Baja en Metán  ';
        $this->assertSame('Baja en Metán', $ticket->observation);

        $ticket->observation = null;
        $this->assertNull($ticket->observation);
    }

    // ====================== Formulario ======================

    public function test_el_paso_resumen_ofrece_el_campo_observaciones(): void
    {
        Livewire::test(CreateTicket::class)
            ->assertFormFieldExists('observation', function ($field): bool {
                return $field->getLabel() === 'Observaciones'
                    && $field->getMaxLength() === Ticket::MAX_OBSERVATION_LENGTH
                    && ! $field->isRequired();
            });
    }

    // ====================== Emisión ======================

    public function test_venta_sin_observacion_deja_los_boletos_sin_observacion(): void
    {
        $this->emit($this->roundTripPayload());

        $this->assertSame(2, Ticket::count());
        $this->assertSame(0, Ticket::whereNotNull('observation')->count());
    }

    public function test_observacion_con_solo_espacios_se_guarda_como_sin_observacion(): void
    {
        $this->emit($this->roundTripPayload(['observation' => "   \n  "]));

        $this->assertSame(0, Ticket::whereNotNull('observation')->count());
    }

    public function test_observacion_de_280_caracteres_se_guarda_completa(): void
    {
        $text = str_repeat('a', 280);

        $this->emit($this->roundTripPayload(['observation' => $text]));

        $this->assertSame([$text, $text], Ticket::pluck('observation')->all());
    }

    public function test_observacion_de_281_caracteres_se_rechaza_y_no_crea_la_venta(): void
    {
        try {
            $this->emit($this->roundTripPayload(['observation' => str_repeat('a', 281)]));
            $this->fail('La venta debió rechazarse por la observación.');
        } catch (Halt) {
            // OK: la venta se frena antes de crear nada.
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Ticket::count());

        $bodies = collect(session('filament.notifications', []))->pluck('body')->implode(' ');
        $this->assertStringContainsString('no puede superar los 280 caracteres', $bodies);
    }

    public function test_ida_y_vuelta_con_dos_pasajeros_copia_la_observacion_a_los_cuatro_boletos(): void
    {
        $payload = $this->roundTripPayload(['observation' => 'Paga el saldo al subir']);
        $payload['passengers_count'] = '2';
        $payload['passengers'][] = $this->passenger('23456789', 'Ana', 'Gómez');
        $payload['seat_ids'] = [$this->seat('1')->id, $this->seat('2')->id];
        $payload['return_seat_ids'] = [$this->seat('1')->id, $this->seat('2')->id];

        $this->emit($payload);

        $this->assertSame(4, Ticket::count());
        $this->assertSame(
            array_fill(0, 4, 'Paga el saldo al subir'),
            Ticket::orderBy('id')->pluck('observation')->all(),
        );
    }

    public function test_vuelta_sin_fecha_copia_la_observacion_al_boleto_pendiente(): void
    {
        $this->emit($this->roundTripPayload([
            'observation' => 'Lleva silla de ruedas',
            'sell_without_date_vuelta' => true,
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ]));

        $pending = Ticket::whereNull('trip_id')->sole();
        $outbound = Ticket::whereNotNull('trip_id')->sole();

        $this->assertSame('Lleva silla de ruedas', $pending->observation);
        $this->assertSame('Lleva silla de ruedas', $outbound->observation);
    }

    public function test_ida_sin_fecha_copia_la_observacion_al_boleto_pendiente(): void
    {
        $this->emit($this->roundTripPayload([
            'observation' => 'Avisar por WhatsApp',
            'is_round_trip' => false,
            'sell_without_date_ida' => true,
            'trip_id' => null,
            'seat_ids' => [],
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ]));

        $this->assertSame('Avisar por WhatsApp', Ticket::sole()->observation);
    }

    public function test_ambos_tramos_sin_fecha_copian_la_observacion(): void
    {
        $this->emit($this->roundTripPayload([
            'observation' => 'Confirma fecha la semana que viene',
            'sell_without_date_ida' => true,
            'sell_without_date_vuelta' => true,
            'trip_id' => null,
            'seat_ids' => [],
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ]));

        $this->assertSame(2, Ticket::count());
        $this->assertSame(2, Ticket::where('observation', 'Confirma fecha la semana que viene')->count());
    }

    // ====================== Parámetro apagado ======================

    public function test_con_el_parametro_apagado_el_resumen_no_ofrece_el_campo(): void
    {
        Setting::set(Setting::OBSERVACIONES_VENTA, '0');

        Livewire::test(CreateTicket::class)
            ->assertFormFieldHidden('observation');
    }

    public function test_con_el_parametro_apagado_una_observacion_forzada_no_se_guarda(): void
    {
        Setting::set(Setting::OBSERVACIONES_VENTA, '0');

        $this->emit($this->roundTripPayload(['observation' => 'Forzada por Livewire']));

        $this->assertSame(2, Ticket::count());
        $this->assertSame(0, Ticket::whereNotNull('observation')->count());
    }

    public function test_con_el_parametro_apagado_una_observacion_larga_no_frena_la_venta(): void
    {
        Setting::set(Setting::OBSERVACIONES_VENTA, '0');

        $this->emit($this->roundTripPayload(['observation' => str_repeat('a', 400)]));

        $this->assertSame(1, Sale::count());
        $this->assertSame(0, Ticket::whereNotNull('observation')->count());
    }

    // ====================== Helpers ======================

    private function emit(array $data): Ticket
    {
        $method = new \ReflectionMethod(CreateTicket::class, 'handleRecordCreation');

        return $method->invoke(app(CreateTicket::class), $data);
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
    private function passenger(string $dni, string $firstName, string $lastName): array
    {
        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'dni' => $dni,
            'phone_number' => '3881234567',
            'email' => null,
            'travels_with_child' => false,
            'travels_with_pets' => false,
            'price' => 15000,
            'payment_method' => 'efectivo',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function roundTripPayload(array $overrides = []): array
    {
        return array_merge([
            'bus_id' => $this->bus->id,
            'sell_without_date_ida' => false,
            'sell_without_date_vuelta' => false,
            'passengers_count' => '1',
            'passengers' => [$this->passenger('12345678', 'Juan', 'Pérez')],
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'is_round_trip' => true,
            'trip_id' => $this->outboundTrip->id,
            'seat_ids' => [$this->seat('1')->id],
            'return_trip_id' => $this->returnTrip->id,
            'return_seat_ids' => [$this->seat('1')->id],
        ], $overrides);
    }
}
