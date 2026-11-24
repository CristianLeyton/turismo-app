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
 * Modo de precio del boleto: se registra en la ida y la vuelta lo lee de ahí.
 */
class TicketPriceModeTest extends TestCase
{
    use RefreshDatabase;

    private Sale $sale;

    private Passenger $passenger;

    private Location $oran;

    private Location $salta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sale = Sale::create(['user_id' => User::factory()->create()->id, 'sale_date' => now()]);
        $this->oran = Location::create(['name' => 'Orán M', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta M', 'is_active' => true]);
        $this->passenger = Passenger::create([
            'first_name' => 'Ana',
            'last_name' => 'Ruiz',
            'dni' => '30111222',
            'passenger_type' => 'adult',
        ]);
    }

    public function test_un_boleto_sin_modo_queda_en_null_con_su_precio_intacto(): void
    {
        $ticket = $this->ticket(['price' => 4500, 'is_round_trip' => false, 'is_return_leg' => false]);

        $fresh = $ticket->fresh();
        $this->assertNull($fresh->price_mode);
        $this->assertNull($fresh->child_insurance);
        $this->assertEquals(4500, $fresh->price);
        $this->assertFalse($fresh->isPricedManually());
    }

    public function test_el_seguro_se_guarda_como_entero(): void
    {
        $ticket = $this->ticket([
            'price' => 5000,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'price_mode' => Ticket::PRICE_MODE_FIXED,
            'child_insurance' => 800,
        ]);

        $this->assertSame(800, $ticket->fresh()->child_insurance);
        $this->assertFalse($ticket->fresh()->isPricedManually());
    }

    public function test_una_ida_manual_responde_que_se_vendio_a_mano(): void
    {
        $ticket = $this->ticket([
            'price' => 4500,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'price_mode' => Ticket::PRICE_MODE_MANUAL,
        ]);

        $this->assertTrue($ticket->fresh()->isPricedManually());
    }

    public function test_la_vuelta_lee_el_modo_de_su_ida(): void
    {
        $this->ticket(['price' => 9000, 'is_round_trip' => true, 'is_return_leg' => false, 'price_mode' => Ticket::PRICE_MODE_MANUAL]);
        $vuelta = $this->ticket(['price' => 0, 'is_round_trip' => true, 'is_return_leg' => true]);

        $this->assertNull($vuelta->fresh()->price_mode);
        $this->assertTrue($vuelta->fresh()->isPricedManually());
    }

    public function test_la_vuelta_sin_ida_vigente_no_es_manual(): void
    {
        $ida = $this->ticket(['price' => 9000, 'is_round_trip' => true, 'is_return_leg' => false, 'price_mode' => Ticket::PRICE_MODE_MANUAL]);
        $vuelta = $this->ticket(['price' => 0, 'is_round_trip' => true, 'is_return_leg' => true]);
        $ida->delete();

        $this->assertNull($vuelta->fresh()->pricedLeg());
        $this->assertFalse($vuelta->fresh()->isPricedManually());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ticket(array $attributes): Ticket
    {
        return Ticket::create($attributes + [
            'sale_id' => $this->sale->id,
            'passenger_id' => $this->passenger->id,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
        ]);
    }
}
