<?php

namespace Tests\Feature;

use App\Filament\Pages\TicketSettings;
use App\Filament\Resources\Tickets\Pages\RescheduleTicket;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
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
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TicketSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-settings@test.local',
            'username' => 'admin-settings',
            'password' => bcrypt('secreto123'),
            'is_admin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    // ====================== Página Configuración ======================

    public function test_la_pagina_configuracion_renderiza_para_admin(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->assertOk()
            ->assertSee('Confirmación de contraseña')
            ->assertSee('Pedir contraseña al borrar boletos');

        $response = $this->get(TicketSettings::getUrl());

        $response->assertOk();
        $response->assertSee('Configuración');
    }

    public function test_no_admin_no_puede_acceder_a_configuracion(): void
    {
        $vendedor = User::create([
            'name' => 'Vendedor',
            'email' => 'vendedor-settings@test.local',
            'username' => 'vendedor-settings',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $this->actingAs($vendedor);

        $this->assertFalse(TicketSettings::canAccess());
        $this->assertFalse(TicketSettings::shouldRegisterNavigation());

        $response = $this->get(TicketSettings::getUrl());

        $response->assertForbidden();
    }

    public function test_guardar_configuracion_persiste_los_toggles(): void
    {
        $this->actingAs($this->admin);

        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE));

        Livewire::test(TicketSettings::class)
            ->set('data.require_password_ticket_delete', true)
            ->set('data.require_password_ticket_reschedule', true)
            ->call('save')
            ->assertOk();

        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE));

        $this->assertDatabaseHas('settings', [
            'key' => Setting::REQUIRE_PASSWORD_TICKET_DELETE,
            'value' => '1',
        ]);

        // Apagarlos de vuelta.
        Livewire::test(TicketSettings::class)
            ->set('data.require_password_ticket_delete', false)
            ->call('save');

        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
    }

    // ====================== Setting API ======================

    public function test_setting_get_bool_defaults_y_set(): void
    {
        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE, false));

        $this->assertTrue(Setting::getBool('clave.inexistente', true));

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');
        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '0');
        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));

        // set() sobrescribe en lugar de duplicar.
        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');
        $this->assertSame(1, Setting::query()->where('key', Setting::REQUIRE_PASSWORD_TICKET_DELETE)->count());
    }

    // ====================== Borrar boleto ======================

    private function createOneWayTicket(string $date = '2026-10-01', array $userOverrides = []): Ticket
    {
        $oran = Location::firstOrCreate(['name' => 'Orán S'], ['is_active' => true]);
        $salta = Location::firstOrCreate(['name' => 'Salta S'], ['is_active' => true]);

        $bus = Bus::firstOrCreate(
            ['plate' => 'SSS111'],
            ['name' => 'Linea S', 'seat_count' => 4, 'floors' => 1],
        );
        foreach ([1, 2, 3, 4] as $n) {
            Seat::firstOrCreate(
                ['bus_id' => $bus->id, 'seat_number' => (string) $n],
                ['is_active' => true, 'floor' => '1'],
            );
        }

        $route = Route::firstOrCreate(
            ['name' => 'Orán - Salta S'],
            ['bus_id' => $bus->id, 'is_active' => true],
        );
        RouteStop::firstOrCreate(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::firstOrCreate(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::firstOrCreate(
            ['route_id' => $route->id, 'name' => 'Mañana S'],
            ['departure_time' => '08:00', 'arrival_time' => '12:00', 'is_active' => true],
        );

        $trip = Trip::firstOrCreate(
            ['route_id' => $route->id, 'schedule_id' => $schedule->id, 'trip_date' => $date],
            ['bus_id' => $bus->id],
        );

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'sale_date' => now(),
            'total_amount' => 100,
        ]);

        $passenger = Passenger::firstOrCreate(
            ['dni' => '77766655'],
            ['first_name' => 'Juana', 'last_name' => 'Sosa', 'passenger_type' => 'adult'],
        );

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first()->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
        ]);
    }

    public function test_borrar_boleto_sin_setting_no_pide_password(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket();

        $this->get(TicketResource::getUrl('view', ['record' => $ticket->id]))
            ->assertOk();

        $ticket->delete();

        $this->assertSoftDeleted($ticket);
    }

    public function test_borrar_boleto_con_password_incorrecta_no_elimina(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');

        $ticket = $this->createOneWayTicket();

        Livewire::test(\App\Filament\Resources\Tickets\Pages\ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', ['password' => 'clave-equivocada'])
            ->assertHasActionErrors(['password']);

        // El boleto sigue vivo.
        $this->assertNotSoftDeleted($ticket);

        // Y también sin password.
        Livewire::test(\App\Filament\Resources\Tickets\Pages\ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', [])
            ->assertHasActionErrors(['password']);

        $this->assertNotSoftDeleted($ticket);
    }

    public function test_borrar_boleto_con_password_correcta_permite_eliminar(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');

        $ticket = $this->createOneWayTicket();

        Livewire::test(\App\Filament\Resources\Tickets\Pages\ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', ['password' => 'secreto123'])
            ->assertHasNoActionErrors();

        $this->assertSoftDeleted($ticket->fresh());
        $this->assertSame($this->admin->id, $ticket->fresh()->deleted_by);
    }

    // ====================== Reprogramar boleto ======================

    public function test_reprogramar_con_setting_activo_exige_password(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE, '1');

        $ticket = $this->createOneWayTicket('2026-10-01');
        $originalTripId = $ticket->trip_id;

        // Via el servicio directamente (defensa server-side).
        $service = app(\App\Services\TicketRescheduleService::class);

        try {
            $service->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $ticket->trip->schedule_id,
                'seat_id' => $ticket->seat_id,
                'confirm_password' => 'clave-mala',
            ]);
            $this->fail('Se esperaba ValidationException por contraseña incorrecta.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('confirm_password', $e->errors());
        }

        $ticket->refresh();
        $this->assertSame($originalTripId, $ticket->trip_id);
        $this->assertSame(0, $ticket->dateChanges()->count());

        // Sin password tampoco pasa.
        try {
            $service->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $ticket->trip->schedule_id,
                'seat_id' => $ticket->seat_id,
            ]);
            $this->fail('Se esperaba ValidationException sin contraseña.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('confirm_password', $e->errors());
        }

        $ticket->refresh();
        $this->assertSame($originalTripId, $ticket->trip_id);
    }

    public function test_reprogramar_con_password_correcta_funciona(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE, '1');

        $ticket = $this->createOneWayTicket('2026-10-01');

        $service = app(\App\Services\TicketRescheduleService::class);

        $result = $service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $ticket->trip->schedule_id,
            'seat_id' => $ticket->seat_id,
            'confirm_password' => 'secreto123',
        ]);

        $ticket->refresh();

        $newTrip = Trip::query()->whereDate('trip_date', '2026-10-05')->where('schedule_id', $ticket->trip->schedule_id)->first();

        $this->assertNotNull($newTrip);
        $this->assertSame((int) $newTrip->id, (int) $ticket->trip_id);
        $this->assertSame(1, $ticket->dateChanges()->count());
    }

    public function test_reprogramar_sin_setting_no_pide_password(): void
    {
        $this->actingAs($this->admin);

        // Sin set(): el setting está apagado (default false).

        $ticket = $this->createOneWayTicket('2026-10-01');

        $service = app(\App\Services\TicketRescheduleService::class);

        $service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $ticket->trip->schedule_id,
            'seat_id' => $ticket->seat_id,
            // Sin confirm_password: no debe importar.
        ]);

        $ticket->refresh();
        $this->assertSame(1, $ticket->dateChanges()->count());
    }

    public function test_el_formulario_de_reprogramacion_incluye_el_campo_cuando_el_setting_esta_activo(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE, '1');

        $ticket = $this->createOneWayTicket('2026-10-01');

        $component = Livewire::test(RescheduleTicket::class, ['record' => $ticket->id]);

        $html = $component->html();

        $this->assertStringContainsString('Tu contraseña', $html, 'El form debe pedir la contraseña con el setting activo.');

        // Apagar el setting: el campo desaparece.
        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE, '0');

        $component2 = Livewire::test(RescheduleTicket::class, ['record' => $ticket->id]);
        $this->assertStringNotContainsString('Tu contraseña', $component2->html());
    }
}
