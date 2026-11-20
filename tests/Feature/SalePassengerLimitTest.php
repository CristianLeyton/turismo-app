<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Models\Bus;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Límite de pasajeros por venta (Parámetros > Venta): el selector "Cantidad
 * de pasajeros" y la validación de la emisión usan el valor configurado.
 */
class SalePassengerLimitTest extends TestCase
{
    use RefreshDatabase;

    private Location $oran;

    private Location $salta;

    private Bus $bus;

    private Schedule $schedule;

    private string $date = '2027-01-07';

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');
        Setting::flushCache();

        $this->oran = Location::create(['name' => 'Orán L', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta L', 'is_active' => true]);
        $this->bus = Bus::create(['name' => 'Linea L', 'plate' => 'LLL111', 'seat_count' => 8, 'floors' => 1]);

        foreach (range(1, 8) as $number) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $number, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta L', 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);

        $this->schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Horario L',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        Trip::findOrCreateForBooking($this->schedule->id, $this->date, $this->oran->id, $this->salta->id);

        $this->actingAs(User::factory()->vendedor()->create());
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_sin_configurar_el_selector_ofrece_de_1_a_4(): void
    {
        $this->assertPassengerCountOptions(Livewire::test(CreateTicket::class), ['1', '2', '3', '4']);
    }

    public function test_con_el_limite_en_6_el_selector_ofrece_de_1_a_6(): void
    {
        Setting::set(Setting::LIMITE_PASAJEROS_VENTA, '6');

        $this->assertPassengerCountOptions(Livewire::test(CreateTicket::class), ['1', '2', '3', '4', '5', '6']);
    }

    public function test_sin_configurar_una_cantidad_de_5_se_rechaza(): void
    {
        $page = $this->fillFirstStep(5);

        $this->nextStep($page, 0);

        $page->assertHasErrors(['data.passengers_count' => 'La cantidad de pasajeros debe ser entre 1 y 4']);
    }

    public function test_con_el_limite_en_6_se_emite_una_venta_de_6_pasajeros(): void
    {
        Setting::set(Setting::LIMITE_PASAJEROS_VENTA, '6');

        $page = $this->mountSummaryStep(6);
        $page->call('create');

        $page->assertHasNoErrors();
        $this->assertSame(1, Sale::count());
        $this->assertSame(6, Ticket::count());
    }

    public function test_con_el_limite_en_2_una_venta_de_3_se_rechaza_sin_crear_boletos(): void
    {
        Setting::set(Setting::LIMITE_PASAJEROS_VENTA, '2');

        $page = $this->fillFirstStep(3);
        $page->call('create');

        $page->assertHasErrors(['data.passengers_count' => 'La cantidad de pasajeros debe ser entre 1 y 2']);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Ticket::count());
    }

    public function test_si_el_limite_baja_con_la_venta_armada_la_emision_se_rechaza(): void
    {
        $page = $this->mountSummaryStep(4);

        Setting::set(Setting::LIMITE_PASAJEROS_VENTA, '3');
        $page->call('create');

        $page->assertHasErrors(['data.passengers_count' => 'La cantidad de pasajeros debe ser entre 1 y 3']);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Ticket::count());
    }

    // ====================== Helpers ======================

    /**
     * @param  list<string>  $expected
     */
    private function assertPassengerCountOptions(Testable $page, array $expected): void
    {
        $page->assertFormFieldExists(
            'passengers_count',
            checkFieldUsing: fn (Select $field): bool => array_map('strval', array_keys($field->getOptions())) === $expected,
        );
    }

    private function fillFirstStep(int $passengers): Testable
    {
        return Livewire::test(CreateTicket::class)->fillForm([
            'bus_id' => (string) $this->bus->id,
            'origin_location_id' => (string) $this->oran->id,
            'destination_location_id' => (string) $this->salta->id,
            'departure_date' => $this->date,
            'schedule_id' => (string) $this->schedule->id,
            'passengers_count' => (string) $passengers,
        ]);
    }

    private function nextStep(Testable $page, int $currentStepIndex): void
    {
        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => $currentStepIndex]);
    }

    /** Avanza el asistente hasta "Resumen" con los pasajeros y asientos reservados. */
    private function mountSummaryStep(int $passengers): Testable
    {
        $seats = Seat::where('bus_id', $this->bus->id)->orderBy('id')->take($passengers)->get();

        $page = $this->fillFirstStep($passengers);
        $this->nextStep($page, 0);

        foreach ($seats as $seat) {
            SeatReservation::create([
                'trip_id' => $page->get('data.trip_id'),
                'seat_id' => $seat->id,
                'user_session_id' => session()->getId(),
                'expires_at' => now()->addMinutes(5),
            ]);
        }

        $page->set('data.seat_ids', $seats->pluck('id')->all());
        $this->nextStep($page, 1);
        $page->set('data.passengers', array_values($page->get('data.passengers') ?? []));

        $firstNames = ['Ana', 'Beto', 'Carla', 'Diego', 'Eva', 'Fede', 'Gabi', 'Hugo'];

        foreach (range(0, $passengers - 1) as $index) {
            $page->set("data.passengers.{$index}.dni", (string) (30123450 + $index))
                ->set("data.passengers.{$index}.last_name", 'Perez')
                ->set("data.passengers.{$index}.first_name", $firstNames[$index])
                ->set("data.passengers.{$index}.price", 15000)
                ->set("data.passengers.{$index}.payment_method", 'efectivo');
        }

        $this->nextStep($page, 2);

        return $page;
    }
}
