<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    // ====================== Paridad del rol Vendedor ======================

    public function test_vendedor_puede_vender_y_ver_clientes_ventas_y_viajes(): void
    {
        $vendedor = User::factory()->vendedor()->create();

        $this->actingAs($vendedor);

        // Paridad: lo que un usuario sin is_admin podía hacer antes.
        $this->assertTrue($vendedor->can('tickets.view_any'));
        $this->assertTrue($vendedor->can('tickets.view'));
        $this->assertTrue($vendedor->can('tickets.create'));
        $this->assertTrue($vendedor->can('clients.view_any'));
        $this->assertTrue($vendedor->can('clients.view'));
        $this->assertTrue($vendedor->can('clients.create'));
        $this->assertTrue($vendedor->can('clients.update'));
        $this->assertTrue($vendedor->can('sales.view_any'));
        $this->assertTrue($vendedor->can('trips.view_any'));
        $this->assertTrue($vendedor->can('trips.create'));
    }

    public function test_vendedor_no_puede_acceder_a_gestion(): void
    {
        $vendedor = User::factory()->vendedor()->create();

        $this->actingAs($vendedor);

        // Paridad: lo que un usuario sin is_admin NUNCA pudo hacer.
        $this->assertFalse($vendedor->can('tickets.reschedule'));
        $this->assertFalse($vendedor->can('tickets.delete'));
        $this->assertFalse($vendedor->can('clients.delete'));
        $this->assertFalse($vendedor->can('clients.ban'));
        $this->assertFalse($vendedor->can('payments.view_any'));
        $this->assertFalse($vendedor->can('payment_methods.create'));
        $this->assertFalse($vendedor->can('users.view_any'));
        $this->assertFalse($vendedor->can('roles.view_any'));
        $this->assertFalse($vendedor->can('ticket_audit.view_any'));
        $this->assertFalse($vendedor->can('settings.manage'));
        $this->assertFalse($vendedor->can('buses.view_any'));
    }

    // ====================== Paridad del rol Administrador ======================

    public function test_administrador_tiene_los_permisos_que_tenia_is_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('users.view_any'));
        $this->assertTrue($admin->can('users.delete'));
        $this->assertTrue($admin->can('payments.view_any'));
        $this->assertTrue($admin->can('payment_methods.create'));
        $this->assertTrue($admin->can('ticket_audit.view_any'));
        $this->assertTrue($admin->can('settings.manage'));
        $this->assertTrue($admin->can('tickets.reschedule'));
        $this->assertTrue($admin->can('tickets.delete'));
        $this->assertTrue($admin->can('clients.delete'));
        $this->assertTrue($admin->can('clients.ban'));
        $this->assertTrue($admin->can('roles.view_any'));
        $this->assertTrue($admin->can('roles.assign'));
    }

    public function test_administrador_no_accede_a_modulos_de_configuracion_como_antes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        // Antes: hardcodeado a $user->id == 1 (BusPolicy y similares).
        $this->assertFalse($admin->can('buses.view_any'));
        $this->assertFalse($admin->can('locations.view_any'));
        $this->assertFalse($admin->can('routes.view_any'));
        $this->assertFalse($admin->can('schedules.view_any'));
        $this->assertFalse($admin->can('seats.view_any'));

        // Antes: PaymentPolicy::forceDelete era sólo para id == 1.
        $this->assertFalse($admin->can('payments.force_delete'));
    }

    public function test_los_boletos_siguen_siendo_inmutables_para_el_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        // TicketPolicy::update sigue devolviendo false (sólo reprogramar).
        $this->assertFalse($admin->can('update', new Ticket));
    }

    // ====================== Super Administrador ======================

    public function test_super_administrador_hace_bypass_de_todo(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super);

        // Módulos de configuración que antes eran sólo para id == 1.
        $this->assertTrue($super->can('buses.view_any'));
        $this->assertTrue($super->can('schedules.delete'));
        $this->assertTrue($super->can('payments.force_delete'));
        $this->assertTrue($super->can('users.view_any'));
        $this->assertTrue($super->can('roles.manage_anything_nonexistent'));
        // Bypass: hasta habilidades inexistentes pasan.
        $this->assertTrue($super->can('cualquier.cosa.inexistente'));
    }

    // ====================== Sincronización is_admin <=> roles ======================

    public function test_crear_usuario_con_is_admin_true_asigna_rol_administrador(): void
    {
        // Ruta legacy (compatibilidad con código/scripts viejos y tests).
        $user = User::create([
            'name' => 'Legacy',
            'email' => 'legacy@test.local',
            'username' => 'legacy',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $this->assertTrue($user->hasRole(Permissions::ROLE_ADMIN));
        $this->assertFalse($user->hasRole(Permissions::ROLE_SELLER));
    }

    public function test_crear_usuario_sin_is_admin_asigna_rol_vendedor(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->hasRole(Permissions::ROLE_SELLER));
        $this->assertTrue($user->hasRole(Permissions::ROLE_ADMIN) === false);
        $this->assertFalse((bool) $user->is_admin);
    }

    public function test_sync_is_admin_flag_refleja_los_roles(): void
    {
        $user = User::factory()->create();

        $user->assignRole(Permissions::ROLE_ADMIN);
        $user->syncIsAdminFlag();
        $this->assertTrue((bool) $user->fresh()->is_admin);

        $user->removeRole(Permissions::ROLE_ADMIN);
        $user->syncIsAdminFlag();
        $this->assertFalse((bool) $user->fresh()->is_admin);
    }

    // ====================== Catálogo y sincronización ======================

    public function test_el_catalogo_se_sincroniza_de_forma_idempotente(): void
    {
        $expected = count(Permissions::all());

        Permissions::syncToDatabase();
        Permissions::syncToDatabase();

        $this->assertSame($expected, \Spatie\Permission\Models\Permission::count());
        $this->assertSame(3, Role::whereIn('name', Permissions::systemRoles())->count());
    }

    public function test_el_sync_no_revoca_permisos_habilitados_desde_la_matriz(): void
    {
        $admin = Role::findByName(Permissions::ROLE_ADMIN);

        // Los permisos "por bandera" no vienen por defecto: se otorgan a mano.
        $this->assertFalse($admin->hasPermissionTo('tickets.vender_pasado_limite'));

        $admin->givePermissionTo('tickets.vender_pasado_limite');

        Permissions::syncToDatabase();
        \Illuminate\Support\Facades\Artisan::call('permissions:sync');

        // Sigue vigente después de sincronizar el catálogo.
        $this->assertTrue(
            Role::findByName(Permissions::ROLE_ADMIN)->hasPermissionTo('tickets.vender_pasado_limite'),
        );
    }

    public function test_comando_permissions_sync_funciona(): void
    {
        // Borrar un permiso y verificar que el comando lo recrea.
        \Spatie\Permission\Models\Permission::where('name', 'tickets.reschedule')->delete();

        Artisan::call('permissions:sync');

        $this->assertDatabaseHas('permissions', ['name' => 'tickets.reschedule']);
    }

    public function test_roles_custom_pueden_tener_permisos_parciales(): void
    {
        $role = Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        $role->syncPermissions(['tickets.view_any', 'tickets.reschedule', 'ticket_audit.view_any']);

        $supervisor = User::factory()->create();
        $supervisor->assignRole($role);

        $this->actingAs($supervisor);

        $this->assertTrue($supervisor->can('tickets.reschedule'));
        $this->assertTrue($supervisor->can('ticket_audit.view_any'));
        $this->assertFalse($supervisor->can('tickets.delete'));
        $this->assertFalse($supervisor->can('users.view_any'));
    }

    public function test_roles_del_sistema_no_se_pueden_borrar_desde_la_ui(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $resource = \App\Filament\Resources\Roles\RoleResource::class;

        $this->assertFalse($resource::canDelete(Role::findByName(Permissions::ROLE_SUPER)));
        $this->assertFalse($resource::canDelete(Role::findByName(Permissions::ROLE_ADMIN)));
        $this->assertFalse($resource::canDelete(Role::findByName(Permissions::ROLE_SELLER)));

        $custom = Role::create(['name' => 'Temporal', 'guard_name' => 'web']);
        $this->assertTrue($resource::canDelete($custom));
    }

    public function test_no_se_puede_eliminar_un_rol_con_usuarios_asignados(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $role = Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        $role->syncPermissions(['tickets.view_any']);

        $miembro = User::factory()->create();
        $miembro->assignRole($role);

        Livewire::test(\App\Filament\Resources\Roles\Pages\ManageRoles::class)
            ->assertOk()
            ->callTableAction('delete', $role)
            ->assertNotified('No se puede eliminar el rol');

        // El rol sobrevive y el usuario conserva su rol y sus permisos.
        $this->assertDatabaseHas('roles', ['name' => 'Supervisor']);
        $this->assertTrue($miembro->fresh()->hasRole('Supervisor'));
        $this->assertTrue($miembro->fresh()->can('tickets.view_any'));
    }

    public function test_un_rol_sin_usuarios_si_se_puede_eliminar(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $role = Role::create(['name' => 'Rol Abandonado', 'guard_name' => 'web']);

        Livewire::test(\App\Filament\Resources\Roles\Pages\ManageRoles::class)
            ->assertOk()
            ->callTableAction('delete', $role)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('roles', ['name' => 'Rol Abandonado']);
    }

    // ====================== Gestión de roles (UI) ======================

    public function test_solo_quien_tiene_roles_view_any_accede_al_recurso(): void
    {
        $admin = User::factory()->admin()->create();
        $vendedor = User::factory()->vendedor()->create();

        $this->actingAs($admin);
        $this->assertTrue(\App\Filament\Resources\Roles\RoleResource::canViewAny());

        $this->actingAs($vendedor);
        $this->assertFalse(\App\Filament\Resources\Roles\RoleResource::canViewAny());
    }

    public function test_el_campo_roles_del_formulario_de_usuarios_exige_roles_assign(): void
    {
        $admin = User::factory()->admin()->create();
        $vendedor = User::factory()->vendedor()->create();

        $this->actingAs($admin);
        $this->assertTrue((bool) auth()->user()->can('roles.assign'));

        $this->actingAs($vendedor);
        $this->assertFalse((bool) auth()->user()->can('roles.assign'));
    }

    // ====================== UI de roles (Filament) ======================

    public function test_la_pagina_de_roles_renderiza_para_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Resources\Roles\Pages\ManageRoles::class)
            ->assertOk()
            ->assertSee('Administrador')
            ->assertSee('Vendedor');
    }

    public function test_la_matriz_de_permisos_muestra_los_modulos_del_catalogo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        // Inspeccionar el schema del action montado: la matriz es un
        // CheckboxList por módulo, con los permisos {modulo}.{accion}.
        $component = Livewire::test(\App\Filament\Resources\Roles\Pages\ManageRoles::class)
            ->assertOk()
            ->mountAction('create');

        $mountedAction = $component->instance()->getMountedAction();
        $schema = $mountedAction->getSchema(\Filament\Schemas\Schema::make($component->instance()));

        $checkboxLists = collect($schema->getComponents())
            ->flatMap(fn ($c) => method_exists($c, 'getChildComponents') ? $c->getChildComponents() : [$c])
            ->filter(fn ($c) => $c instanceof \Filament\Forms\Components\CheckboxList);

        $labels = $checkboxLists->map(fn ($list) => $list->getLabel())->values()->all();

        $this->assertContains('Boletos', $labels);
        $this->assertContains('Clientes', $labels);
        $this->assertContains('Pagos', $labels);
        $this->assertContains('Usuarios', $labels);
        $this->assertContains('Configuración', $labels);

        $ticketOptions = $checkboxLists
            ->first(fn ($list) => $list->getLabel() === 'Boletos')
            ?->getOptions() ?? [];

        $this->assertArrayHasKey('tickets.reschedule', $ticketOptions);
        $this->assertArrayHasKey('tickets.create', $ticketOptions);
    }

    public function test_crear_un_rol_desde_la_ui_persiste_sus_permisos(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Resources\Roles\Pages\ManageRoles::class)
            ->assertOk()
            ->callAction('create', data: [
                'name' => 'Supervisor',
                'permissions' => [
                    'tickets' => ['tickets.view_any', 'tickets.reschedule'],
                ],
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('roles', ['name' => 'Supervisor']);

        $role = Role::findByName('Supervisor');
        $permissionNames = $role->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame(['tickets.reschedule', 'tickets.view_any'], $permissionNames);
    }

    public function test_editar_un_rol_precarga_el_nombre_y_persiste_cambios(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $role = Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        $role->syncPermissions(['tickets.view_any', 'tickets.reschedule']);

        // 1) Al montar el edit action, el nombre debe venir precargado
        // (regresión: fillForm reemplazaba el fill por defecto del action).
        Livewire::test(\App\Filament\Resources\Roles\Pages\ManageRoles::class)
            ->assertOk()
            ->mountTableAction('edit', $role)
            ->assertTableActionDataSet(['name' => 'Supervisor']);

        // 2) Renombrar y AGREGAR un permiso persiste ambos cambios.
        Livewire::test(\App\Filament\Resources\Roles\Pages\ManageRoles::class)
            ->assertOk()
            ->callTableAction('edit', $role, data: [
                'name' => 'Supervisor Renombrado',
                'permissions' => [
                    'tickets' => ['tickets.view_any', 'tickets.reschedule', 'tickets.delete'],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $fresh = $role->fresh();

        $this->assertSame('Supervisor Renombrado', $fresh->name);
        $this->assertEqualsCanonicalizing(
            ['tickets.view_any', 'tickets.reschedule', 'tickets.delete'],
            $fresh->permissions->pluck('name')->all(),
        );

        // 3) DESMARCAR un permiso (flujo real de la UI: el checkbox list
        // reemplaza el array completo). El helper fillForm setea índice por
        // índice y no puede expresar remociones, por eso seteamos el estado
        // del action montado directamente.
        $component = Livewire::test(\App\Filament\Resources\Roles\Pages\ManageRoles::class)
            ->assertOk()
            ->mountTableAction('edit', $role);

        $component->set('mountedActions.0.data.permissions.tickets', ['tickets.view_any']);
        $component->callMountedTableAction();

        $this->assertSame(['tickets.view_any'], $role->fresh()->permissions->pluck('name')->all());
    }

    // ====================== Mapeo resource -> módulo ======================

    public function test_cada_modelo_resuelve_su_policy_de_modulo(): void
    {
        // Filament autoriza cada resource con la policy de su MODEL. Si el
        // naming no matchea (ej. modelo Clients vs policy ClientPolicy) la
        // policy se pierde y la página queda abierta o exige el módulo
        // equivocado. Este test pilla cualquier desalineación futura.
        $expected = [
            \App\Models\Bus::class => \App\Policies\BusPolicy::class,
            \App\Models\BusLayoutArea::class => \App\Policies\BusLayoutAreaPolicy::class,
            \App\Models\Clients::class => \App\Policies\ClientPolicy::class,
            \App\Models\Location::class => \App\Policies\LocationPolicy::class,
            \App\Models\Payment::class => \App\Policies\PaymentPolicy::class,
            \App\Models\PaymentMethod::class => \App\Policies\PaymentMethodPolicy::class,
            \App\Models\Route::class => \App\Policies\RoutePolicy::class,
            \App\Models\RouteStop::class => \App\Policies\RouteStopPolicy::class,
            \App\Models\Sale::class => \App\Policies\SalePolicy::class,
            \App\Models\Schedule::class => \App\Policies\SchedulePolicy::class,
            \App\Models\Seat::class => \App\Policies\SeatPolicy::class,
            \App\Models\Ticket::class => \App\Policies\TicketPolicy::class,
            \App\Models\Trip::class => \App\Policies\TripPolicy::class,
            \App\Models\User::class => \App\Policies\UserPolicy::class,
        ];

        foreach ($expected as $model => $policy) {
            $resolved = Gate::getPolicyFor($model);
            $resolved = is_object($resolved) ? $resolved::class : $resolved;

            $this->assertSame($policy, $resolved, "El modelo {$model} debe resolver la policy {$policy}.");
        }
    }

    public function test_la_pagina_de_ventas_se_autoriza_con_sales_y_no_con_users(): void
    {
        // Regresión: el resource de Ventas lista vendedores (model User), así
        // que Filament resolvía UserPolicy y la página exigía users.view_any:
        // un rol con permisos de ventas no podía entrar.
        $salesRole = Role::create(['name' => 'Solo Ventas', 'guard_name' => 'web']);
        $salesRole->syncPermissions(
            collect(Permissions::catalog()['sales']['actions'])
                ->keys()
                ->map(fn (string $action) => Permissions::permissionName('sales', $action))
                ->all()
        );

        $vendedorVentas = User::factory()->create();
        $vendedorVentas->syncRoles($salesRole);

        $this->actingAs($vendedorVentas);

        $this->assertTrue(\App\Filament\Resources\Sales\SalesResource::canViewAny());

        Livewire::test(\App\Filament\Resources\Sales\Pages\ManageSales::class)
            ->assertOk();

        // Inverso: tener users.* no habilita la página de Ventas.
        $usersRole = Role::create(['name' => 'Solo Usuarios', 'guard_name' => 'web']);
        $usersRole->syncPermissions(['users.view_any']);

        $otro = User::factory()->create();
        $otro->syncRoles($usersRole);

        $this->actingAs($otro);

        $this->assertFalse(\App\Filament\Resources\Sales\SalesResource::canViewAny());
    }

    public function test_registrar_pago_desde_ventas_exige_payments_create(): void
    {
        $record = User::factory()->create();

        $salesRole = Role::create(['name' => 'Solo Ventas', 'guard_name' => 'web']);
        $salesRole->syncPermissions(['sales.view_any', 'sales.view']);

        $vendedorVentas = User::factory()->create();
        $vendedorVentas->syncRoles($salesRole);

        $this->actingAs($vendedorVentas);

        // Sin payments.create el botón no aparece (antes cualquier usuario
        // podía registrar pagos desde la página de Ventas).
        Livewire::test(\App\Filament\Resources\Sales\Pages\ManageSales::class)
            ->assertOk()
            ->assertTableActionVisible('view_tickets', $record)
            ->assertTableActionHidden('register_payment', $record);

        $salesRole->givePermissionTo('payments.create');
        $vendedorVentas->refresh();

        Livewire::test(\App\Filament\Resources\Sales\Pages\ManageSales::class)
            ->assertOk()
            ->assertTableActionVisible('register_payment', $record);
    }

    public function test_la_pagina_de_clientes_se_autoriza_con_clients(): void
    {
        // Regresión: el modelo se llama Clients (plural) y la policy
        // ClientPolicy nunca se autodescubría: la página quedaba abierta
        // para cualquier usuario del panel, sin importar sus permisos.
        $usersRole = Role::create(['name' => 'Solo Usuarios', 'guard_name' => 'web']);
        $usersRole->syncPermissions(['users.view_any']);

        $user = User::factory()->create();
        $user->syncRoles($usersRole);

        $this->actingAs($user);

        $this->assertFalse(\App\Filament\Resources\Clients\ClientsResource::canViewAny());

        $clientsRole = Role::create(['name' => 'Solo Clientes', 'guard_name' => 'web']);
        $clientsRole->syncPermissions(['clients.view_any']);

        $otro = User::factory()->create();
        $otro->syncRoles($clientsRole);

        $this->actingAs($otro);

        $this->assertTrue(\App\Filament\Resources\Clients\ClientsResource::canViewAny());
    }
}
