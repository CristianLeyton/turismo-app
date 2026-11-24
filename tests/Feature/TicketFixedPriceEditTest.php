<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketEdit;
use App\Models\User;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Editar boletos con precio fijo: el precio no se edita a mano y agregar o
 * quitar el menor ajusta un seguro en el boleto de ida del pasajero.
 */
class TicketFixedPriceEditTest extends TestCase
{
    use ConfiguresFixedPrice;
    use RefreshDatabase;

    private User $seller;

    private PaymentMethod $method;

    private Location $oran;

    private Location $salta;

    private Passenger $passenger;

    private Sale $sale;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();
        Permissions::syncToDatabase();
        $this->configureFixedPrice(true, 5000, 800);

        $this->seller = User::factory()->create()->assignRole(Permissions::ROLE_SELLER);
        $this->seller->givePermissionTo('tickets.edit');

        $this->method = PaymentMethod::create(['label' => 'Efectivo', 'is_active' => true]);
        $this->oran = Location::create(['name' => 'Orán F', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta F', 'is_active' => true]);
        $this->passenger = Passenger::create([
            'first_name' => 'Pedro',
            'last_name' => 'Gomez',
            'dni' => '30000111',
            'phone_number' => '3875550001',
            'passenger_type' => 'adult',
        ]);
        $this->sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);
    }

    // ====================== Precio bloqueado ======================

    public function test_con_precio_fijo_el_precio_no_se_puede_editar(): void
    {
        $ticket = $this->oneWayTicket(5000);

        $this->editor($ticket)
            ->assertFormFieldDisabled('price')
            ->assertSee('Lo calcula el precio fijo');
    }

    public function test_un_precio_manipulado_se_ignora(): void
    {
        $ticket = $this->oneWayTicket(5000);

        $this->editor($ticket)
            ->set('data.price', '1')
            ->set('data.first_name', 'Juan')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(5000, $ticket->fresh()->price);
        $this->assertDatabaseMissing('ticket_edits', ['ticket_id' => $ticket->id, 'field' => 'price']);
    }

    public function test_fijo_y_sin_modo_siguen_bloqueados(): void
    {
        $fixed = $this->oneWayTicket(5000, pricing: $this->fixed(800));

        $this->editor($fixed)->assertFormFieldDisabled('price');

        $this->editor($fixed)
            ->set('data.price', '1')
            ->set('data.first_name', 'Juan')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(5000, $fixed->fresh()->price);
    }

    public function test_con_precio_fijo_un_boleto_vendido_a_mano_se_edita_a_mano(): void
    {
        $ticket = $this->oneWayTicket(4500, pricing: ['price_mode' => Ticket::PRICE_MODE_MANUAL]);

        $this->editor($ticket)
            ->assertFormFieldEnabled('price')
            ->set('data.price', '5000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(5000, $ticket->fresh()->price);
        $this->assertEquals(5000, $this->sale->fresh()->total_amount);

        $edit = TicketEdit::where('ticket_id', $ticket->id)->where('field', 'price')->sole();
        $this->assertEquals(4500, (float) $edit->old_value);
        $this->assertEquals(5000, (float) $edit->new_value);
    }

    public function test_la_vuelta_de_un_boleto_vendido_a_mano_sigue_sin_precio_propio(): void
    {
        [$ida, $vuelta] = $this->roundTrip(9000, withChild: false, pricing: ['price_mode' => Ticket::PRICE_MODE_MANUAL]);

        $this->editor($vuelta)
            ->assertFormFieldDisabled('price')
            ->set('data.price', '7000')
            ->set('data.first_name', 'Juan')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(0, $vuelta->fresh()->price);
        $this->assertEquals(9000, $ida->fresh()->price);
    }

    // ====================== Ajuste del seguro ======================

    public function test_agregar_el_menor_en_la_ida_suma_el_seguro(): void
    {
        $ticket = $this->oneWayTicket(5000);

        $this->addChild($this->editor($ticket))->call('save')->assertHasNoErrors();

        $this->assertEquals(5800, $ticket->fresh()->price);
        $this->assertEquals(5800, $this->sale->fresh()->total_amount);
    }

    public function test_agregar_el_menor_en_la_vuelta_ajusta_la_ida(): void
    {
        [$ida, $vuelta] = $this->roundTrip(10000, withChild: false);

        $this->addChild($this->editor($vuelta))
            ->set('data.edit_reason', 'Avisó por teléfono')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(10800, $ida->fresh()->price);
        $this->assertEquals(0, $vuelta->fresh()->price);
        $this->assertEquals(10800, $this->sale->fresh()->total_amount);

        $edit = TicketEdit::where('ticket_id', $ida->id)->where('field', 'price')->sole();
        $this->assertSame('10000', $edit->old_value);
        $this->assertSame('10800', $edit->new_value);
        $this->assertSame('Avisó por teléfono (menor modificado en la vuelta)', $edit->reason);
    }

    public function test_quitar_el_menor_en_la_ida_resta_el_seguro(): void
    {
        [$ida] = $this->roundTrip(11600, withChild: true);

        $this->editor($ida)
            ->set('data.travels_with_child', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(10800, $ida->fresh()->price);
    }

    public function test_el_ajuste_usa_el_seguro_vigente_del_mismo_periodo(): void
    {
        $ticket = $this->oneWayTicket(5800, withChild: true);
        $this->configureFixedPrice(true, 5000, 1000);

        $this->editor($ticket)
            ->set('data.travels_with_child', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(4800, $ticket->fresh()->price);
    }

    public function test_el_ajuste_usa_el_seguro_del_periodo_de_la_venta(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $this->seasonPeriods();
        $this->sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);
        $ticket = $this->oneWayTicket(75000, withChild: true);

        $this->travelTo(Carbon::parse('2026-12-05 10:00'));

        $this->editor($ticket)
            ->set('data.travels_with_child', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(70000, $ticket->fresh()->price);
    }

    public function test_una_venta_anterior_al_primer_periodo_usa_el_mas_antiguo(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 10:00'));
        $this->sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);
        $ticket = $this->oneWayTicket(5000);

        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $this->seasonPeriods();

        $this->addChild($this->editor($ticket))->call('save')->assertHasNoErrors();

        $this->assertEquals(10000, $ticket->fresh()->price);
    }

    public function test_desde_la_vuelta_se_usa_el_periodo_de_la_venta(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $this->seasonPeriods();
        $this->sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);
        [$ida, $vuelta] = $this->roundTrip(140000, withChild: false);

        $this->travelTo(Carbon::parse('2026-12-05 10:00'));

        $this->addChild($this->editor($vuelta))
            ->set('data.edit_reason', 'Avisó por teléfono')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(145000, $ida->fresh()->price);
        $this->assertEquals(0, $vuelta->fresh()->price);
    }

    public function test_con_seguro_cero_el_precio_no_cambia(): void
    {
        $this->configureFixedPrice(true, 5000, 0);
        $ticket = $this->oneWayTicket(5000);

        $this->addChild($this->editor($ticket))->call('save')->assertHasNoErrors();

        $this->assertEquals(5000, $ticket->fresh()->price);
        $this->assertDatabaseMissing('ticket_edits', ['ticket_id' => $ticket->id, 'field' => 'price']);
    }

    public function test_vuelta_sin_ida_vigente_no_guarda_nada(): void
    {
        [$ida, $vuelta] = $this->roundTrip(10000, withChild: false);
        $ida->delete();

        $this->addChild($this->editor($vuelta))->call('save')->assertNotified();

        $this->assertFalse((bool) $vuelta->fresh()->travels_with_child);
        $this->assertSame(0, $this->passenger->children()->count());
        $this->assertSame(0, TicketEdit::count());
    }

    public function test_el_resumen_de_confirmacion_muestra_el_cambio_de_precio(): void
    {
        $ticket = $this->oneWayTicket(5000);

        $summary = $this->summary($this->addChild($this->editor($ticket)));

        $this->assertStringContainsString('Precio: $5.000 → $5.800', $summary);
    }

    public function test_el_resumen_desde_la_vuelta_nombra_el_boleto_de_ida(): void
    {
        [, $vuelta] = $this->roundTrip(10000, withChild: false);

        $summary = $this->summary($this->addChild($this->editor($vuelta)));

        $this->assertStringContainsString('Precio del boleto de ida: $10.000 → $10.800', $summary);
    }

    // ====================== Ajuste según el modo registrado ======================

    public function test_fijo_agregar_el_menor_en_la_ida_suma_el_seguro_registrado(): void
    {
        $this->configureFixedPrice(true, 5000, 3000);
        $ticket = $this->oneWayTicket(5000, pricing: $this->fixed(800));

        $this->addChild($this->editor($ticket))->call('save')->assertHasNoErrors();

        $this->assertEquals(5800, $ticket->fresh()->price);
    }

    public function test_fijo_agregar_el_menor_en_la_vuelta_ajusta_la_ida_con_su_seguro(): void
    {
        $this->configureFixedPrice(true, 5000, 3000);
        [$ida, $vuelta] = $this->roundTrip(10000, withChild: false, pricing: $this->fixed(800));

        $this->addChild($this->editor($vuelta))
            ->set('data.edit_reason', 'Avisó por teléfono')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(10800, $ida->fresh()->price);
        $this->assertEquals(0, $vuelta->fresh()->price);
    }

    public function test_fijo_quitar_el_menor_en_la_ida_resta_el_seguro_registrado(): void
    {
        [$ida] = $this->roundTrip(11600, withChild: true, pricing: $this->fixed(800));

        $this->editor($ida)
            ->set('data.travels_with_child', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(10800, $ida->fresh()->price);
    }

    public function test_fijo_ignora_el_seguro_cambiado_en_la_grilla(): void
    {
        $ticket = $this->oneWayTicket(5800, withChild: true, pricing: $this->fixed(800));
        $this->configureFixedPrice(true, 5000, 1000);

        $this->editor($ticket)
            ->set('data.travels_with_child', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(5000, $ticket->fresh()->price);
    }

    public function test_fijo_con_el_periodo_de_la_venta_borrado_usa_su_seguro(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $this->seasonPeriods();
        $this->sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);
        $ticket = $this->oneWayTicket(70000, pricing: $this->fixed(5000));

        $this->travelTo(Carbon::parse('2026-12-05 10:00'));
        $this->setFixedPricePeriods([['desde' => '2026-12-02', 'boleto' => 160000, 'seguro' => 8000]]);

        $this->addChild($this->editor($ticket))->call('save')->assertHasNoErrors();

        $this->assertEquals(75000, $ticket->fresh()->price);
    }

    public function test_manual_agregar_el_menor_no_cambia_el_precio(): void
    {
        $ticket = $this->oneWayTicket(4500, pricing: ['price_mode' => Ticket::PRICE_MODE_MANUAL]);

        $this->addChild($this->editor($ticket))->call('save')->assertHasNoErrors();

        $this->assertEquals(4500, $ticket->fresh()->price);
        $this->assertTrue((bool) $ticket->fresh()->travels_with_child);
        $this->assertDatabaseMissing('ticket_edits', ['ticket_id' => $ticket->id, 'field' => 'price']);
    }

    public function test_manual_desde_la_vuelta_no_cambia_el_precio_de_la_ida(): void
    {
        [$ida, $vuelta] = $this->roundTrip(9000, withChild: false, pricing: ['price_mode' => Ticket::PRICE_MODE_MANUAL]);

        $this->addChild($this->editor($vuelta))
            ->set('data.edit_reason', 'Avisó por teléfono')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(9000, $ida->fresh()->price);
        $this->assertTrue((bool) $vuelta->fresh()->travels_with_child);
    }

    // ====================== Precio fijo apagado ======================

    public function test_con_precio_fijo_apagado_el_menor_no_cambia_el_precio_y_se_edita_a_mano(): void
    {
        $this->configureFixedPrice(false, 5000, 800);
        $ticket = $this->oneWayTicket(5000);

        $this->addChild($this->editor($ticket)->assertFormFieldEnabled('price'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(5000, $ticket->fresh()->price);

        $this->editor($ticket->fresh())
            ->set('data.price', '7000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(7000, $ticket->fresh()->price);
        $this->assertEquals(7000, $this->sale->fresh()->total_amount);
    }

    // ====================== Helpers ======================

    /** Períodos de temporada: seguro 5.000 desde el 20/11 y 8.000 desde el 02/12. */
    private function seasonPeriods(): void
    {
        Setting::set(Setting::PRECIO_FIJO_ACTIVO, '1');
        $this->setFixedPricePeriods([
            ['desde' => '2026-11-20', 'boleto' => 70000, 'seguro' => 5000],
            ['desde' => '2026-12-02', 'boleto' => 160000, 'seguro' => 8000],
        ]);
    }

    /**
     * @return array{price_mode: string, child_insurance: int}
     */
    private function fixed(int $insurance): array
    {
        return ['price_mode' => Ticket::PRICE_MODE_FIXED, 'child_insurance' => $insurance];
    }

    private function editor(Ticket $ticket): Testable
    {
        return Livewire::actingAs($this->seller)->test(EditTicket::class, ['record' => $ticket->getKey()]);
    }

    private function addChild(Testable $component): Testable
    {
        return $component
            ->set('data.travels_with_child', true)
            ->set('data.child_data.first_name', 'Tomi')
            ->set('data.child_data.last_name', 'Gomez')
            ->set('data.child_data.dni', '55555555');
    }

    private function summary(Testable $component): string
    {
        return (new \ReflectionMethod(EditTicket::class, 'confirmationSummary'))->invoke($component->instance());
    }

    private function addExistingChild(): void
    {
        $this->passenger->children()->create([
            'first_name' => 'Tomi',
            'last_name' => 'Gomez',
            'dni' => '55555555',
            'passenger_type' => 'child',
        ]);
    }

    /**
     * @param  array<string, mixed>  $pricing  Modo y seguro registrados en la venta.
     */
    private function oneWayTicket(int $price, bool $withChild = false, array $pricing = []): Ticket
    {
        if ($withChild) {
            $this->addExistingChild();
        }

        $ticket = Ticket::create([
            'sale_id' => $this->sale->id,
            'passenger_id' => $this->passenger->id,
            'price' => $price,
            'payment_method' => $this->method->code,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'travels_with_child' => $withChild,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
        ] + $pricing);
        $this->sale->recalculateTotal();

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $pricing  Modo y seguro registrados en la ida.
     * @return array{Ticket, Ticket}
     */
    private function roundTrip(int $price, bool $withChild, array $pricing = []): array
    {
        if ($withChild) {
            $this->addExistingChild();
        }

        $base = [
            'sale_id' => $this->sale->id,
            'passenger_id' => $this->passenger->id,
            'payment_method' => $this->method->code,
            'is_round_trip' => true,
            'travels_with_child' => $withChild,
        ];

        $ida = Ticket::create($base + [
            'price' => $price,
            'is_return_leg' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
        ] + $pricing);
        $vuelta = Ticket::create($base + [
            'price' => 0,
            'is_return_leg' => true,
            'origin_location_id' => $this->salta->id,
            'destination_location_id' => $this->oran->id,
        ]);
        $this->sale->recalculateTotal();

        return [$ida, $vuelta];
    }
}
