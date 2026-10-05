<?php

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Semilla de roles y permisos + migración de datos de is_admin.
 *
 * Crea todos los permisos del catálogo y los tres roles del sistema, y
 * asigna a cada usuario existente su rol según el flag legacy is_admin:
 *
 *   - Usuario id 1:            Super Administrador (+ Administrador)
 *   - is_admin = true:         Administrador
 *   - resto:                   Vendedor
 *
 * Esto replica EXACTAMENTE los tres niveles de acceso que existían antes
 * de la migración (superusuario hardcodeado a id==1, admins y vendedores).
 *
 * La columna is_admin NO se elimina: queda sincronizada por el UserObserver
 * como red de seguridad para rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            // La tabla de roles debería existir (migración de spatie anterior),
            // pero si alguien corre las migraciones en otro orden fallaríamos
            // con un mensaje claro en vez de un error de SQL críptico.
            throw new RuntimeException(
                'Falta la tabla "roles": corré primero la migración de spatie/laravel-permission.'
            );
        }

        Permissions::syncToDatabase();

        $super = \Spatie\Permission\Models\Role::findByName(Permissions::ROLE_SUPER, 'web');
        $admin = \Spatie\Permission\Models\Role::findByName(Permissions::ROLE_ADMIN, 'web');
        $seller = \Spatie\Permission\Models\Role::findByName(Permissions::ROLE_SELLER, 'web');

        User::withTrashed()
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($super, $admin, $seller): void {
                foreach ($users as $user) {
                    if ((int) $user->id === 1) {
                        // Superusuario original: bypass total vía Gate::before.
                        $user->syncRoles([$super, $admin]);
                        $user->forceFill(['is_admin' => true])->saveQuietly();

                        continue;
                    }

                    if ($user->is_admin) {
                        $user->syncRoles([$admin]);
                    } else {
                        $user->syncRoles([$seller]);
                    }
                }
            });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Los usuarios vuelven a quedar definidos sólo por is_admin.
        User::withTrashed()->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                $user->roles()->detach();
            }
        });

        // Los roles/permisos quedan (idempotente y sin datos de usuarios);
        // un rollback completo de spatie es responsabilidad de su propia
        // migración de tablas.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
