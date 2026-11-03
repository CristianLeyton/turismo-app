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
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Parámetro "Venta con acompañante" (Parámetros del sistema) aplicado al
 * formulario de edición de boletos:
 *
 * - Apagado: la sección "Acompañante del pasajero" no se muestra.
 * - Encendido: la sección se muestra (comportamiento original).
 * - Apagado: al guardar otros campos NO se pierde el menor ni los datos de
 *   mascota del boleto (el estado de los switches ocultos se conserva en el
 *   payload vía `dehydratedWhenHidden()`).
 */
class CompanionParamEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;

    protected Bus $bus;

    protected Location $oran;

    protected Location $salta;

    protected Schedule $schedule;

    protected PaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        // La caché de Setting es estática dentro del proceso de PHPUnit: sin
        // esto, una prueba que cambia el parámetro la "hereda" a las
        // siguientes aunque la base se refresque.
        Setting::flushCache();

        Permissions::syncToDatabase();

        $this->seller = User::factory()->create([
            'password' => Hash::make('secreto123'),
        ])->assignRole(Permissions::ROLE_SELLER);
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

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    /** Boleto con menor acompañante y mascota ya cargados. */
    protected function createTicketWithCompanions(): Ticket
    {
        $trip = Trip::findOrCreateForBooking($this->schedule->id, '2030-01-01', $this->oran->id, $this->salta->id)['trip'];
        $passenger = Passenger::create([
            'first_name' => 'Pedro',
            'last_name' => 'Gomez',
            'dni' => '30000111',
            'phone_number' => '3875550001',
            'passenger_type' => 'adult',
        ]);
        $sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);

        $ticket = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'passenger_id' => $passenger->id,
            'seat_id' => Seat::where('bus_id', $this->bus->id)->where('seat_number', '1')->first()->id,
            'price' => 10000,
            'payment_method' => $this->method->code,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
        ]);

        $passenger->children()->create([
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'dni' => '55555555',
            'passenger_type' => 'child',
        ]);
        $ticket->update([
            'travels_with_child' => true,
            'travels_with_pets' => true,
            'pet_names' => 'Firu',
            'pet_count' => 1,
        ]);

        return $ticket;
    }

    public function test_con_parametro_apagado_la_seccion_de_acompanante_no_se_muestra(): void
    {
        Setting::set(Setting::PERMITIR_ACOMPANANTES, '0');
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicketWithCompanions();

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->assertDontSee('Viaja con un menor')
            ->assertDontSee('Viaja con mascota');
    }

    public function test_con_parametro_encendido_la_seccion_se_muestra(): void
    {
        Setting::set(Setting::PERMITIR_ACOMPANANTES, '1');
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicketWithCompanions();

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->assertSee('Viaja con un menor')
            ->assertSee('Viaja con mascota');
    }

    public function test_apagar_el_parametro_no_destruye_menor_ni_mascota_al_guardar_otro_campo(): void
    {
        Setting::set(Setting::PERMITIR_ACOMPANANTES, '0');
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicketWithCompanions();
        $childId = $ticket->passenger->children()->first()->id;

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.price', 20000)
            ->call('save')
            ->assertHasNoErrors();

        $ticket->refresh();

        $this->assertSame(20000.0, (float) $ticket->price);
        $this->assertTrue((bool) $ticket->travels_with_child, 'El flag de menor debe conservarse');
        $this->assertTrue((bool) $ticket->travels_with_pets, 'El flag de mascota debe conservarse');
        $this->assertSame('Firu', $ticket->pet_names);
        $this->assertSame(1, (int) $ticket->pet_count);
        $this->assertDatabaseHas('passengers', ['id' => $childId]);

        $this->assertSame(
            0,
            TicketEdit::where('ticket_id', $ticket->id)->whereIn('field', ['travels_with_child', 'travels_with_pets'])->count(),
            'No debe auditarse cambio de acompañante cuando la sección estaba oculta',
        );
    }
}
