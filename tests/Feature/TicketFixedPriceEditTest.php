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

    public function test_el_ajuste_usa_el_seguro_vigente(): void
    {
        $ticket = $this->oneWayTicket(5800, withChild: true);
        Setting::set(Setting::PRECIO_FIJO_SEGURO_MENOR, '1000');

        $this->editor($ticket)
            ->set('data.travels_with_child', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(4800, $ticket->fresh()->price);
    }

    public function test_con_seguro_cero_el_precio_no_cambia(): void
    {
        Setting::set(Setting::PRECIO_FIJO_SEGURO_MENOR, '0');
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

    private function configureFixedPrice(bool $active, int $base, int $insurance): void
    {
        Setting::set(Setting::PRECIO_FIJO_ACTIVO, $active ? '1' : '0');
        Setting::set(Setting::PRECIO_FIJO_BOLETO, (string) $base);
        Setting::set(Setting::PRECIO_FIJO_SEGURO_MENOR, (string) $insurance);
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

    private function oneWayTicket(int $price, bool $withChild = false): Ticket
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
        ]);
        $this->sale->recalculateTotal();

        return $ticket;
    }

    /**
     * @return array{Ticket, Ticket}
     */
    private function roundTrip(int $price, bool $withChild): array
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
        ]);
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
