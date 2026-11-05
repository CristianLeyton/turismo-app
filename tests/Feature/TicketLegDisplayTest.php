<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identificación del tramo (ida / vuelta / sólo ida) en el resumen del boleto
 * ("Ver boleto" del panel y página del link) y en el PDF.
 */
class TicketLegDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Location $oran;

    private Location $salta;

    private Bus $bus;

    private Schedule $schedule;

    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oran = Location::create(['name' => 'Orán T', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta T', 'is_active' => true]);

        $this->bus = Bus::create(['name' => 'Linea T', 'plate' => 'TTT111', 'seat_count' => 4, 'floors' => 1]);
        foreach (range(1, 4) as $n) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta T', 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);

        $this->schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana T',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $this->sale = Sale::create([
            'user_id' => User::factory()->vendedor()->create()->id,
            'sale_date' => now(),
            'total_amount' => 15000,
        ]);
    }

    // -----------------------------------------------------------------
    // Modelo: boleto de vuelta hermano
    // -----------------------------------------------------------------

    public function test_la_ida_encuentra_su_boleto_de_vuelta(): void
    {
        [$ida, $vuelta] = $this->roundTrip(idaDate: null, vueltaDate: null);

        $this->assertTrue($ida->roundTripReturnTicket()->is($vuelta));
        $this->assertNull($vuelta->roundTripReturnTicket(), 'La vuelta no tiene vuelta propia.');
        $this->assertTrue($vuelta->roundTripOutboundTicket()->is($ida));
    }

    public function test_un_boleto_solo_de_ida_no_tiene_vuelta(): void
    {
        $ticket = $this->oneWay(null);

        $this->assertNull($ticket->roundTripReturnTicket());
    }

    public function test_una_vuelta_borrada_no_se_toma_como_hermana(): void
    {
        [$ida, $vuelta] = $this->roundTrip(idaDate: null, vueltaDate: null);
        $vuelta->delete();

        $this->assertNull($ida->fresh()->roundTripReturnTicket());
    }

    // -----------------------------------------------------------------
    // Resumen del boleto
    // -----------------------------------------------------------------

    public function test_ida_y_vuelta_sin_fecha_resumen_de_la_ida(): void
    {
        [$ida, $vuelta] = $this->roundTrip(idaDate: null, vueltaDate: null);

        $html = $this->renderSummary($ida);

        $this->assertStringContainsString('Ida:', $html);
        $this->assertStringContainsString('Asiento ida', $html);
        $this->assertStringContainsString('Vuelta:', $html);
        $this->assertStringContainsString("Boleto de vuelta N°{$vuelta->id}", $html);
        $this->assertSame(2, substr_count($html, 'Fecha y horario a confirmar'), 'Ida y vuelta pendientes.');
        $this->assertStringNotContainsString('Asiento vuelta', $html, 'La vuelta sin viaje no tiene asiento que mostrar.');
        $this->assertStringNotContainsString('Viaje de vuelta', $html);
    }

    public function test_ida_y_vuelta_sin_fecha_resumen_de_la_vuelta(): void
    {
        [$ida, $vuelta] = $this->roundTrip(idaDate: null, vueltaDate: null);

        $html = $this->renderSummary($vuelta);

        $this->assertStringContainsString('Vuelta:', $html);
        $this->assertStringContainsString('Asiento vuelta', $html);
        $this->assertStringContainsString('Fecha y horario a confirmar', $html);
        $this->assertStringContainsString("Boleto de ida N°{$ida->id}", $html);
        $this->assertStringNotContainsString('Asiento ida', $html);
        $this->assertStringNotContainsString('Viaje de vuelta', $html);
        $this->assertStringNotContainsString('Ida:', $html);
    }

    public function test_ida_con_fecha_y_vuelta_sin_fecha(): void
    {
        [$ida, $vuelta] = $this->roundTrip(idaDate: '2030-10-01', vueltaDate: null);

        $html = $this->renderSummary($ida);

        $this->assertStringContainsString('01/10/2030', $html);
        $this->assertStringContainsString('Linea T', $html);
        $this->assertStringContainsString('Vuelta:', $html);
        $this->assertStringContainsString('Fecha y horario a confirmar', $html);
        $this->assertStringContainsString("Boleto de vuelta N°{$vuelta->id}", $html);
    }

    public function test_ida_sin_fecha_y_vuelta_con_fecha(): void
    {
        [$ida, $vuelta] = $this->roundTrip(idaDate: null, vueltaDate: '2030-10-08', vueltaSeat: '3');

        $html = $this->renderSummary($ida);

        $this->assertStringContainsString('Ida:', $html);
        $this->assertStringContainsString('Fecha y horario a confirmar', $html);
        $this->assertStringContainsString('08/10/2030', $html);
        $this->assertStringContainsString('Asiento vuelta', $html);
        $this->assertMatchesRegularExpression('/Asiento vuelta.*?>\s*3\s*</s', $html);
        $this->assertStringContainsString("Boleto de vuelta N°{$vuelta->id}", $html);
    }

    public function test_la_ida_muestra_la_vuelta_reprogramada(): void
    {
        [$ida, $vuelta] = $this->roundTrip(idaDate: '2030-10-01', vueltaDate: null);

        // La vuelta recibe fecha y asiento después de la venta; la ida
        // conserva return_trip_id NULL.
        $vuelta->update([
            'trip_id' => $this->trip('2030-10-15')->id,
            'seat_id' => $this->seat('4')->id,
        ]);

        $html = $this->renderSummary($ida->fresh());

        $this->assertStringContainsString('15/10/2030', $html);
        $this->assertMatchesRegularExpression('/Asiento vuelta.*?>\s*4\s*</s', $html);
        $this->assertSame(0, substr_count($html, 'Fecha y horario a confirmar'));
    }

    public function test_la_vuelta_sin_ida_no_falla(): void
    {
        [$ida, $vuelta] = $this->roundTrip(idaDate: null, vueltaDate: null);
        $ida->delete();

        $html = $this->renderSummary($vuelta->fresh());

        $this->assertStringContainsString('Vuelta:', $html);
        $this->assertStringNotContainsString('Boleto de ida N°', $html);
    }

    public function test_solo_ida_sin_fecha_no_muestra_vuelta(): void
    {
        $ticket = $this->oneWay(null);

        $html = $this->renderSummary($ticket);

        $this->assertStringContainsString('Ida:', $html);
        $this->assertStringContainsString('Fecha y horario a confirmar', $html);
        $this->assertStringNotContainsString('Vuelta', $html);
    }

    public function test_ver_boleto_del_panel_muestra_el_tramo(): void
    {
        [$ida, $vuelta] = $this->roundTrip(idaDate: null, vueltaDate: null);
        $this->actingAs(User::factory()->vendedor()->create());

        $this->get(\App\Filament\Resources\Tickets\TicketResource::getUrl('view', ['record' => $vuelta]))
            ->assertOk()
            ->assertSee('Asiento vuelta')
            ->assertSee("Boleto de ida N°{$ida->id}")
            ->assertDontSee('Viaje de vuelta');
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function renderSummary(Ticket $ticket): string
    {
        return view('tickets.infolist-summary-header', ['record' => $ticket])->render();
    }

    private function trip(string $date): Trip
    {
        return Trip::create([
            'route_id' => $this->schedule->route_id,
            'schedule_id' => $this->schedule->id,
            'bus_id' => $this->bus->id,
            'trip_date' => $date,
        ]);
    }

    private function seat(string $number): Seat
    {
        return Seat::query()->where('bus_id', $this->bus->id)->where('seat_number', $number)->firstOrFail();
    }

    private function passenger(string $dni = '30111222'): Passenger
    {
        return Passenger::create([
            'first_name' => 'Juan',
            'last_name' => 'Tramo',
            'dni' => $dni,
            'passenger_type' => 'adult',
        ]);
    }

    private function oneWay(?string $date): Ticket
    {
        return Ticket::create([
            'sale_id' => $this->sale->id,
            'trip_id' => $date ? $this->trip($date)->id : null,
            'seat_id' => $date ? $this->seat('1')->id : null,
            'bus_id' => $this->bus->id,
            'passenger_id' => $this->passenger()->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 15000,
        ]);
    }

    /**
     * Diferido como lo emite la venta: ida con el precio, vuelta a precio 0
     * con origen y destino invertidos.
     *
     * @return array{0: Ticket, 1: Ticket}
     */
    private function roundTrip(?string $idaDate, ?string $vueltaDate, string $vueltaSeat = '2', ?Passenger $passenger = null): array
    {
        $passenger ??= $this->passenger();
        $returnTrip = $vueltaDate ? $this->trip($vueltaDate) : null;

        $ida = Ticket::create([
            'sale_id' => $this->sale->id,
            'trip_id' => $idaDate ? $this->trip($idaDate)->id : null,
            'seat_id' => $idaDate ? $this->seat('1')->id : null,
            'return_trip_id' => $returnTrip?->id,
            'bus_id' => $this->bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 15000,
        ]);

        $vuelta = Ticket::create([
            'sale_id' => $this->sale->id,
            'trip_id' => $returnTrip?->id,
            'seat_id' => $returnTrip ? $this->seat($vueltaSeat)->id : null,
            'bus_id' => $this->bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => true,
            'origin_location_id' => $this->salta->id,
            'destination_location_id' => $this->oran->id,
            'price' => 0,
        ]);

        return [$ida, $vuelta];
    }
}
