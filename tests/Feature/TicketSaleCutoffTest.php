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
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Límite de venta para viajes que YA SALIERON.
 *
 * Regla: pasadas N horas desde la salida del colectivo en la parada donde sube
 * el pasajero (N se configura en Configuración, default 5), no se puede crear
 * el pasaje salvo con el permiso `tickets.vender_pasado_limite`, que ningún rol
 * trae por defecto.
 */
class TicketSaleCutoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');

        Setting::flushCache();

        // Reloj base: un rato antes de la salida del viaje del fixture.
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 07:00:00'));
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    // ====================== Catálogo y default ======================

    public function test_el_permiso_esta_en_el_catalogo_y_ningun_rol_del_sistema_lo_trae(): void
    {
        $this->assertContains('tickets.vender_pasado_limite', \App\Support\Permissions::all());

        \App\Support\Permissions::syncToDatabase();

        foreach ([\App\Support\Permissions::ROLE_ADMIN, \App\Support\Permissions::ROLE_SELLER] as $roleName) {
            $this->assertFalse(
                Role::findByName($roleName)->hasPermissionTo('tickets.vender_pasado_limite'),
                "El rol {$roleName} no debe traer el permiso por defecto.",
            );
        }

        // SUPER lo trae por bypass total del catálogo (por diseño).
        $this->assertTrue(
            Role::findByName(\App\Support\Permissions::ROLE_SUPER)->hasPermissionTo('tickets.vender_pasado_limite'),
        );
    }

    public function test_el_default_de_horas_es_cinco(): void
    {
        // Sin la clave en la tabla, el valor por defecto es 5.
        $this->assertSame(5, Setting::ventaLimiteHoras());
    }

    // ====================== Bloqueo ======================

    public function test_no_se_puede_vender_pasadas_las_horas_limite_sin_permiso(): void
    {
        $f = $this->makeFixture();
        $seller = User::factory()->vendedor()->create();
        $this->actingAs($seller);
        $this->assertFalse($seller->can('tickets.vender_pasado_limite'));

        // Salida 08:00 → el límite de 5 horas vence 13:00.
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 13:00:01'));

        try {
            $this->invokeHandleRecordCreation($this->salePayload($f));
            $this->fail('La venta pasadas las 5 horas debió ser rechazada.');
        } catch (\Filament\Support\Exceptions\Halt $e) {
            // OK: el guard hace $this->halt().
        }

        $this->assertSame(0, Sale::count(), 'La venta no debió crearse.');
        $this->assertSame(0, Ticket::count(), 'Ningún boleto debió emitirse.');
    }

    public function test_se_puede_vender_dentro_de_las_horas_limite(): void
    {
        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        // 4 horas y 59 minutos después de la salida: todavía dentro del plazo.
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 12:59:00'));

        $ticket = $this->invokeHandleRecordCreation($this->salePayload($f));

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertSame($f['trip']->id, $ticket->trip_id);
        $this->assertSame(1, Sale::count());
    }

    public function test_con_el_permiso_se_puede_vender_pasado_el_limite(): void
    {
        $f = $this->makeFixture();
        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_pasado_limite');
        $this->actingAs($seller);

        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 20:00:00'));

        $ticket = $this->invokeHandleRecordCreation($this->salePayload($f));

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertSame($f['trip']->id, $ticket->trip_id);
        $this->assertSame(1, Sale::count());
    }

    // ====================== Horas configurables ======================

    public function test_las_horas_son_configurables(): void
    {
        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        // Con 2 horas, una venta 3 horas después de la salida ya está fuera.
        Setting::set(Setting::VENTA_LIMITE_HORAS, '2');
        $this->assertSame(2, Setting::ventaLimiteHoras());

        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 11:00:00')); // 3h después

        try {
            $this->invokeHandleRecordCreation($this->salePayload($f));
            $this->fail('Con 2 horas configuradas, la venta a las 3 horas debió rechazarse.');
        } catch (\Filament\Support\Exceptions\Halt $e) {
            // OK.
        }

        $this->assertSame(0, Sale::count());

        // Con 10 horas, esa misma venta entra.
        Setting::set(Setting::VENTA_LIMITE_HORAS, '10');
        $this->assertSame(10, Setting::ventaLimiteHoras());

        $ticket = $this->invokeHandleRecordCreation($this->salePayload($f));

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertSame(1, Sale::count());
    }

    public function test_cero_horas_desactiva_el_limite(): void
    {
        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        Setting::set(Setting::VENTA_LIMITE_HORAS, '0');
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 23:59:00'));

        $ticket = $this->invokeHandleRecordCreation($this->salePayload($f));

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertSame(1, Sale::count());
    }

    // ====================== El reloj corre desde la parada de subida ======================

    public function test_el_limite_cuenta_desde_la_salida_en_la_parada_de_subida(): void
    {
        // El horario sale 08:00 y la parada de subida tiene +120 min de offset:
        // el pasajero sube a las 10:00, así que el plazo vence a las 15:00.
        $f = $this->makeFixture(boardingOffsetMinutes: 120);
        $this->actingAs(User::factory()->vendedor()->create());

        // 14:00 = 6h después de las 08:00, pero sólo 4h después de la subida.
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 14:00:00'));

        $ticket = $this->invokeHandleRecordCreation($this->salePayload($f));

        $this->assertInstanceOf(Ticket::class, $ticket);

        // 15:00:01 ya está fuera del plazo de 5 horas desde la subida.
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 15:00:01'));

        try {
            $this->invokeHandleRecordCreation($this->salePayload($f));
            $this->fail('Pasadas las 5 horas desde la subida debió rechazarse.');
        } catch (\Filament\Support\Exceptions\Halt $e) {
            // OK.
        }

        $this->assertSame(1, Sale::count(), 'Sólo la primera venta debió crearse.');
    }

    // ====================== Helpers ======================

    /**
     * Invoca CreateTicket::handleRecordCreation (protected) simulando el submit
     * del wizard, sin montar todo el formulario Livewire.
     */
    private function invokeHandleRecordCreation(array $data): Ticket
    {
        $page = app(CreateTicket::class);

        $method = new \ReflectionMethod(CreateTicket::class, 'handleRecordCreation');
        $method->setAccessible(true);

        return $method->invoke($page, $data);
    }

    /**
     * Fixture mínimo de una venta de ida con fecha: una ruta Orán→Salta con un
     * horario que sale 08:00 y un viaje el 2026-10-01.
     *
     * @return array{origin: Location, destination: Location, bus: Bus, schedule: Schedule, trip: Trip, seat: Seat}
     */
    private function makeFixture(int $boardingOffsetMinutes = 0): array
    {
        $oran = Location::create(['name' => 'Orán C', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta C', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea C', 'plate' => 'CCC111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta C', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create([
            'route_id' => $route->id,
            'location_id' => $oran->id,
            'stop_order' => 1,
            'departure_offset_minutes' => $boardingOffsetMinutes,
        ]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana C',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::findOrCreateForBooking($schedule->id, '2026-10-01', $oran->id, $salta->id)['trip'];
        $this->assertNotNull($trip);

        return [
            'origin' => $oran,
            'destination' => $salta,
            'bus' => $bus,
            'schedule' => $schedule,
            'trip' => $trip,
            'seat' => Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first(),
        ];
    }

    /**
     * Payload del wizard para una venta normal (con fecha) de 1 adulto.
     */
    private function salePayload(array $fixture): array
    {
        return [
            'bus_id' => $fixture['bus']->id,
            'sell_without_date_ida' => false,
            'sell_without_date_vuelta' => false,
            'passengers_count' => '1',
            'passengers' => [
                [
                    'first_name' => 'Juan',
                    'last_name' => 'Pérez',
                    'dni' => '12345678',
                    'phone_number' => '3881234567',
                    'email' => null,
                    'travels_with_child' => false,
                    'travels_with_pets' => false,
                    'price' => 15000,
                    'payment_method' => 'efectivo',
                ],
            ],
            'origin_location_id' => $fixture['origin']->id,
            'destination_location_id' => $fixture['destination']->id,
            'is_round_trip' => false,
            'trip_id' => $fixture['trip']->id,
            'seat_ids' => [$fixture['seat']->id],
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ];
    }
}
