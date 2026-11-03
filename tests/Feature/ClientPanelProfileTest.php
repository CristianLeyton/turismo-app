<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\Auth\ClientEditProfile;
use App\Filament\Client\Pages\Auth\ClientLogin;
use App\Filament\Client\Pages\MyTickets;
use App\Filament\Client\Pages\SetPassword;
use App\Models\Clients;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Contraseña del cliente (panel público /clientes):
 * - la inicial es el DNI: entra con DNI + su DNI como contraseña,
 * - el primer ingreso obliga a definirla (nada del panel antes),
 * - después el ingreso exige DNI + contraseña,
 * - Mi perfil permite datos personales y cambio de contraseña (pidiendo la actual),
 * - el DNI del perfil nunca cambia desde el panel,
 * - el action de admin "Resetear contraseña" hace que vuelva el flujo inicial.
 */
class ClientPanelProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush(); // El rate limiter de identificación usa la caché
    }

    private function createInitialClient(string $dni = '33445566'): Clients
    {
        // Estado real post-migración/alta: contraseña inicial hasheada = su
        // DNI y bandera de primer ingreso. Entrará por DNI (o DNI + su DNI
        // como contraseña) y deberá definirla antes de ver sus boletos.
        return Clients::create([
            'dni' => $dni,
            'nombre' => 'Beto',
            'apellido' => 'Suárez',
            'telefono' => '3812222222',
            'password' => $dni, // cast 'hashed': guarda bcrypt
            'must_change_password' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // Contraseña inicial = DNI y cambio obligatorio
    // ------------------------------------------------------------------

    public function test_nadie_entra_con_el_dni_solo(): void
    {
        $this->createInitialClient();

        // La contraseña es SIEMPRE obligatoria: DNI solo no identifica ni en
        // el primer ingreso (la inicial, el DNI, va también en la contraseña).
        $this->clientLogin('33445566', '');

        $this->assertGuest('client');

        // Con el DNI repetido como contraseña (la inicial) sí entra.
        auth('client')->logout();

        $this->clientLogin('33445566', '33445566')
            ->assertRedirect(MyTickets::getUrl(panel: 'clientes'));

        $this->assertSame('33445566', auth('client')->user()?->dni);

        // Y queda en cartel de clave de primer ingreso.
        $this->get('/clientes')->assertRedirect('/clientes/clave');
        $this->get('/clientes/clave')->assertOk();

        // El perfil también está bloqueado hasta definir la clave.
        $this->get('/clientes/perfil')->assertRedirect('/clientes/clave');
    }

    public function test_en_el_primer_ingreso_una_contraseña_errónea_no_identifica(): void
    {
        $this->createInitialClient('34455667');

        $this->clientLogin('34455667', 'no-es-su-clave');

        $this->assertGuest('client');
    }

    public function test_elegir_la_clave_guarda_el_hash_y_habilita_el_panel(): void
    {
        $client = $this->createInitialClient();

        $this->actingAs($client, 'client');

        Livewire::test(SetPassword::class)
            ->set('nuevaClave', 'mi-clave-secreta')
            ->set('nuevaClaveConfirmacion', 'mi-clave-secreta')
            ->call('guardar')
            ->assertRedirect(MyTickets::getUrl(panel: 'clientes'));

        $fresh = $client->fresh();
        $this->assertTrue(Hash::check('mi-clave-secreta', $fresh->password));
        $this->assertTrue($fresh->hasPersonalPassword());
        $this->assertFalse($fresh->needsPasswordSetup());
        $this->assertFalse($fresh->must_change_password);

        // Ahora el panel queda accesible directamente.
        $this->get('/clientes')->assertOk();
    }

    public function test_el_primer_ingreso_saluda_con_el_nombre_de_la_app_y_del_cliente(): void
    {
        config(['app.name' => 'Expresso Sofia']);
        $this->actingAs($this->createInitialClient(), 'client');

        Livewire::test(SetPassword::class)
            ->assertSeeText('¡Bienvenido a Expresso Sofia, Beto!');
    }

    public function test_elegir_la_clave_valida_largo_y_coincidencia(): void
    {
        $client = $this->createInitialClient();

        $this->actingAs($client, 'client');

        // Demasiado corta: no guarda nada (la inicial, el DNI, queda igual).
        Livewire::test(SetPassword::class)
            ->set('nuevaClave', 'corta')
            ->set('nuevaClaveConfirmacion', 'corta')
            ->call('guardar');

        $this->assertTrue(Hash::check($client->dni, $client->fresh()->password));

        // No coincide: no guarda nada.
        Livewire::test(SetPassword::class)
            ->set('nuevaClave', 'mi-clave-secreta')
            ->set('nuevaClaveConfirmacion', 'distinta')
            ->call('guardar');

        $this->assertTrue(Hash::check($client->dni, $client->fresh()->password));
        $this->assertTrue($client->fresh()->must_change_password);
    }

    public function test_desde_que_elije_contraseña_el_ingreso_exige_dni_mas_contraseña(): void
    {
        $client = $this->createInitialClient();

        // Primer ingreso: DNI + su DNI como contraseña (la inicial) y elige clave.
        $this->clientLogin($client->dni, $client->dni);

        Livewire::test(SetPassword::class)
            ->set('nuevaClave', 'mi-clave-secreta')
            ->set('nuevaClaveConfirmacion', 'mi-clave-secreta')
            ->call('guardar');

        // Salió (simulado): DNI solo ya NO alcanza.
        auth('client')->logout();

        $this->clientLogin($client->dni, '');

        $this->assertGuest('client');

        // DNI + la clave que eligió: sí.
        $this->clientLogin($client->dni, 'mi-clave-secreta')
            ->assertRedirect(MyTickets::getUrl(panel: 'clientes'));

        $this->assertSame($client->dni, auth('client')->user()?->dni);
    }

    /** Ingreso por el login de Filament del panel de clientes. */
    private function clientLogin(string $dni, string $password): \Livewire\Features\SupportTesting\Testable
    {
        Filament::setCurrentPanel('clientes');

        return Livewire::test(ClientLogin::class)
            ->fillForm(['dni' => $dni, 'password' => $password])
            ->call('authenticate');
    }

    // ------------------------------------------------------------------
    // Mi perfil
    // ------------------------------------------------------------------

    private function identifyClientWithPassword(string $dni, string $password = 'clave-secreta'): Clients
    {
        $client = Clients::create([
            'dni' => $dni,
            'nombre' => 'Carla',
            'apellido' => 'Díaz',
            'telefono' => '3813333333',
            'password' => $password,
            'must_change_password' => false, // ya eligió su propia clave
        ]);

        $this->actingAs($client, 'client');

        return $client;
    }

    /** Perfil de Filament del panel de clientes, con el panel actual fijado. */
    private function profile(): \Livewire\Features\SupportTesting\Testable
    {
        Filament::setCurrentPanel('clientes');

        return Livewire::test(ClientEditProfile::class);
    }

    public function test_cambiar_la_contraseña_desde_el_perfil_levanta_el_cambio_obligatorio(): void
    {
        $client = $this->identifyClientWithPassword('55667799');
        $client->forceFill(['must_change_password' => true])->saveQuietly();

        $this->profile()
            ->set('data.currentPassword', 'clave-secreta')
            ->set('data.password', 'nueva-clave')
            ->set('data.passwordConfirmation', 'nueva-clave')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $client->fresh();
        $this->assertTrue(Hash::check('nueva-clave', $fresh->password));
        $this->assertFalse($fresh->must_change_password);
    }

    public function test_un_telefono_invalido_no_se_guarda(): void
    {
        $client = $this->identifyClientWithPassword('55667799');

        $this->profile()
            ->set('data.telefono', '12ab')
            ->call('save')
            ->assertHasErrors(['data.telefono']);

        $this->assertSame('3813333333', $client->fresh()->telefono);
    }

    public function test_el_perfil_queda_en_la_direccion_de_siempre(): void
    {
        $this->identifyClientWithPassword('55667799');

        $this->get('/clientes/perfil')->assertOk()->assertSee('DNI');
    }

    public function test_el_cliente_actualiza_sus_datos_personales(): void
    {
        $client = $this->identifyClientWithPassword('55667799');

        $this->profile()
            ->set('data.nombre', 'Carla Belén')
            ->set('data.apellido', 'Díaz Ríos')
            ->set('data.telefono', '3814444444')
            ->call('save');

        $fresh = $client->fresh();

        $this->assertSame('Carla Belén', $fresh->nombre);
        $this->assertSame('Díaz Ríos', $fresh->apellido);
        $this->assertSame('3814444444', $fresh->telefono);
        $this->assertSame('55667799', $fresh->dni); // read-only: intacto
    }

    public function test_el_perfil_no_modifica_ni_dni_ni_contraseña(): void
    {
        $client = $this->identifyClientWithPassword('55667799');

        // "Enviar" por la bolsa un DNI inventado y una password no afecta:
        // el form del perfil nunca toca esas columnas (y el teléfono inválido
        // corta el guardado por validación).
        $this->profile()
            ->set('data.dni', '99999999')
            ->set('data.telefono', 'no-un-telefono')
            ->set('data.password', 'intento-de-cambio')
            ->set('data.passwordConfirmation', 'intento-de-cambio')
            ->set('data.currentPassword', 'clave-secreta')
            ->call('save');

        $fresh = $client->fresh();
        $this->assertSame('55667799', $fresh->dni);
        $this->assertTrue(Hash::check('clave-secreta', $fresh->password));
    }

    public function test_campos_requeridos_del_perfil_con_errores_mantienen_lo_escrito(): void
    {
        $this->identifyClientWithPassword('55667799');

        // Apellido vacío: error debajo del campo y sin guardar nada.
        $component = $this->profile()
            ->set('data.nombre', 'Ceci')
            ->set('data.apellido', '')
            ->set('data.telefono', '3812222222')
            ->call('save')
            ->assertHasErrors(['data.apellido']);

        $this->assertSame('Díaz', Clients::where('dni', '55667799')->value('apellido'));

        // Los campos siguen con lo que escribió el usuario, como en los
        // formularios del panel admin.
        $component
            ->assertSet('data.nombre', 'Ceci')
            ->assertSet('data.telefono', '3812222222');
    }

    public function test_cambiar_la_contraseña_exige_la_actual(): void
    {
        $client = $this->identifyClientWithPassword('66778800');

        // Actual errónea: error del formulario y no cambia.
        $this->profile()
            ->set('data.currentPassword', 'wrong-password')
            ->set('data.password', 'nueva-clave-larga')
            ->set('data.passwordConfirmation', 'nueva-clave-larga')
            ->call('save')
            ->assertHasErrors(['data.currentPassword']);

        $this->assertTrue(Hash::check('clave-secreta', $client->fresh()->password));

        // Actual correcta: cambia.
        $this->profile()
            ->set('data.currentPassword', 'clave-secreta')
            ->set('data.password', 'nueva-clave-larga')
            ->set('data.passwordConfirmation', 'nueva-clave-larga')
            ->call('save');

        $fresh = $client->fresh();
        $this->assertTrue(Hash::check('nueva-clave-larga', $fresh->password));
        $this->assertFalse(Hash::check('clave-secreta', $fresh->password));
    }

    public function test_cambio_de_contraseña_valida_largo_y_coincidencia(): void
    {
        $client = $this->identifyClientWithPassword('66778800', password: 'clave-secreta');

        // Demasiado corta: error debajo del campo y sin cambios.
        $this->profile()
            ->set('data.currentPassword', 'clave-secreta')
            ->set('data.password', 'corta')
            ->set('data.passwordConfirmation', 'corta')
            ->call('save')
            ->assertHasErrors(['data.password']);

        $this->assertTrue(Hash::check('clave-secreta', $client->fresh()->password));

        // No coincide: lo mismo.
        $this->profile()
            ->set('data.currentPassword', 'clave-secreta')
            ->set('data.password', 'larga-suficiente')
            ->set('data.passwordConfirmation', 'otra-cosa-larga')
            ->call('save')
            ->assertHasErrors(['data.password']);

        $this->assertTrue(Hash::check('clave-secreta', $client->fresh()->password));
    }

    // ------------------------------------------------------------------
    // Reset desde el panel de administración
    // ------------------------------------------------------------------

    public function test_despues_del_reset_admin_vuelve_el_flujo_inicial(): void
    {
        $client = $this->identifyClientWithPassword('77889900');

        // Reset del admin: contraseña vuelve a ser su DNI (hasheada) y la
        // bandera de primer ingreso se activa de nuevo.
        $client->update([
            'password' => $client->dni,
            'must_change_password' => true,
        ]);

        $fresh = $client->fresh();

        $this->assertTrue($fresh->needsPasswordSetup());
        $this->assertTrue(Hash::check($fresh->dni, $fresh->password));

        // La contraseña inicial otra vez el DNI: DNI + su DNI identifica.
        auth('client')->logout();

        $this->clientLogin($client->dni, $client->dni)
            ->assertRedirect(MyTickets::getUrl(panel: 'clientes'));

        // Y vuelve a tener que elegir una contraseña nueva.
        $this->get('/clientes')->assertRedirect('/clientes/clave');
    }

    public function test_resetear_la_contraseña_no_borra_datos_ni_boletos(): void
    {
        $client = $this->identifyClientWithPassword('77889900');

        $client->update([
            'password' => $client->dni,
            'must_change_password' => true,
        ]);

        $fresh = $client->fresh();

        $this->assertSame('Carla', $fresh->nombre);
        $this->assertSame('Díaz', $fresh->apellido);
        $this->assertTrue(Hash::check($fresh->dni, $fresh->password));
    }
}
