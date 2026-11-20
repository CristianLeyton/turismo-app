<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\Auth\ClientEditProfile;
use App\Models\Clients;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Perfil del panel de clientes: después de guardar, el formulario vuelve
 * a llenarse con lo persistido en la base (trim de nombre y apellido,
 * teléfono vacío → null), igual que en el mount() inicial, para que la
 * pantalla quede sincronizada con los datos reales.
 */
class ClientEditProfileRefreshFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El panel de clientes viene apagado por defecto.
        \App\Models\Setting::set(\App\Models\Setting::PANEL_CLIENTES_ACTIVO, '1');
    }

    private function identifyClientWithPassword(string $dni, string $password = 'clave-secreta'): Clients
    {
        $client = Clients::create([
            'dni' => $dni,
            'nombre' => 'Carla',
            'apellido' => 'Díaz',
            'telefono' => '3813333333',
            'password' => $password,
            'must_change_password' => false,
        ]);

        Filament::setCurrentPanel('clientes');
        $this->actingAs($client, 'client');

        return $client;
    }

    /** Perfil de Filament del panel de clientes, con el panel actual fijado. */
    private function profile(): \Livewire\Features\SupportTesting\Testable
    {
        Filament::setCurrentPanel('clientes');

        return Livewire::test(ClientEditProfile::class);
    }

    public function test_guardar_deja_el_formulario_con_lo_persistido(): void
    {
        $client = $this->identifyClientWithPassword('55667799');

        // Espacios sobrantes en el nombre y teléfono en blanco:
        // mutateFormDataBeforeSave los normaliza al persistir.
        $this->profile()
            ->set('data.nombre', '  Carla Belén  ')
            ->set('data.apellido', 'Díaz Ríos')
            ->set('data.telefono', '   ')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $client->fresh();

        $this->assertSame('Carla Belén', $fresh->nombre);
        $this->assertSame('Díaz Ríos', $fresh->apellido);
        $this->assertNull($fresh->telefono);

        // El formulario muestra lo persistido (no lo tipeado con espacios).
        $this->profile()
            ->assertSet('data.nombre', 'Carla Belén')
            ->assertSet('data.apellido', 'Díaz Ríos')
            ->assertSet('data.telefono', null);
    }

    public function test_un_error_de_validacion_no_rehidrata_y_conserva_lo_escrito(): void
    {
        $this->identifyClientWithPassword('55667799');

        // Apellido vacío: error de "required" y sin guardar nada.
        $component = $this->profile()
            ->set('data.nombre', 'Ceci')
            ->set('data.apellido', '')
            ->set('data.telefono', '3812222222')
            ->call('save')
            ->assertHasErrors(['data.apellido']);

        $this->assertSame('Díaz', Clients::where('dni', '55667799')->value('apellido'));

        // Lo que escribió el usuario sigue en el formulario: no se leyó
        // la base por encima.
        $component
            ->assertSet('data.nombre', 'Ceci')
            ->assertSet('data.apellido', '')
            ->assertSet('data.telefono', '3812222222');
    }

    public function test_tras_cambiar_la_contrasena_los_campos_quedan_vacios_y_la_persistida_es_la_nueva(): void
    {
        $client = $this->identifyClientWithPassword('66778800');
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

        // Campos de contraseña vacíos, como en un ingreso fresco.
        $this->profile()
            ->assertSet('data.password', null)
            ->assertSet('data.passwordConfirmation', null)
            ->assertSet('data.currentPassword', null);
    }
}
