<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Support\FixedTicketPrice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pasajes vendidos antes de registrar el modo de precio que dependen de un
 * período de la grilla: los que tomarían otro seguro si se borra.
 */
class LegacyFixedPriceTicketsTest extends TestCase
{
    use RefreshDatabase;

    private Bus $bus;

    private Route $route;

    private Schedule $schedule;

    private Location $oran;

    private Location $salta;

    private User $seller;

    private int $count = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-25 10:00'));

        $this->seller = User::factory()->create();
        $this->oran = Location::create(['name' => 'Orán L', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta L', 'is_active' => true]);
        $this->bus = Bus::create(['name' => 'Linea L', 'plate' => 'LLL111', 'seat_count' => 40, 'floors' => 1]);
        $this->route = Route::create(['name' => 'Orán - Salta L', 'bus_id' => $this->bus->id, 'is_active' => true]);
        $this->schedule = Schedule::create(['route_id' => $this->route->id, 'departure_time' => '08:00', 'is_active' => true]);
    }

    public function test_cuenta_los_boletos_anteriores_vendidos_en_el_periodo_y_por_viajar(): void
    {
        foreach (range(1, 3) as $i) {
            $this->oneWay('2026-11-10 09:00', $this->trip('2026-12-15'));
        }

        $this->assertSame(3, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_no_cuenta_las_ventas_fuera_del_rango(): void
    {
        $this->oneWay('2026-10-31 23:00', $this->trip('2026-12-15'));
        $this->oneWay('2026-11-20 00:30', $this->trip('2026-12-15'));

        $this->assertSame(0, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_no_cuenta_los_solo_ida_ya_viajados(): void
    {
        $this->oneWay('2026-11-10 09:00', $this->trip('2026-11-24'));

        $this->assertSame(0, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_cuenta_un_viaje_de_hoy(): void
    {
        $this->oneWay('2026-11-10 09:00', $this->trip('2026-11-25'));

        $this->assertSame(1, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_cuenta_la_ida_viajada_con_la_vuelta_por_viajar(): void
    {
        $this->roundTrip('2026-11-10 09:00', $this->trip('2026-11-12'), $this->trip('2026-12-15'));

        $this->assertSame(1, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_cuenta_la_ida_viajada_con_la_vuelta_sin_fecha(): void
    {
        $this->roundTrip('2026-11-10 09:00', $this->trip('2026-11-12'), null);

        $this->assertSame(1, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_no_cuenta_la_ida_y_vuelta_ya_viajada(): void
    {
        $this->roundTrip('2026-11-10 09:00', $this->trip('2026-11-12'), $this->trip('2026-11-14'));

        $this->assertSame(0, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_no_cuenta_la_vuelta_eliminada(): void
    {
        [, $vuelta] = $this->roundTrip('2026-11-10 09:00', $this->trip('2026-11-12'), $this->trip('2026-12-15'));
        $vuelta->delete();

        $this->assertSame(0, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_cuenta_un_boleto_sin_fecha(): void
    {
        $this->oneWay('2026-11-10 09:00', null);

        $this->assertSame(1, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_el_periodo_mas_antiguo_incluye_las_ventas_anteriores(): void
    {
        $this->oneWay('2026-10-10 09:00', $this->trip('2026-12-15'));

        $this->assertSame(1, FixedTicketPrice::legacyTicketsDependingOn(null, '2026-11-20'));
        $this->assertSame(0, FixedTicketPrice::legacyTicketsDependingOn('2026-11-20', null));
    }

    public function test_el_ultimo_periodo_no_tiene_limite_superior(): void
    {
        $this->oneWay('2026-11-25 09:00', $this->trip('2026-12-15'));

        $this->assertSame(1, FixedTicketPrice::legacyTicketsDependingOn('2026-11-20', null));
    }

    public function test_no_cuenta_los_eliminados(): void
    {
        $this->oneWay('2026-11-10 09:00', $this->trip('2026-12-15'))->delete();

        $this->assertSame(0, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_no_cuenta_los_que_tienen_modo_registrado(): void
    {
        $this->oneWay('2026-11-10 09:00', $this->trip('2026-12-15'), ['price_mode' => Ticket::PRICE_MODE_FIXED, 'child_insurance' => 800]);
        $this->oneWay('2026-11-10 09:00', $this->trip('2026-12-15'), ['price_mode' => Ticket::PRICE_MODE_MANUAL]);

        $this->assertSame(0, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
    }

    public function test_con_la_venta_eliminada_usa_la_fecha_del_boleto(): void
    {
        $sale = $this->saleAt('2026-11-25 09:00');
        $this->travelTo(Carbon::parse('2026-11-10 09:00'));
        $this->ticket($sale, $this->trip('2026-12-15'), ['is_round_trip' => false, 'is_return_leg' => false]);
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $sale->delete();

        $this->assertSame(1, FixedTicketPrice::legacyTicketsDependingOn('2026-11-01', '2026-11-20'));
        $this->assertSame(0, FixedTicketPrice::legacyTicketsDependingOn('2026-11-20', null));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function oneWay(string $soldAt, ?Trip $trip, array $attributes = []): Ticket
    {
        return $this->ticket($this->saleAt($soldAt), $trip, $attributes + ['is_round_trip' => false, 'is_return_leg' => false]);
    }

    /**
     * @return array{Ticket, Ticket}
     */
    private function roundTrip(string $soldAt, Trip $outbound, ?Trip $return): array
    {
        $sale = $this->saleAt($soldAt);
        $ida = $this->ticket($sale, $outbound, ['is_round_trip' => true, 'is_return_leg' => false, 'return_trip_id' => $return?->id]);
        $vuelta = $this->ticket($sale, $return, ['is_round_trip' => true, 'is_return_leg' => true, 'price' => 0], $ida->passenger_id);

        return [$ida, $vuelta];
    }

    /** Venta con la fecha de creación dada (la que decide el período). */
    private function saleAt(string $soldAt): Sale
    {
        $sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => $soldAt]);
        $sale->forceFill(['created_at' => $soldAt])->saveQuietly();

        return $sale;
    }

    private function trip(string $date): Trip
    {
        return Trip::create([
            'route_id' => $this->route->id,
            'schedule_id' => $this->schedule->id,
            'bus_id' => $this->bus->id,
            'trip_date' => $date,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ticket(?Sale $sale, ?Trip $trip, array $attributes, ?int $passengerId = null): Ticket
    {
        $this->count++;

        return Ticket::create($attributes + [
            'sale_id' => $sale?->id,
            'trip_id' => $trip?->id,
            'seat_id' => $trip ? Seat::create([
                'bus_id' => $this->bus->id,
                'seat_number' => (string) $this->count,
                'is_active' => true,
                'floor' => '1',
            ])->id : null,
            'passenger_id' => $passengerId ?? Passenger::create([
                'first_name' => 'Pasajero',
                'last_name' => (string) $this->count,
                'dni' => (string) (31000000 + $this->count),
                'passenger_type' => 'adult',
            ])->id,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 5000,
        ]);
    }
}
