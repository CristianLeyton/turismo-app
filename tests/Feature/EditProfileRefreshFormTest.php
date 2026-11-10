<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Perfil del panel de administración: después de guardar, el formulario
 * vuelve a llenarse con lo persistido en la base, igual que en el mount()
 * inicial, para que la pantalla quede sincronizada con los datos reales.
 */
class EditProfileRefreshFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->superAdmin()->create();
        $this->admin = User::factory()->admin()->create();

        $this->actingAs($this->admin);
    }

    public function test_guardar_deja_el_formulario_con_lo_persistido_y_las_contrasenas_vacias(): void
    {
        $this->assertNotSame('usuario-actualizado', $this->admin->username);

        Livewire::test(EditProfile::class)
            ->assertSet('data.username', $this->admin->username)
            ->set('data.username', 'usuario-actualizado')
            ->set('data.password', 'nueva-clave-1234')
            ->set('data.passwordConfirmation', 'nueva-clave-1234')
            ->set('data.currentPassword', 'password')
            ->call('save')
            ->assertHasNoErrors();

        // La base quedó con lo nuevo...
        $fresh = $this->admin->fresh();

        $this->assertSame('usuario-actualizado', $fresh->username);
        $this->assertTrue(filled($fresh->password));

        // ... y el formulario, igual que en un ingreso fresco: lo
        // persistido, sin la contraseña tipeada en ningún campo.
        $component = Livewire::test(EditProfile::class);

        $component
            ->assertSet('data.username', 'usuario-actualizado')
            ->assertSet('data.password', null)
            ->assertSet('data.passwordConfirmation', null)
            ->assertSet('data.currentPassword', null);
    }

    public function test_un_error_de_validacion_no_rehidrata_y_conserva_lo_escrito(): void
    {
        // Username vacío: error de "required" y sin guardar nada.
        $component = Livewire::test(EditProfile::class)
            ->set('data.currentPassword', 'password')
            ->set('data.password', 'nueva-clave-1234')
            ->set('data.passwordConfirmation', 'nueva-clave-1234')
            ->set('data.username', '')
            ->call('save')
            ->assertHasErrors(['data.username']);

        // Lo que escribió el usuario sigue en el formulario: no se leyó
        // la base por encima (la contraseña tipeada no se pisa con null).
        $this->assertSame($this->admin->username, $this->admin->fresh()->username);

        $component
            ->assertSet('data.password', 'nueva-clave-1234')
            ->assertSet('data.passwordConfirmation', 'nueva-clave-1234')
            ->assertSet('data.currentPassword', 'password');
    }
}
