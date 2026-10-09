<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use App\Support\SaleCutoff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bloqueo de la venta fuera de término en el PASO 1 del wizard de venta.
 *
 * Sin el permiso `tickets.vender_pasado_limite`:
 *  - no se puede pasar del paso 1 si el horario elegido salió hace más de las
 *    horas configuradas (o es de una fecha anterior a hoy),
 *  - la Fecha de ida no ofrece fechas anteriores a hoy (minDate),
 *  - los horarios vencidos no se listan en el select.
 */
class TicketSaleCutoffWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');

        Setting::flushCache();
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    // ====================== Reglas del soporte ======================

    public function test_la_fecha_anterior_a_hoy_genera_un_motivo_de_bloqueo(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $schedule = $this->makeSchedule();

        $reason = SaleCutoff::blockReasonForSchedule('2026-09-30', $schedule, $this->oran->id);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('anterior a hoy', $reason);
    }

    public function test_la_salida_fuera_de_la_ventana_de_horas_genera_un_motivo_de_bloqueo(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $schedule = $this->makeSchedule('08:00');

        // 08:00 + 5 horas = 13:00 vence; a las 18:00 ya está fuera.
        $this->travelTo(Carbon::parse('2026-10-01 18:00:00'));

        $reason = SaleCutoff::blockReasonForSchedule(Carbon::parse('2026-10-01'), $schedule, $this->oran->id);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('fuera del límite', $reason);
    }

    public function test_un_horario_valido_no_genera_motivo(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $schedule = $this->makeSchedule('14:00');

        // Salida 14:00 hoy; dentro de las 5 horas.
        $this->travelTo(Carbon::parse('2026-10-01 16:00:00'));

        $this->assertNull(SaleCutoff::blockReasonForSchedule(Carbon::parse('2026-10-01'), $schedule, $this->oran->id));
    }

    public function test_con_el_permiso_el_horario_vencido_no_genera_motivo(): void
    {
        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_pasado_limite');
        $this->actingAs($seller);

        $schedule = $this->makeSchedule('08:00');

        // Fecha anterior Y horas vencidas: ambos aceptados con el permiso.
        $this->travelTo(Carbon::parse('2026-10-03 20:00:00'));

        $this->assertNull(SaleCutoff::blockReasonForSchedule(Carbon::parse('2026-09-30'), $schedule, $this->oran->id));
    }

    public function test_con_horas_en_cero_no_hay_bloqueo(): void
    {
        Setting::set(Setting::VENTA_LIMITE_HORAS, '0');
        $this->actingAs(User::factory()->vendedor()->create());

        $schedule = $this->makeSchedule('08:00');

        $this->travelTo(Carbon::parse('2026-10-03 20:00:00'));

        $this->assertNull(SaleCutoff::blockReasonForSchedule(Carbon::parse('2026-09-30'), $schedule, $this->oran->id));
    }

    public function test_min_selectable_date_sin_permiso_es_hoy(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 09:00:00'));
        $this->actingAs(User::factory()->vendedor()->create());

        $this->assertSame('2026-10-01', SaleCutoff::minSelectableDate()->format('Y-m-d'));
    }

    public function test_min_selectable_date_con_permiso_es_un_ano_atras(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 09:00:00'));

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_pasado_limite');
        $this->actingAs($seller);

        $this->assertSame('2025-10-01', SaleCutoff::minSelectableDate()->format('Y-m-d'));
    }

    // ====================== Wizard: avance del paso 1 ======================

    public function test_el_paso_1_no_avanza_con_un_horario_de_fecha_anterior(): void
    {
        $f = $this->makeFixture();

        $this->actingAs(User::factory()->vendedor()->create());

        // "Hoy" es el 02/10; la fecha del fixture es del 01/10 (anterior).
        $this->travelTo(Carbon::parse('2026-10-02 09:00:00'));

        $page = $this->mountWizard($f);

        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 0]);

        // Sin el dispatch el wizard se queda en el paso 1.
        $page->assertNotDispatched('next-wizard-step');

        // Defensa adicional: nada se vendió aunque se forzara el submit.
        $this->assertSame(0, Sale::count());
    }

    public function test_el_paso_1_avanza_con_un_horario_valido(): void
    {
        $f = $this->makeFixture();

        $this->actingAs(User::factory()->vendedor()->create());

        // La salida (08:00 del 01/10) vence 13:00; con "ahora" dentro de la ventana.
        $this->travelTo(Carbon::parse('2026-10-01 09:00:00'));

        $page = $this->mountWizard($f);

        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 0]);

        $page->assertDispatched('next-wizard-step');
    }

    public function test_el_paso_1_avanza_con_el_permiso_aunque_el_horario_vencio(): void
    {
        $f = $this->makeFixture();

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_pasado_limite');
        $this->actingAs($seller);

        // 08:00 + 5 h = 13:00 vence; "ahora" ya pasó.
        $this->travelTo(Carbon::parse('2026-10-01 18:00:00'));

        $page = $this->mountWizard($f);

        $page->call('callSchemaComponentMethod', 'form.data::wizard', 'nextStep', ['currentStepIndex' => 0]);

        $page->assertDispatched('next-wizard-step');
    }

    // ====================== Helpers ======================

    /**
     * Monta el wizard con el paso 1 completo (colectivo, origen, destino,
     * fecha y horario del fixture) sin pasar todavía del paso.
     */
    private function mountWizard(array $f): Testable
    {
        return Livewire::test(\App\Filament\Resources\Tickets\Pages\CreateTicket::class)
            ->fillForm([
                'bus_id' => (string) $f['bus']->id,
                'origin_location_id' => (string) $f['origin']->id,
                'destination_location_id' => (string) $f['destination']->id,
                'departure_date' => '2026-10-01',
                'schedule_id' => (string) $f['schedule']->id,
                'passengers_count' => '1',
            ]);
    }

    private function makeSchedule(string $departureTime = '08:00'): Schedule
    {
        if (! isset($this->oran)) {
            $this->oran = Location::create(['name' => 'Orán W', 'is_active' => true]);
            $this->salta = Location::create(['name' => 'Salta W', 'is_active' => true]);
            $this->bus = Bus::create(['name' => 'Linea W', 'plate' => 'WWW111', 'seat_count' => 4, 'floors' => 1]);
            foreach ([1, 2, 3, 4] as $n) {
                Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
            }
            $this->route = Route::create(['name' => 'Orán - Salta W', 'bus_id' => $this->bus->id, 'is_active' => true]);
            RouteStop::create(['route_id' => $this->route->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
            RouteStop::create(['route_id' => $this->route->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);
        }

        return Schedule::create([
            'route_id' => $this->route->id,
            'name' => 'Horario '.$departureTime,
            'departure_time' => $departureTime,
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
    }

    /**
     * Fixture de venta de ida: ruta Orán→Salta con horario 08:00 y viaje el
     * 2026-10-01 (creado de antemano para que `afterValidation` sólo lo lea).
     *
     * @return array{origin: Location, destination: Location, bus: Bus, schedule: Schedule, trip: Trip}
     */
    private function makeFixture(): array
    {
        $schedule = $this->makeSchedule();

        $trip = Trip::findOrCreateForBooking($schedule->id, '2026-10-01', $this->oran->id, $this->salta->id)['trip'];
        $this->assertNotNull($trip);

        return [
            'origin' => $this->oran,
            'destination' => $this->salta,
            'bus' => $this->bus,
            'schedule' => $schedule,
            'trip' => $trip,
        ];
    }
}
