<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\Permissions;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // El usuario id 1 (fundador) está excluido de la tabla y las
        // acciones del id 2 están deshabilitadas: crear usuarios extra
        // deja al admin de prueba (id 3) y a los targets fuera de esas
        // restricciones.
        User::factory()->superAdmin()->create();
        User::factory()->create();
        $this->admin = User::factory()->admin()->create();

        $this->actingAs($this->admin);
    }

    // ====================== Roles obligatorios ======================

    public function test_crear_un_usuario_requiere_al_menos_un_rol(): void
    {
        Livewire::test(ManageUsers::class)
            ->callAction('create', data: [
                'username' => 'sinrol',
                'name' => 'Sin',
                'password' => 'secreta-123',
            ])
            ->assertHasActionErrors(['roles']);

        $this->assertDatabaseMissing('users', ['username' => 'sinrol']);
    }

    public function test_crear_un_usuario_con_rol_lo_persiste(): void
    {
        $vendedor = Role::findByName(Permissions::ROLE_SELLER);

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: [
                'username' => 'conrol',
                'name' => 'Con',
                'password' => 'secreta-123',
                'roles' => [$vendedor->id],
            ])
            ->assertHasNoActionErrors();

        $user = User::where('username', 'conrol')->firstOrFail();

        $this->assertTrue($user->hasRole(Permissions::ROLE_SELLER));
    }

    public function test_no_se_puede_editar_un_usuario_para_dejarlo_sin_rol(): void
    {
        $vendedor = User::factory()->vendedor()->create();

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $vendedor, data: ['roles' => []])
            ->assertHasTableActionErrors(['roles']);

        // El rol sigue intacto: la edición no se guardó.
        $this->assertTrue($vendedor->fresh()->hasRole(Permissions::ROLE_SELLER));
    }

    // ====================== Filtro por roles ======================

    public function test_el_filtro_por_roles_muestra_solo_los_usuarios_con_ese_rol(): void
    {
        // Filtrando por Administrador: los admins (id 3 y 5) se ven y el
        // vendedor (id 4, sólo Vendedor) queda fuera.
        $vendedor = User::factory()->vendedor()->create();
        $otroAdmin = User::factory()->admin()->create();

        $adminRoleId = Role::findByName(Permissions::ROLE_ADMIN)->id;

        Livewire::test(ManageUsers::class)
            ->filterTable('roles', [$adminRoleId])
            ->assertCanSeeTableRecords([$this->admin, $otroAdmin])
            ->assertCanNotSeeTableRecords([$vendedor]);
    }

    // ====================== Filtro de eliminados ======================

    public function test_el_filtro_de_eliminados_muestra_ocultos_y_solo_eliminados(): void
    {
        $activo = User::factory()->vendedor()->create();
        $eliminado = User::factory()->vendedor()->create();
        $eliminado->delete();

        // Por defecto los eliminados no se ven.
        Livewire::test(ManageUsers::class)
            ->assertCanSeeTableRecords([$activo])
            ->assertCanNotSeeTableRecords([$eliminado]);

        // "Con eliminados" los incluye junto a los activos.
        Livewire::test(ManageUsers::class)
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$activo, $eliminado]);

        // "Sólo eliminados" muestra únicamente los eliminados.
        Livewire::test(ManageUsers::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$eliminado])
            ->assertCanNotSeeTableRecords([$activo]);
    }

    // ====================== Opciones del filtro de roles ======================

    public function test_el_filtro_de_roles_oculta_super_administrador_a_un_admin(): void
    {
        // Actúa un administrador (no super): el rol Super Administrador no
        // debe estar entre las opciones del filtro.
        $opciones = Livewire::test(ManageUsers::class)
            ->instance()
            ->getTable()
            ->getFilter('roles')
            ->getRelationshipQuery()
            ->pluck('name')
            ->all();

        $this->assertContains(Permissions::ROLE_ADMIN, $opciones);
        $this->assertContains(Permissions::ROLE_SELLER, $opciones);
        $this->assertNotContains(Permissions::ROLE_SUPER, $opciones);
    }

    public function test_el_filtro_de_roles_muestra_super_administrador_a_un_superadministrador(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super);

        $opciones = Livewire::test(ManageUsers::class)
            ->instance()
            ->getTable()
            ->getFilter('roles')
            ->getRelationshipQuery()
            ->pluck('name')
            ->all();

        $this->assertContains(Permissions::ROLE_SUPER, $opciones);
    }

    // ====================== Comportamiento de los filtros ======================

    public function test_los_filtros_estan_arriba_y_se_aplican_sin_boton(): void
    {
        $table = Livewire::test(ManageUsers::class)->instance()->getTable();

        $this->assertSame(FiltersLayout::AboveContent, $table->getFiltersLayout());
        $this->assertFalse($table->hasDeferredFilters());
    }
}
