<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogo central de roles y permisos.
 *
 * Es la ÚNICA fuente de verdad del sistema de autorización:
 *  - La migración de datos lo usa para crear roles/permisos.
 *  - El comando `php artisan permissions:sync` lo usa para aplicar novedades.
 *  - La matriz de UI de RoleResource lo usa para renderizar checkboxes.
 *  - Las policies y los checks de UI consultan permisos con estos nombres.
 *
 * Formato de nombre de permiso: `{modulo}.{accion}` (ej: `tickets.create`).
 *
 * Para agregar un permiso nuevo: agregalo acá y corré `php artisan permissions:sync`.
 * Para agregar un módulo nuevo: agregá la entrada al catálogo con sus acciones.
 */
final class Permissions
{
    /** Roles del sistema (no se borran desde la UI). */
    public const ROLE_SUPER = 'Super Administrador';

    public const ROLE_ADMIN = 'Administrador';

    public const ROLE_SELLER = 'Vendedor';

    /** @return list<string> */
    public static function systemRoles(): array
    {
        return [self::ROLE_SUPER, self::ROLE_ADMIN, self::ROLE_SELLER];
    }

    /**
     * Catálogo de módulos: nombre del módulo => [label, acciones].
     * Cada acción es `accion => label humano`.
     *
     * @return array<string, array{label: string, actions: array<string, string>}>
     */
    public static function catalog(): array
    {
        return [
            'tickets' => [
                'label' => 'Boletos',
                'actions' => [
                    'view_any' => 'Ver listado',
                    'view' => 'Ver detalle',
                    'create' => 'Vender / crear',
                    'reschedule' => 'Reprogramar',
                    'delete' => 'Eliminar',
                    'restore' => 'Restaurar',
                    'force_delete' => 'Eliminar definitivamente',
                ],
            ],
            'clients' => [
                'label' => 'Clientes',
                'actions' => [
                    'view_any' => 'Ver listado',
                    'view' => 'Ver detalle',
                    'create' => 'Crear',
                    'update' => 'Editar',
                    'ban' => 'Banear / habilitar',
                    'delete' => 'Eliminar',
                    'restore' => 'Restaurar',
                    'force_delete' => 'Eliminar definitivamente',
                ],
            ],
            'sales' => [
                'label' => 'Ventas',
                'actions' => self::standardActions(),
            ],
            'trips' => [
                'label' => 'Viajes',
                'actions' => self::standardActions(),
            ],
            'payments' => [
                'label' => 'Pagos',
                'actions' => self::standardActions(),
            ],
            'payment_methods' => [
                'label' => 'Métodos de pago',
                'actions' => self::standardActions(),
            ],
            'users' => [
                'label' => 'Usuarios',
                'actions' => self::standardActions(),
            ],
            'roles' => [
                'label' => 'Roles y permisos',
                'actions' => [
                    'view_any' => 'Ver listado',
                    'view' => 'Ver detalle',
                    'create' => 'Crear roles',
                    'update' => 'Editar roles',
                    'delete' => 'Eliminar roles',
                    'assign' => 'Asignar roles a usuarios',
                ],
            ],
            'ticket_audit' => [
                'label' => 'Historial de boletos',
                'actions' => [
                    'view_any' => 'Ver historial',
                    'view' => 'Ver detalle',
                ],
            ],
            'settings' => [
                'label' => 'Configuración',
                'actions' => [
                    'manage' => 'Administrar configuración',
                ],
            ],
            'buses' => [
                'label' => 'Colectivos',
                'actions' => self::standardActions(),
            ],
            'bus_layout_areas' => [
                'label' => 'Distribuciones de asientos',
                'actions' => self::standardActions(),
            ],
            'locations' => [
                'label' => 'Ubicaciones',
                'actions' => self::standardActions(),
            ],
            'routes' => [
                'label' => 'Rutas',
                'actions' => self::standardActions(),
            ],
            'route_stops' => [
                'label' => 'Paradas',
                'actions' => self::standardActions(),
            ],
            'schedules' => [
                'label' => 'Horarios',
                'actions' => self::standardActions(),
            ],
            'seats' => [
                'label' => 'Asientos',
                'actions' => self::standardActions(),
            ],
        ];
    }

    /** Acciones estándar CRUD para recursos de Filament. @return array<string, string> */
    private static function standardActions(): array
    {
        return [
            'view_any' => 'Ver listado',
            'view' => 'Ver detalle',
            'create' => 'Crear',
            'update' => 'Editar',
            'delete' => 'Eliminar',
            'restore' => 'Restaurar',
            'force_delete' => 'Eliminar definitivamente',
        ];
    }

    /** Lista plana de todos los nombres de permisos del catálogo. @return list<string> */
    public static function all(): array
    {
        $names = [];

        foreach (self::catalog() as $module => $config) {
            foreach (array_keys($config['actions']) as $action) {
                $names[] = self::permissionName($module, $action);
            }
        }

        return $names;
    }

    /** Nombre completo de un permiso: `modulo.accion`. */
    public static function permissionName(string $module, string $action): string
    {
        return "{$module}.{$action}";
    }

    /**
     * Permisos por defecto de cada rol del sistema (paridad con el sistema
     * anterior a la migración, que tenía tres niveles: superusuario id==1,
     * administradores (is_admin) y vendedores).
     *
     * - Super Administrador: bypass completo vía Gate::before (además recibe
     *   todos los permisos del catálogo por prolijidad).
     * - Administrador: lo que hoy ve un usuario is_admin. NOTA: los módulos
     *   de configuración (colectivos, rutas, horarios, etc.) estaban
     *   hardcodeados al usuario id==1, por eso no están acá: se pueden
     *   habilitar desde la matriz de la UI cuando se necesite.
     * - Vendedor: lo que hoy ve cualquier usuario del panel sin ser admin.
     *
     * @return list<string>
     */
    public static function defaultsFor(string $roleName): array
    {
        return match ($roleName) {
            self::ROLE_SUPER => self::all(),
            self::ROLE_ADMIN => [
                // Boletos: ver, vender, reprogramar, eliminar. NO editar
                // genéricamente (los boletos son inmutables por diseño).
                'tickets.view_any', 'tickets.view', 'tickets.create', 'tickets.reschedule',
                'tickets.delete', 'tickets.restore', 'tickets.force_delete',
                // Clientes: acceso completo (incluido banear).
                'clients.view_any', 'clients.view', 'clients.create', 'clients.update',
                'clients.ban', 'clients.delete', 'clients.restore', 'clients.force_delete',
                // Ventas y viajes (hoy sin policy: todos accedían).
                ...array_map(fn (string $a) => "sales.{$a}", array_keys(self::standardActions())),
                ...array_map(fn (string $a) => "trips.{$a}", array_keys(self::standardActions())),
                // Pagos (force_delete era exclusivo del superusuario id==1) y
                // métodos de pago (acceso completo para admins, como antes).
                ...array_map(
                    fn (string $a) => "payments.{$a}",
                    array_diff(array_keys(self::standardActions()), ['force_delete'])
                ),
                ...array_map(fn (string $a) => "payment_methods.{$a}", array_keys(self::standardActions())),
                // Usuarios y roles.
                ...array_map(fn (string $a) => "users.{$a}", array_keys(self::standardActions())),
                'roles.view_any', 'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign',
                // Historial y configuración.
                'ticket_audit.view_any', 'ticket_audit.view',
                'settings.manage',
            ],
            self::ROLE_SELLER => [
                // Boletos: ver y vender (no reprogramar ni eliminar).
                'tickets.view_any', 'tickets.view', 'tickets.create',
                // Clientes: ver, crear y editar (no eliminar ni banear).
                'clients.view_any', 'clients.view', 'clients.create', 'clients.update',
                // Ventas y viajes (hoy sin policy: todos accedían).
                ...array_map(fn (string $a) => "sales.{$a}", array_keys(self::standardActions())),
                ...array_map(fn (string $a) => "trips.{$a}", array_keys(self::standardActions())),
            ],
            default => [],
        };
    }

    /**
     * Crea/actualiza en la base de datos todos los permisos del catálogo y
     * los tres roles del sistema con sus permisos por defecto.
     *
     * Idempotente: seguro de correr las veces que haga falta.
     * Nunca revoca permisos de roles custom; para los roles del sistema
     * re-aplica los defaults del catálogo (que sólo crecen).
     */
    public static function syncToDatabase(): void
    {
        foreach (self::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        foreach ([self::ROLE_SUPER, self::ROLE_ADMIN, self::ROLE_SELLER] as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(self::defaultsFor($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Opciones agrupadas para la matriz de la UI de roles:
     * [ "Módulo" => [ "modulo.accion" => "Label acción", ... ] ]
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        $grouped = [];

        foreach (self::catalog() as $module => $config) {
            $grouped[$config['label']] = collect($config['actions'])
                ->mapWithKeys(fn (string $label, string $action) => [
                    self::permissionName($module, $action) => $label,
                ])
                ->all();
        }

        return $grouped;
    }

    /** Recalcula el flag is_admin de todos los usuarios a partir de sus roles. */
    public static function reconcileUsers(): void
    {
        User::withTrashed()
            ->with('roles')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $user->syncIsAdminFlag();
                }
            });
    }
}
