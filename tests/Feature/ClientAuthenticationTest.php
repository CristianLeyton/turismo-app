<?php

namespace Tests\Feature;

use App\Models\Clients;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El cliente del padrón como usuario autenticable del guard `client`
 * (change login-clientes-filament).
 */
class ClientAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_cliente_vigente_se_autentica_con_dni_y_contraseña(): void
    {
        $client = $this->createClient('11223344', 'clave-propia');

        $this->assertTrue(Auth::guard('client')->attempt(['dni' => '11223344', 'password' => 'clave-propia']));
        $this->assertTrue(Auth::guard('client')->user()->is($client));
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_una_contraseña_incorrecta_no_autentica(): void
    {
        $this->createClient('11223344', 'clave-propia');

        $this->assertFalse(Auth::guard('client')->attempt(['dni' => '11223344', 'password' => 'otra']));
    }

    public function test_un_cliente_dado_de_baja_no_se_autentica(): void
    {
        $this->createClient('11223344', 'clave-propia')->delete();

        $this->assertFalse(Auth::guard('client')->attempt(['dni' => '11223344', 'password' => 'clave-propia']));
    }

    public function test_nombre_para_filament_y_acceso_solo_al_panel_de_clientes(): void
    {
        $client = $this->createClient('11223344', 'clave-propia');

        $this->assertSame('Ana Gómez', $client->getFilamentName());
        $this->assertTrue($client->canAccessPanel(Filament::getPanel('clientes')));
        $this->assertFalse($client->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_un_cliente_creado_sin_contraseña_recibe_la_inicial(): void
    {
        $client = Clients::create(['dni' => '11223344', 'nombre' => 'Ana', 'apellido' => 'Gómez', 'can_buy' => true]);

        $this->assertTrue(Hash::check('11223344', $client->fresh()->password));
        $this->assertTrue($client->fresh()->must_change_password);
        $this->assertTrue(Auth::guard('client')->attempt(['dni' => '11223344', 'password' => '11223344']));
    }

    public function test_un_cliente_creado_con_contraseña_conserva_la_suya(): void
    {
        $client = $this->createClient('11223344', 'clave-propia');

        $this->assertTrue(Hash::check('clave-propia', $client->fresh()->password));
        $this->assertFalse($client->fresh()->must_change_password);
    }

    public function test_la_migracion_asigna_la_inicial_solo_a_los_que_no_tienen(): void
    {
        $sinClave = $this->createClient('11223344', 'temporal');
        $conClave = $this->createClient('55667788', 'clave-propia');
        DB::table('clients')->where('id', $sinClave->id)->update(['password' => null, 'must_change_password' => false]);

        $migration = require database_path('migrations/2026_11_03_021634_set_initial_password_for_clients_without_password.php');
        $migration->up();

        $this->assertTrue(Hash::check('11223344', $sinClave->fresh()->password));
        $this->assertTrue($sinClave->fresh()->must_change_password);
        $this->assertTrue(Hash::check('clave-propia', $conClave->fresh()->password));
        $this->assertFalse($conClave->fresh()->must_change_password);
    }

    public function test_un_cliente_autenticado_no_entra_al_panel_de_administracion(): void
    {
        $this->actingAs($this->createClient('11223344', 'clave-propia'), 'client');

        $this->get('/clientes')->assertOk();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_un_usuario_de_administracion_no_entra_al_panel_de_clientes(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create(), 'web');

        $this->get('/clientes')->assertRedirect('/clientes/login');
        $this->assertGuest('client');
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
