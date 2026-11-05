<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Passenger;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La marca `is_return_leg` es la única fuente de verdad del tramo de vuelta
 * (change marca-tramo-vuelta, hallazgo H2).
 */
class ReturnLegMarkTest extends TestCase
{
    use RefreshDatabase;

    private Sale $sale;

    private Passenger $passenger;

    private Location $oran;

    private Location $salta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta', 'is_active' => true]);
        $this->passenger = Passenger::create([
            'first_name' => 'Pedro',
            'last_name' => 'Gomez',
            'dni' => '30000111',
            'phone_number' => '3875550001',
            'passenger_type' => 'adult',
        ]);
        $this->sale = Sale::create(['user_id' => User::factory()->create()->id, 'sale_date' => now()]);
    }

    public function test_sin_marca_indicada_la_convencion_marca_la_vuelta(): void
    {
        $ticket = $this->createRoundTripTicket(['price' => 0]);

        $this->assertTrue((bool) $ticket->fresh()->is_return_leg);
        $this->assertTrue($ticket->fresh()->isReturnLeg());
    }

    public function test_la_marca_explicita_de_ida_no_se_pisa_con_precio_0(): void
    {
        $ticket = $this->createRoundTripTicket(['price' => 0, 'is_return_leg' => false]);

        $this->assertFalse((bool) $ticket->fresh()->is_return_leg);
        $this->assertFalse($ticket->fresh()->isReturnLeg());
    }

    public function test_sin_marca_y_con_precio_la_convencion_no_aplica(): void
    {
        $ticket = $this->createRoundTripTicket(['price' => 15000]);

        $this->assertFalse((bool) $ticket->fresh()->is_return_leg);
    }

    public function test_la_lectura_no_infiere_la_vuelta_por_precio(): void
    {
        $ticket = $this->createRoundTripTicket(['price' => 0, 'is_return_leg' => false]);

        // Un boleto viejo sin marca que cumple la convención: ya no se lee como vuelta.
        $this->assertFalse($ticket->fresh()->isReturnLeg());
    }

    public function test_ida_mixta_con_precio_es_parte_de_ida_y_vuelta(): void
    {
        $ida = $this->createRoundTripTicket(['price' => 15000, 'is_return_leg' => false]);

        $this->assertTrue($ida->fresh()->isPartOfRoundTrip());
        $this->assertFalse($ida->fresh()->isReturnLeg());
        $this->assertEquals(15000.0, $ida->fresh()->display_price);
    }

    public function test_ida_a_precio_0_muestra_su_propio_precio(): void
    {
        $ida = $this->createRoundTripTicket(['price' => 0, 'is_return_leg' => false]);
        $this->createRoundTripTicket([
            'price' => 0,
            'is_return_leg' => true,
            'origin_location_id' => $this->salta->id,
            'destination_location_id' => $this->oran->id,
        ]);

        $this->assertTrue($ida->fresh()->isPartOfRoundTrip());
        $this->assertEquals(0.0, $ida->fresh()->display_price);
    }

    public function test_la_vuelta_muestra_el_precio_de_su_ida(): void
    {
        $this->createRoundTripTicket(['price' => 15000, 'is_return_leg' => false]);
        $vuelta = $this->createRoundTripTicket([
            'price' => 0,
            'is_return_leg' => true,
            'origin_location_id' => $this->salta->id,
            'destination_location_id' => $this->oran->id,
        ]);

        $this->assertTrue($vuelta->fresh()->isPartOfRoundTrip());
        $this->assertEquals(15000.0, $vuelta->fresh()->display_price);
    }

    public function test_solo_ida_no_es_parte_de_ida_y_vuelta(): void
    {
        $ticket = $this->createRoundTripTicket(['price' => 0, 'is_round_trip' => false]);

        $this->assertFalse($ticket->fresh()->isPartOfRoundTrip());
        $this->assertFalse($ticket->fresh()->isReturnLeg());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRoundTripTicket(array $overrides): Ticket
    {
        return Ticket::create(array_merge([
            'sale_id' => $this->sale->id,
            'passenger_id' => $this->passenger->id,
            'trip_id' => null,
            'return_trip_id' => null,
            'is_round_trip' => true,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
        ], $overrides));
    }
}
