<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Autorización estándar por módulo para recursos de configuración
 * (colectivos, rutas, horarios, asientos, etc.).
 *
 * Antes de la migración estos recursos estaban hardcodeados al usuario
 * id 1. Ahora se autorizan con los permisos `{modulo}.*` del catálogo, de
 * modo que se pueden otorgar desde la matriz de roles sin tocar código.
 */
trait AuthorizesModule
{
    /** Nombre del módulo en el catálogo (ej: 'buses', 'schedules'). */
    abstract protected static function permissionModule(): string;

    public function viewAny(User $user): bool
    {
        return $user->can(static::permissionModule().'.view_any');
    }

    public function view(User $user, $model): bool
    {
        return $user->can(static::permissionModule().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can(static::permissionModule().'.create');
    }

    public function update(User $user, $model): bool
    {
        return $user->can(static::permissionModule().'.update');
    }

    public function delete(User $user, $model): bool
    {
        return $user->can(static::permissionModule().'.delete');
    }

    public function restore(User $user, $model): bool
    {
        return $user->can(static::permissionModule().'.restore');
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->can(static::permissionModule().'.force_delete');
    }
}
