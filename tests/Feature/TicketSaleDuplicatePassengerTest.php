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
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Support\DuplicateTicketNotification;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Una persona por viaje en la emisión: el guard previo y el re-chequeo
 * dentro de la transacción rechazan la venta sin crear nada.
 */
class TicketSaleDuplicatePassengerTest extends TestCase
{
    use RefreshDatabase;

    private Location $oran;

    private Location $salta;

    private Bus $bus;

    private Trip $outboundTrip;

    private Trip $returnTrip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 07:00:00'));

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');

        $this->actingAs(User::factory()->vendedor()->create());

        $this->oran = Location::create(['name' => 'Orán U', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta U', 'is_active' => true]);

        $this->bus = Bus::create(['name' => 'Linea U', 'plate' => 'UUU999', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $this->outboundTrip = $this->makeTrip($this->oran, $this->salta, '08:00', '2026-10-01');
        $this->returnTrip = $this->makeTrip($this->salta, $this->oran, '17:00', '2026-10-02');
    }

    public function test_dni_con_boleto_en_la_ida_bloquea_la_venta(): void
    {
        $existing = $this->sellExisting($this->outboundTrip, $this->seat('4'), '12345678');

        $this->assertHalted(fn () => $this->emit($this->payload()));

        $this->assertNothingCreatedBesides($existing);
        Notification::assertNotified(DuplicateTicketNotification::TITLE);
    }

    public function test_dni_con_boleto_solo_en_la_vuelta_bloquea_tambien_la_ida(): void
    {
        $existing = $this->sellExisting($this->returnTrip, $this->seat('4'), '12345678');

        $this->assertHalted(fn () => $this->emit($this->payload(roundTrip: true)));

        $this->assertNothingCreatedBesides($existing);
        Notification::assertNotified(DuplicateTicketNotification::TITLE);
    }

    public function test_menor_que_ya_viaja_bloquea_la_venta(): void
    {
        $existing = $this->sellExisting($this->outboundTrip, $this->seat('4'), '40111222', childDni: '45123456');

        $payload = $this->payload();
        $payload['passengers'][0]['travels_with_child'] = true;
        $payload['passengers'][0]['child_data'] = ['first_name' => 'Nene', 'last_name' => 'Pérez', 'dni' => '45123456'];

        $this->assertHalted(fn () => $this->emit($payload));

        $this->assertNothingCreatedBesides($existing);
    }

    public function test_emision_forzada_con_dni_repetidos_se_rechaza(): void
    {
        $payload = $this->payload();
        $payload['passengers_count'] = '2';
        $payload['seat_ids'] = [$this->seat('1')->id, $this->seat('2')->id];
        $payload['passengers'][] = array_merge($payload['passengers'][0], ['first_name' => 'Otro']);

        $this->assertHalted(fn () => $this->emit($payload));

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Passenger::count());
        $this->assertSame(0, Ticket::withTrashed()->count());
        Notification::assertNotified('No se puede completar la venta');
    }

    public function test_un_usuario_con_todos_los_permisos_tambien_queda_bloqueado(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $existing = $this->sellExisting($this->outboundTrip, $this->seat('4'), '12345678');

        $this->assertHalted(fn () => $this->emit($this->payload()));

        $this->assertNothingCreatedBesides($existing);
    }

    public function test_venta_sin_conflicto_se_emite(): void
    {
        $this->sellExisting($this->outboundTrip, $this->seat('4'), '40111222');
        $this->sellExisting($this->outboundTrip, $this->seat('3'), '12345678')->delete();

        $ticket = $this->emit($this->payload(roundTrip: true));

        $this->assertSame($this->outboundTrip->id, $ticket->trip_id);
        $this->assertSame(2, Ticket::query()->where('sale_id', $ticket->sale_id)->count());
    }

    public function test_conflicto_creado_despues_del_guard_previo_se_detecta_en_la_transaccion(): void
    {
        $injected = false;

        // Simula otra venta que emite el mismo DNI entre el guard previo y la
        // transacción de emisión (el boleto aparece al abrirse la transacción).
        Event::listen(TransactionBeginning::class, function () use (&$injected): void {
            if ($injected) {
                return;
            }

            $injected = true;
            $this->sellExisting($this->outboundTrip, $this->seat('4'), '12345678');
        });

        $this->assertHalted(fn () => $this->emit($this->payload()));

        $this->assertTrue($injected, 'La transacción de emisión debió abrirse.');
        $this->assertSame(0, Passenger::query()->where('first_name', 'Juan')->count(), 'No debe crearse el pasajero de la venta rechazada.');
        $this->assertSame(0, Ticket::query()->where('seat_id', $this->seat('1')->id)->count());
        Notification::assertNotified(DuplicateTicketNotification::TITLE);
    }

    // ====================== Helpers ======================

    private function assertHalted(callable $callback): void
    {
        try {
            $callback();
            $this->fail('La emisión debió detenerse.');
        } catch (Halt) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertNothingCreatedBesides(Ticket $existing): void
    {
        $this->assertSame([$existing->sale_id], Sale::pluck('id')->all());
        $this->assertSame([$existing->id], Ticket::withTrashed()->pluck('id')->all());
    }

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

    private function sellExisting(Trip $trip, Seat $seat, string $dni, ?string $childDni = null): Ticket
    {
        $sale = Sale::create(['user_id' => auth()->id(), 'sale_date' => now(), 'total_amount' => 100]);
        $passenger = Passenger::create(['first_name' => 'Otro', 'last_name' => 'Pasajero', 'dni' => $dni, 'passenger_type' => 'adult']);

        if ($childDni !== null) {
            Passenger::create([
                'first_name' => 'Nene',
                'last_name' => 'Pasajero',
                'dni' => $childDni,
                'parent_passenger_id' => $passenger->id,
                'passenger_type' => 'child',
            ]);
        }

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'seat_id' => $seat->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'travels_with_child' => $childDni !== null,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 100,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(bool $roundTrip = false): array
    {
        return [
            'bus_id' => $this->bus->id,
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
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'is_round_trip' => $roundTrip,
            'trip_id' => $this->outboundTrip->id,
            'seat_ids' => [$this->seat('1')->id],
            'return_trip_id' => $roundTrip ? $this->returnTrip->id : null,
            'return_seat_ids' => $roundTrip ? [$this->seat('1')->id] : [],
        ];
    }
}
