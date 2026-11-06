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

    // ====================== Primer administrador ======================

    public function test_un_administrador_edita_al_primer_administrador_y_sus_roles_no_cambian(): void
    {
        $firstAdmin = $this->firstAdmin();

        Livewire::test(ManageUsers::class)
            ->assertCanSeeTableRecords([$firstAdmin])
            ->assertTableActionEnabled('edit', $firstAdmin)
            ->callTableAction('edit', $firstAdmin, data: ['name' => 'Nombre corregido'])
            ->assertHasNoTableActionErrors();

        $firstAdmin->refresh();
        $this->assertSame('Nombre corregido', $firstAdmin->name);
        $this->assertSame([Permissions::ROLE_ADMIN], $firstAdmin->getRoleNames()->all());
    }

    public function test_forzar_otros_roles_al_primer_administrador_no_los_cambia(): void
    {
        $firstAdmin = $this->firstAdmin();
        $vendedor = Role::findByName(Permissions::ROLE_SELLER);

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $firstAdmin, data: [
                'name' => 'Otro nombre',
                'roles' => [$vendedor->id],
            ]);

        $this->assertSame([Permissions::ROLE_ADMIN], $firstAdmin->fresh()->getRoleNames()->all());
    }

    public function test_al_editar_al_primer_administrador_los_roles_estan_deshabilitados(): void
    {
        $firstAdmin = $this->firstAdmin();
        $other = User::factory()->vendedor()->create();

        $rolesField = fn (User $record) => Livewire::test(ManageUsers::class)
            ->mountTableAction('edit', $record)
            ->instance()
            ->getMountedTableActionForm()
            ->getComponent('roles', withHidden: true);

        $this->assertTrue($rolesField($firstAdmin)->isDisabled());
        $this->assertFalse($rolesField($other)->isDisabled());
    }

    public function test_un_administrador_puede_restablecer_la_contrasena_del_primer_administrador(): void
    {
        $firstAdmin = $this->firstAdmin();

        Livewire::test(ManageUsers::class)
            ->assertTableActionEnabled('resetPassword', $firstAdmin);
    }

    public function test_quien_no_es_administrador_ve_deshabilitadas_las_acciones_del_primer_administrador(): void
    {
        $firstAdmin = $this->firstAdmin();
        $other = User::factory()->vendedor()->create();

        $editor = User::factory()->create();
        $editor->givePermissionTo(['users.view_any', 'users.view', 'users.update', 'users.delete']);
        $this->actingAs($editor);

        Livewire::test(ManageUsers::class)
            ->assertTableActionVisible('edit', $firstAdmin)
            ->assertTableActionDisabled('edit', $firstAdmin)
            ->assertTableActionDisabled('resetPassword', $firstAdmin)
            ->assertTableActionVisible('delete', $firstAdmin)
            ->assertTableActionDisabled('delete', $firstAdmin)
            ->assertTableActionEnabled('edit', $other)
            ->assertTableActionEnabled('delete', $other);

        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($editor)->denies('update', $firstAdmin));
    }

    /**
     * "Eliminar permanentemente" solo aparece en usuarios eliminados, y al
     * primer administrador no se lo puede eliminar: su caso lo cubre la policy.
     */
    public function test_nadie_puede_eliminar_al_primer_administrador_desde_la_tabla(): void
    {
        $firstAdmin = $this->firstAdmin();

        foreach ([$this->admin, User::factory()->superAdmin()->create()] as $user) {
            $this->actingAs($user);

            Livewire::test(ManageUsers::class)
                ->assertTableActionVisible('delete', $firstAdmin)
                ->assertTableActionDisabled('delete', $firstAdmin);
        }
    }

    public function test_la_policy_rechaza_eliminar_al_primer_administrador_incluso_al_superadministrador(): void
    {
        $firstAdmin = $this->firstAdmin();
        $super = User::factory()->superAdmin()->create();
        $other = User::factory()->vendedor()->create();

        foreach ([$this->admin, $super] as $user) {
            $gate = \Illuminate\Support\Facades\Gate::forUser($user);

            $this->assertTrue($gate->denies('delete', $firstAdmin));
            $this->assertTrue($gate->denies('forceDelete', $firstAdmin));
            $this->assertTrue($gate->allows('update', $firstAdmin));
        }

        // El resto de los usuarios se sigue pudiendo eliminar.
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($super)->allows('delete', $other));
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($this->admin)->allows('delete', $other));
    }

    public function test_intentar_eliminar_al_primer_administrador_no_lo_borra(): void
    {
        $firstAdmin = $this->firstAdmin();
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(ManageUsers::class)
            ->callTableAction('delete', $firstAdmin);

        $this->assertNotSoftDeleted($firstAdmin);
    }

    /**
     * El usuario id 2 del setUp, solo con el rol Administrador (al crearlo,
     * el UserObserver le había asignado Vendedor).
     */
    private function firstAdmin(): User
    {
        $firstAdmin = User::findOrFail(User::FIRST_ADMIN_ID);
        $firstAdmin->syncRoles([Permissions::ROLE_ADMIN]);

        return $firstAdmin->fresh();
    }
}
