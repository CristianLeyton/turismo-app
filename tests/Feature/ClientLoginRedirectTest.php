<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\Auth\ClientLogin;
use App\Filament\Client\Pages\MyTickets;
use App\Filament\Resources\Users\Pages\Auth\CustomLogin;
use App\Models\Clients;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Destino del ingreso (change fix-client-login-redirect): la URL prevista
 * guardada en la sesión (`url.intended`) sólo se respeta si es una página
 * del panel de clientes; si apunta a la zona admin o a otra parte, el
 * ingreso lleva a "Mis boletos". El login de administración queda igual.
 */
class ClientLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El panel de clientes viene apagado por defecto.
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
    }

    public function test_la_url_prevista_del_admin_no_secuestra_el_ingreso_del_cliente(): void
    {
        Filament::setCurrentPanel('clientes');

        // Escenario del bug: pasando por /admin sin sesión web, Laravel
        // guarda la URL prevista /admin; después el cliente ingresa bien.
        $this->createClient('11223344', 'clave-propia');
        session(['url.intended' => 'http://localhost/admin']);

        $this->clientLogin()
            ->fillForm(['dni' => '11223344', 'password' => 'clave-propia'])
            ->call('authenticate')
            ->assertRedirect(MyTickets::getUrl(panel: 'clientes'));

        $this->assertGuest('web');
        $this->assertFalse(session()->has('url.intended'));
    }

    public function test_la_url_prevista_de_la_raiz_tampoco_secuestra(): void
    {
        Filament::setCurrentPanel('clientes');

        $this->createClient('11223344', 'clave-propia');
        session(['url.intended' => 'http://localhost/']);

        $this->clientLogin()
            ->fillForm(['dni' => '11223344', 'password' => 'clave-propia'])
            ->call('authenticate')
            ->assertRedirect(MyTickets::getUrl(panel: 'clientes'));
    }

    public function test_la_url_prevista_del_link_compartible_tampoco(): void
    {
        Filament::setCurrentPanel('clientes');

        $this->createClient('11223344', 'clave-propia');
        session(['url.intended' => 'http://localhost/boletos/t_token-publico']);

        $this->clientLogin()
            ->fillForm(['dni' => '11223344', 'password' => 'clave-propia'])
            ->call('authenticate')
            ->assertRedirect(MyTickets::getUrl(panel: 'clientes'));
    }

    public function test_la_url_prevista_de_una_pagina_del_panel_se_respeta(): void
    {
        Filament::setCurrentPanel('clientes');

        $this->createClient('11223344', 'clave-propia');
        session(['url.intended' => 'http://localhost/clientes/boletos/7']);

        $this->clientLogin()
            ->fillForm(['dni' => '11223344', 'password' => 'clave-propia'])
            ->call('authenticate')
            ->assertRedirect('http://localhost/clientes/boletos/7');

        // La URL prevista se consume (no queda para el próximo ingreso).
        $this->assertFalse(session()->has('url.intended'));
    }

    public function test_un_cliente_con_sesion_que_abre_el_ingreso_no_va_al_admin(): void
    {
        Filament::setCurrentPanel('clientes');

        $this->actingAs($this->createClient('11223344', 'clave-propia'), 'client');
        session(['url.intended' => 'http://localhost/admin']);

        $this->clientLogin()
            // El redirect ocurre en mount(), sin datos en el formulario.
            ->assertRedirect(MyTickets::getUrl(panel: 'clientes'));
    }

    /** El login de administración usa la respuesta de Filament de siempre. */
    public function test_el_login_de_administracion_sigue_la_url_prevista(): void
    {
        Filament::setCurrentPanel('admin');

        $user = User::factory()->superAdmin()->create();
        $destinoProtegido = 'http://localhost/admin/usuarios/55667788';

        session(['url.intended' => $destinoProtegido]);

        Livewire::test(CustomLogin::class)
            ->fillForm(['username' => $user->username, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect($destinoProtegido);

        $this->assertTrue(auth('web')->check());
    }

    private function clientLogin(): \Livewire\Features\SupportTesting\Testable
    {
        Filament::setCurrentPanel('clientes');

        return Livewire::test(ClientLogin::class);
    }

    private function createClient(string $dni, string $password): Clients
    {
        return Clients::create([
            'dni' => $dni,
            'nombre' => 'Ana',
            'apellido' => 'Gómez',
            'can_buy' => true,
            'password' => $password,
            'must_change_password' => false,
        ]);
    }
}
