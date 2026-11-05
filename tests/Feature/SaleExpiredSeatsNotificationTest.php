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
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Al presionar "Finalizar" con los asientos liberados (reserva vencida), el
 * vendedor ve una notificación que explica el problema y cómo volver al paso
 * de asientos, en lugar de que el botón no haga nada visible.
 */
class SaleExpiredSeatsNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Location $oran;

    protected Location $salta;

    protected Bus $bus;

    protected Schedule $schedule;

    protected string $date = '2027-01-07';

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');
        Setting::flushCache();

        $this->oran = Location::create(['name' => 'Orán E', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta E', 'is_active' => true]);
        $this->bus = Bus::create(['name' => 'Linea E', 'plate' => 'EEE111', 'seat_count' => 4, 'floors' => 1]);

        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta E', 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);

        $this->schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Horario E',
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

    public function test_finalizar_con_los_asientos_liberados_avisa_como_volver_a_elegirlos(): void
    {
        $page = $this->mountSummaryStep();

        // La reserva vence: el selector de asientos descarta la selección.
        SeatReservation::query()->delete();
        $page->call('$refresh');

        $page->call('create');

        $notification = $this->sentNotification('Faltan los asientos de ida');

        $this->assertSame(
            'La reserva de los asientos venció y se liberaron. Presioná «Anterior» hasta el paso «Asientos (Ida)», elegí los asientos de nuevo y volvé a finalizar la venta.',
            $notification['body'],
        );
        $this->assertSame([], $notification['actions'], 'La notificación solo explica qué hacer.');
        $this->assertSame(0, Sale::count(), 'No se emite nada.');
    }

    public function test_si_falta_solo_la_vuelta_el_aviso_nombra_el_paso_de_vuelta(): void
    {
        $this->invokeValidationError(['data.return_seat_ids' => ['validation.required']]);

        $notification = $this->sentNotification('Faltan los asientos de vuelta');
        $this->assertStringContainsString('«Asientos (Vuelta)»', $notification['body']);
    }

    public function test_si_faltan_ambos_tramos_el_aviso_empieza_por_la_ida(): void
    {
        $this->invokeValidationError([
            'data.seat_ids' => ['validation.required'],
            'data.return_seat_ids' => ['validation.required'],
        ]);

        $notification = $this->sentNotification('Faltan los asientos');
        $this->assertStringContainsString('«Asientos (Ida)»', $notification['body']);
    }

    public function test_otros_errores_de_validacion_no_muestran_el_aviso(): void
    {
        $this->invokeValidationError(['data.passengers.0.dni' => ['validation.required']]);

        $this->assertSame([], session('filament.notifications', []));
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    protected function invokeValidationError(array $errors): void
    {
        $method = new \ReflectionMethod(CreateTicket::class, 'onValidationError');
        $method->invoke(app(CreateTicket::class), \Illuminate\Validation\ValidationException::withMessages($errors));
    }

    public function test_finalizar_con_los_asientos_reservados_no_muestra_el_aviso(): void
    {
        $page = $this->mountSummaryStep();

        $page->call('create');

        $this->assertNull($this->sentNotification('Faltan los asientos de ida', required: false));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function sentNotification(string $title, bool $required = true): ?array
    {
        $notification = collect(session('filament.notifications', []))
            ->first(fn (array $notification): bool => ($notification['title'] ?? null) === $title);

        if ($required) {
            $this->assertNotNull($notification, "No se envió la notificación \"{$title}\".");
        }

        return $notification;
    }

    /** Avanza el asistente hasta "Resumen" con 1 pasajero y su asiento reservado. */
    protected function mountSummaryStep(): Testable
    {
        $seat = Seat::where('bus_id', $this->bus->id)->orderBy('seat_number')->first();

        $page = Livewire::test(CreateTicket::class)->fillForm([
            'bus_id' => (string) $this->bus->id,
            'origin_location_id' => (string) $this->oran->id,
            'destination_location_id' => (string) $this->salta->id,
            'departure_date' => $this->date,
            'schedule_id' => (string) $this->schedule->id,
            'passengers_count' => '1',
        ]);

        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 0]);

        SeatReservation::create([
            'trip_id' => $page->get('data.trip_id'),
            'seat_id' => $seat->id,
            'user_session_id' => session()->getId(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $page->set('data.seat_ids', [$seat->id]);
        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 1]);
        $page->set('data.passengers', array_values($page->get('data.passengers') ?? []));

        $page->set('data.passengers.0.dni', '30123456')
            ->set('data.passengers.0.last_name', 'Perez')
            ->set('data.passengers.0.first_name', 'Ana')
            ->set('data.passengers.0.price', 15000)
            ->set('data.passengers.0.payment_method', 'efectivo');

        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 2]);

        return $page;
    }
}
