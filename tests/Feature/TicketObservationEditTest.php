<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\PaymentMethod;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketEdit;
use App\Models\Trip;
use App\Models\User;
use App\Services\TicketRescheduleService;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Edición de la observación interna: cambia solo el boleto editado, queda en
 * el historial y la reprogramación la conserva.
 */
class TicketObservationEditTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Bus $bus;

    private Location $oran;

    private Location $salta;

    private Schedule $schedule;

    private PaymentMethod $method;

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

        Setting::flushCache();
        Permissions::syncToDatabase();

        $this->seller = User::factory()->create()->assignRole(Permissions::ROLE_SELLER);
        $this->seller->givePermissionTo('tickets.edit');

        $this->method = PaymentMethod::create(['label' => 'Efectivo', 'is_active' => true]);
        $this->oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta', 'is_active' => true]);
        $this->bus = Bus::create(['name' => 'Linea 1', 'plate' => 'AAA111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }
        $route = Route::create(['name' => 'Orán - Salta T', 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);
        $this->schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
    }

    public function test_cambiar_la_observacion_de_un_boleto_no_cambia_la_del_otro_boleto_de_la_venta(): void
    {
        [$first, $second] = $this->createSaleWithTwoTickets('Baja en Metán');

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $first->getKey()])
            ->assertSet('data.observation', 'Baja en Metán')
            ->set('data.observation', 'Baja en Rosario de la Frontera')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Baja en Rosario de la Frontera', $first->fresh()->observation);
        $this->assertSame('Baja en Metán', $second->fresh()->observation);
    }

    public function test_el_cambio_queda_en_el_historial_con_la_etiqueta_observacion(): void
    {
        [$first] = $this->createSaleWithTwoTickets('Baja en Metán');

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $first->getKey()])
            ->set('data.observation', 'Paga el saldo al subir')
            ->set('data.edit_reason', 'Lo pidió el pasajero')
            ->call('save');

        $edit = TicketEdit::where('ticket_id', $first->id)->sole();
        $this->assertSame('observation', $edit->field);
        $this->assertSame('Observación', $edit->field_label);
        $this->assertSame('Baja en Metán', $edit->old_value);
        $this->assertSame('Paga el saldo al subir', $edit->new_value);
        $this->assertSame('Lo pidió el pasajero', $edit->reason);
    }

    public function test_cargar_una_observacion_en_un_boleto_que_no_tenia(): void
    {
        [$first] = $this->createSaleWithTwoTickets(null);

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $first->getKey()])
            ->set('data.observation', '  Lleva silla de ruedas  ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Lleva silla de ruedas', $first->fresh()->observation);
        $this->assertNull(TicketEdit::where('ticket_id', $first->id)->sole()->old_value);
    }

    public function test_vaciar_la_observacion_la_deja_sin_observacion(): void
    {
        [$first] = $this->createSaleWithTwoTickets('Baja en Metán');

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $first->getKey()])
            ->set('data.observation', '   ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($first->fresh()->observation);
        $this->assertNull(TicketEdit::where('ticket_id', $first->id)->sole()->new_value);
    }

    public function test_editar_otro_campo_no_registra_cambio_de_observacion(): void
    {
        [$first] = $this->createSaleWithTwoTickets('Baja en Metán');

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $first->getKey()])
            ->set('data.phone_number', '3875559999')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, TicketEdit::where('ticket_id', $first->id)->where('field', 'observation')->count());
        $this->assertSame('Baja en Metán', $first->fresh()->observation);
    }

    public function test_observacion_de_281_caracteres_se_rechaza(): void
    {
        [$first] = $this->createSaleWithTwoTickets('Baja en Metán');

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $first->getKey()])
            ->set('data.observation', str_repeat('a', 281))
            ->call('save');

        $this->assertSame('Baja en Metán', $first->fresh()->observation);
        $this->assertSame(0, TicketEdit::where('ticket_id', $first->id)->count());
    }

    public function test_asignar_fecha_a_un_boleto_pendiente_conserva_la_observacion(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 09:00:00'));

        $passenger = Passenger::create(['first_name' => 'Juan', 'last_name' => 'Pérez', 'dni' => '12345678', 'passenger_type' => 'adult']);
        $sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);
        $pending = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => null,
            'seat_id' => null,
            'bus_id' => $this->bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'price' => 10000,
            'payment_method' => $this->method->code,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'observation' => 'Lleva silla de ruedas',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        app(TicketRescheduleService::class)->reschedule($pending, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $this->schedule->id,
            'seat_id' => Seat::where('bus_id', $this->bus->id)->where('seat_number', '2')->first()->id,
        ]);

        $pending->refresh();
        $this->assertNotNull($pending->trip_id);
        $this->assertSame('Lleva silla de ruedas', $pending->observation);

        $row = $pending->trip->getPassengersWithDetails()->firstWhere('ticket_id', $pending->id);
        $this->assertSame('Lleva silla de ruedas', $row['observation']);
    }

    public function test_con_el_parametro_apagado_la_edicion_conserva_la_observacion_sin_historial(): void
    {
        [$first] = $this->createSaleWithTwoTickets('Baja en Metán');
        Setting::set(Setting::OBSERVACIONES_VENTA, '0');

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $first->getKey()])
            ->assertFormFieldHidden('observation')
            ->set('data.observation', 'Forzada')
            ->set('data.phone_number', '3875559999')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Baja en Metán', $first->fresh()->observation);
        $this->assertSame(0, TicketEdit::where('ticket_id', $first->id)->where('field', 'observation')->count());
        $this->assertSame(1, TicketEdit::where('ticket_id', $first->id)->where('field', 'phone_number')->count());
    }

    public function test_con_el_parametro_apagado_solo_cambiar_la_observacion_no_guarda_nada(): void
    {
        [$first] = $this->createSaleWithTwoTickets('Baja en Metán');
        Setting::set(Setting::OBSERVACIONES_VENTA, '0');

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $first->getKey()])
            ->set('data.observation', 'Forzada')
            ->call('save')
            ->assertNotified('No hay cambios para guardar');

        $this->assertSame('Baja en Metán', $first->fresh()->observation);
        $this->assertSame(0, TicketEdit::where('ticket_id', $first->id)->count());
    }

    /**
     * Venta de dos pasajeros en el mismo viaje, con la misma observación.
     *
     * @return array{0: Ticket, 1: Ticket}
     */
    private function createSaleWithTwoTickets(?string $observation): array
    {
        $trip = Trip::findOrCreateForBooking($this->schedule->id, '2030-01-01', $this->oran->id, $this->salta->id)['trip'];
        $sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);

        $tickets = [];
        foreach ([['Pedro', '30000111', '1'], ['Ana', '30000222', '2']] as [$name, $dni, $seat]) {
            $passenger = Passenger::create([
                'first_name' => $name,
                'last_name' => 'Gomez',
                'dni' => $dni,
                'phone_number' => '3875550001',
                'passenger_type' => 'adult',
            ]);

            $tickets[] = Ticket::create([
                'sale_id' => $sale->id,
                'trip_id' => $trip->id,
                'passenger_id' => $passenger->id,
                'seat_id' => Seat::where('bus_id', $this->bus->id)->where('seat_number', $seat)->first()->id,
                'price' => 10000,
                'payment_method' => $this->method->code,
                'origin_location_id' => $this->oran->id,
                'destination_location_id' => $this->salta->id,
                'observation' => $observation,
            ]);
        }

        return $tickets;
    }
}
