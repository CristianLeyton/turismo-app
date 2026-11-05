<?php

namespace Tests\Feature;

use App\Exceptions\SeatUnavailableException;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Models\Bus;
use App\Models\Clients;
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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Emisión atómica: la venta, los pasajeros y los boletos de los dos tramos
 * se confirman juntos o no se crea nada; la entrega (PDF o link) ocurre
 * después del commit.
 */
class EmissionAtomicityTest extends TestCase
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

        $this->oran = Location::create(['name' => 'Orán A', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta A', 'is_active' => true]);

        $this->bus = Bus::create(['name' => 'Linea A', 'plate' => 'AAA999', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $this->outboundTrip = $this->makeTrip($this->oran, $this->salta, '08:00', '2026-10-01');
        $this->returnTrip = $this->makeTrip($this->salta, $this->oran, '17:00', '2026-10-02');
    }

    public function test_si_falla_la_vuelta_no_queda_nada_de_la_venta(): void
    {
        // El asiento 1 de la vuelta ya está vendido: la ida se crearía, pero la vuelta falla.
        $existing = $this->sellExisting($this->returnTrip, $this->seat('1'));

        try {
            $this->emit($this->roundTripPayload());
            $this->fail('La emisión debió fallar por la vuelta.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('vuelta', $e->getMessage());
        }

        $this->assertSame([$existing->sale_id], Sale::pluck('id')->all(), 'Solo debe quedar la venta previa.');
        $this->assertSame([$existing->id], Ticket::withTrashed()->pluck('id')->all(), 'No deben quedar boletos de ida.');
        $this->assertSame(1, Passenger::count(), 'No deben quedar pasajeros de la venta fallida.');
    }

    public function test_venta_mixta_con_fallo_en_la_ida_no_deja_el_boleto_pendiente(): void
    {
        Clients::create(['dni' => '12345678', 'nombre' => 'Juan', 'apellido' => 'Pérez', 'can_buy' => true]);
        $this->sellExisting($this->outboundTrip, $this->seat('1'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        $payload = array_merge($this->roundTripPayload(), [
            'sell_without_date_vuelta' => true,
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ]);

        try {
            $this->emit($payload);
            $this->fail('La emisión debió fallar por la ida.');
        } catch (\Exception) {
            // OK: el asiento de ida está ocupado.
        }

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, Ticket::withTrashed()->count(), 'No debe quedar la vuelta pendiente.');
        $this->assertSame(0, Ticket::whereNull('trip_id')->count());
    }

    public function test_una_venta_exitosa_crea_todo_y_libera_las_reservas(): void
    {
        $sessionId = session()->getId();
        SeatReservation::create([
            'trip_id' => $this->outboundTrip->id,
            'seat_id' => $this->seat('1')->id,
            'user_session_id' => $sessionId,
            'expires_at' => now()->addMinutes(5),
        ]);

        $ticket = $this->emit($this->roundTripPayload());

        $this->assertSame($this->outboundTrip->id, $ticket->trip_id);
        $this->assertSame(1, Sale::count());
        $this->assertSame(2, Ticket::count());
        $this->assertSame($this->returnTrip->id, Ticket::where('price', 0)->first()->trip_id);
        $this->assertSame(0, SeatReservation::where('user_session_id', $sessionId)->count());
    }

    public function test_la_emision_exitosa_conserva_las_reservas_de_otra_venta_en_curso(): void
    {
        $sessionId = session()->getId();
        $otherSaleTrip = $this->makeTrip($this->oran, $this->salta, '20:00', '2026-10-03');
        $this->reserveFor($sessionId, $this->outboundTrip, '1');
        $this->reserveFor($sessionId, $this->returnTrip, '1');
        $this->reserveFor($sessionId, $otherSaleTrip, '2');

        $this->emit($this->roundTripPayload());

        $this->assertSame([$otherSaleTrip->id], SeatReservation::where('user_session_id', $sessionId)->pluck('trip_id')->all());
    }

    public function test_la_emision_fallida_libera_solo_los_viajes_de_la_venta(): void
    {
        $sessionId = session()->getId();
        $otherSaleTrip = $this->makeTrip($this->oran, $this->salta, '20:00', '2026-10-03');
        $this->sellExisting($this->returnTrip, $this->seat('1'));
        $this->reserveFor($sessionId, $this->outboundTrip, '1');
        $this->reserveFor($sessionId, $otherSaleTrip, '2');

        try {
            $this->emit($this->roundTripPayload());
            $this->fail('La emisión debió fallar por la vuelta.');
        } catch (\Exception) {
            // OK: el asiento de vuelta está ocupado.
        }

        $this->assertSame([$otherSaleTrip->id], SeatReservation::where('user_session_id', $sessionId)->pluck('trip_id')->all());
    }

    public function test_create_tickets_with_lock_no_crea_ninguno_si_un_asiento_esta_ocupado(): void
    {
        $existing = $this->sellExisting($this->outboundTrip, $this->seat('2'));
        $sale = Sale::create(['user_id' => auth()->id(), 'sale_date' => now(), 'total_amount' => 0]);
        $passenger = Passenger::create(['first_name' => 'B', 'last_name' => 'C', 'dni' => '30111222', 'passenger_type' => 'adult']);

        $base = [
            'sale_id' => $sale->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 100,
        ];

        try {
            $this->outboundTrip->createTicketsWithLock([
                $base + ['seat_id' => $this->seat('1')->id],
                $base + ['seat_id' => $this->seat('2')->id],
            ]);
            $this->fail('Debió lanzar SeatUnavailableException.');
        } catch (SeatUnavailableException $e) {
            $this->assertSame(['2'], $e->seatNumbers);
        }

        $this->assertSame([$existing->id], Ticket::pluck('id')->all(), 'No debe crearse el boleto del asiento 1.');
    }

    public function test_si_falla_el_pdf_la_venta_queda_emitida(): void
    {
        $brokenViews = storage_path('framework/testing/broken-pdf-views');
        File::ensureDirectoryExists($brokenViews.'/tickets/pdf');
        File::put(
            $brokenViews.'/tickets/pdf/passenger-tickets.blade.php',
            '@php throw new \RuntimeException(\'PDF roto\'); @endphp',
        );
        View::getFinder()->prependLocation($brokenViews);

        try {
            $ticket = $this->emit($this->roundTripPayload());
        } finally {
            File::deleteDirectory($brokenViews);
        }

        $this->assertNotNull($ticket->fresh());
        $this->assertSame(1, Sale::count());
        $this->assertSame(2, Ticket::count());

        $titles = collect(session('filament.notifications', []))->pluck('title')->all();
        $this->assertContains('Error al generar tickets PDF', $titles);
    }

    // ====================== Helpers ======================

    private function emit(array $data): Ticket
    {
        $method = new \ReflectionMethod(CreateTicket::class, 'handleRecordCreation');

        return $method->invoke(app(CreateTicket::class), $data);
    }

    private function reserveFor(string $sessionId, Trip $trip, string $seatNumber): void
    {
        SeatReservation::create([
            'trip_id' => $trip->id,
            'seat_id' => $this->seat($seatNumber)->id,
            'user_session_id' => $sessionId,
            'expires_at' => now()->addMinutes(5),
        ]);
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

    private function sellExisting(Trip $trip, Seat $seat): Ticket
    {
        $sale = Sale::create(['user_id' => auth()->id(), 'sale_date' => now(), 'total_amount' => 100]);
        $passenger = Passenger::create(['first_name' => 'Otro', 'last_name' => 'Pasajero', 'dni' => '40111222', 'passenger_type' => 'adult']);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'seat_id' => $seat->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 100,
        ]);
    }

    private function roundTripPayload(): array
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
            'is_round_trip' => true,
            'trip_id' => $this->outboundTrip->id,
            'seat_ids' => [$this->seat('1')->id],
            'return_trip_id' => $this->returnTrip->id,
            'return_seat_ids' => [$this->seat('1')->id],
        ];
    }
}
