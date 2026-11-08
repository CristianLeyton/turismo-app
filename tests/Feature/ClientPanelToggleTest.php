<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Clients;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Parámetro "Panel de clientes activo" (change client-panel-toggle): el panel
 * /clientes y sus rutas sueltas existen solo con el parámetro activado; el
 * aviso del login de administración lo sigue y el link público del boleto no
 * depende de él.
 */
class ClientPanelToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();
    }

    public function test_el_parametro_esta_desactivado_por_defecto(): void
    {
        $this->assertFalse(Setting::panelClientesActivo());

        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $this->assertTrue(Setting::panelClientesActivo());
    }

    public function test_sin_configurar_el_panel_responde_404(): void
    {
        $this->get('/clientes/login')->assertNotFound();
        $this->get('/clientes')->assertNotFound();
    }

    public function test_desactivado_el_panel_y_sus_rutas_sueltas_responden_404(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '0');
        $ticket = $this->createTicketFor('11223344');

        $this->get('/clientes/login')->assertNotFound();
        $this->get('/clientes')->assertNotFound();
        $this->get('/clientes/perfil')->assertNotFound();
        $this->get('/clientes/ingresar')->assertNotFound();
        $this->post('/clientes/salir')->assertNotFound();
        $this->get("/clientes/boletos/{$ticket->id}")->assertNotFound();
        $this->get("/clientes/boletos/{$ticket->id}/pdf")->assertNotFound();
    }

    public function test_desactivado_un_cliente_con_sesion_recibe_404(): void
    {
        $ticket = $this->createTicketFor('11223344');
        $this->actingAs(Clients::query()->where('dni', '11223344')->sole(), 'client');

        $this->get('/clientes')->assertNotFound();
        $this->get("/clientes/boletos/{$ticket->id}")->assertNotFound();
        $this->get("/clientes/boletos/{$ticket->id}/pdf")->assertNotFound();
    }

    public function test_activado_el_panel_funciona_como_siempre(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $this->get('/clientes')->assertRedirect('/clientes/login');
        $this->get('/clientes/login')->assertOk()->assertSeeText('DNI');
        $this->get('/clientes/ingresar')->assertRedirect('/clientes/login');
    }

    public function test_desactivar_corta_las_acciones_livewire_de_una_pagina_abierta(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
        $this->createTicketFor('11223344');
        $this->actingAs(Clients::query()->where('dni', '11223344')->sole(), 'client');

        $html = $this->get('/clientes')->assertOk()->getContent();
        $snapshot = $this->componentSnapshot($html, 'app.filament.client.pages.my-tickets');

        $update = fn () => $this->withHeaders(['X-Livewire' => 'true'])
            ->postJson('/livewire/update', [
                '_token' => csrf_token(),
                'components' => [['snapshot' => $snapshot, 'updates' => new \stdClass, 'calls' => []]],
            ]);

        $update()->assertOk();

        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '0');

        $update()->assertNotFound();
    }

    public function test_desactivado_el_link_publico_del_boleto_sigue_funcionando(): void
    {
        $ticket = $this->createTicketFor('11223344');

        $response = $this->get($ticket->shareUrl())->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_el_aviso_del_login_de_administracion_sigue_al_parametro(): void
    {
        $this->get('/admin/login')->assertOk()->assertDontSee('¿Sos cliente?');

        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('¿Sos cliente?')
            ->assertSee('href="'.route('filament.clientes.auth.login').'"', false);
    }

    public function test_el_ingreso_de_clientes_muestra_las_lineas_de_contacto(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
        Setting::set(Setting::CONTACT_LINES, json_encode([
            ['name' => 'Oficina Orán', 'phone' => '+54 9 (3878) 804-506'],
            ['name' => null, 'phone' => '387 555-1234'],
        ]));

        $this->get('/clientes/login')
            ->assertOk()
            ->assertSee('¿No podés entrar? Comunicate con nosotros:')
            ->assertSeeInOrder(['Oficina Orán:', '+54 9 (3878) 804-506', '387 555-1234'])
            ->assertSee('href="tel:+5493878804506"', false)
            ->assertSee('href="tel:3875551234"', false);
    }

    public function test_cada_linea_del_ingreso_de_clientes_tiene_su_boton_de_whatsapp(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
        Setting::set(Setting::CONTACT_LINES, json_encode([
            ['name' => 'Oficina Orán', 'phone' => '+54 9 (3878) 804-506'],
            ['name' => null, 'phone' => '387 555-1234'],
        ]));

        $this->get('/clientes/login')
            ->assertOk()
            ->assertSee('href="https://wa.me/5493878804506"', false)
            ->assertSee('href="https://wa.me/5493875551234"', false)
            ->assertSee('data-icon="whatsapp"', false)
            ->assertSee('WhatsApp');
    }

    public function test_sin_lineas_de_contacto_el_ingreso_de_clientes_no_muestra_el_bloque(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $this->get('/clientes/login')
            ->assertOk()
            ->assertDontSee('Comunicate con nosotros:');
    }

    public function test_las_lineas_de_contacto_no_aparecen_en_el_ingreso_de_administracion(): void
    {
        Setting::set(Setting::CONTACT_LINES, json_encode([
            ['name' => 'Oficina Orán', 'phone' => '3878804506'],
        ]));

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('Comunicate con nosotros:');
    }

    /**
     * Snapshot (JSON) del componente Livewire `$name` en el HTML de una página.
     */
    private function componentSnapshot(string $html, string $name): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);

            if ((json_decode($snapshot, true)['memo']['name'] ?? null) === $name) {
                return $snapshot;
            }
        }

        $this->fail("No se encontró el componente {$name} en la página.");
    }

    /**
     * Cliente del padrón (contraseña ya elegida) con un boleto pendiente.
     */
    private function createTicketFor(string $dni): Ticket
    {
        Clients::create([
            'dni' => $dni,
            'nombre' => 'Ana',
            'apellido' => 'García',
            'telefono' => '3811111111',
            'password' => 'clave-prueba',
            'must_change_password' => false,
        ]);

        $origin = Location::create(['name' => 'Tartagal', 'is_active' => true]);
        $destination = Location::create(['name' => 'Urundel', 'is_active' => true]);
        $bus = Bus::create(['name' => 'Linea SF', 'plate' => 'SF123', 'seat_count' => 4, 'floors' => 1]);

        $passenger = Passenger::create([
            'first_name' => 'Ana',
            'last_name' => 'García',
            'dni' => $dni,
            'passenger_type' => 'adult',
        ]);

        $sale = Sale::create([
            'user_id' => User::factory()->vendedor()->create()->id,
            'sale_date' => now(),
            'total_amount' => 15000,
        ]);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => null,
            'seat_id' => null,
            'return_trip_id' => null,
            'bus_id' => $bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'price' => 15000,
        ]);
    }
}
