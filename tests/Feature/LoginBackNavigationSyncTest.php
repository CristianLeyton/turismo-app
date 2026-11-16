<?php

namespace Tests\Feature;

use App\Models\Clients;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Script que pasa al formulario los valores que el navegador repone al volver
 * con Atrás a un ingreso (change sincronizar-formularios-al-volver-atras):
 * solo en las páginas de ingreso de los dos paneles.
 */
class LoginBackNavigationSyncTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = 'data-login-back-navigation-sync';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
    }

    public function test_el_ingreso_de_administracion_incluye_el_script_una_vez(): void
    {
        $content = $this->get('/admin/login')
            ->assertOk()
            ->assertSee("type !== 'back_forward'", false)
            ->getContent();

        $this->assertSame(1, substr_count($content, self::MARKER));
    }

    public function test_el_ingreso_de_clientes_incluye_el_script_una_vez(): void
    {
        $content = $this->get('/clientes/login')
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($content, self::MARKER));
    }

    public function test_el_script_no_aparece_en_paginas_autenticadas_de_administracion(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertDontSee(self::MARKER, false);
    }

    public function test_el_script_no_aparece_en_paginas_autenticadas_de_clientes(): void
    {
        $client = Clients::create([
            'dni' => '11223344',
            'nombre' => 'Ana',
            'apellido' => 'García',
            'telefono' => '3811111111',
            'password' => 'clave-prueba',
            'must_change_password' => false,
        ]);

        $this->actingAs($client, 'client');

        $this->get('/clientes')
            ->assertOk()
            ->assertDontSee(self::MARKER, false);
    }

    public function test_con_el_panel_de_clientes_apagado_el_ingreso_de_administracion_sigue_incluyendo_el_script(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '0');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee(self::MARKER, false);
    }
}
