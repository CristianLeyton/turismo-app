<?php

namespace App\Observers;

use App\Models\User;
use App\Support\Permissions;

/**
 * Capa de compatibilidad entre el flag legacy `is_admin` y los roles.
 *
 * Dirección is_admin => roles: si algún código (scripts, tinker, código
 * viejo no migrado) crea o modifica usuarios seteando `is_admin`, el rol
 * correspondiente se ajusta para mantener el invariante:
 *
 *   is_admin = true  <=>  tiene el rol "Administrador" (o "Super Administrador")
 *
 * La dirección roles => is_admin vive en User::syncIsAdminFlag(), llamada
 * desde la UI cuando se cambian roles.
 */
class UserObserver
{
    public function saved(User $user): void
    {
        // Sólo reaccionar cuando el flag cambió (o en la creación).
        if (! $user->wasRecentlyCreated && ! $user->wasChanged('is_admin')) {
            return;
        }

        if ($user->is_admin) {
            if (! $user->hasRole(Permissions::ROLE_ADMIN)) {
                $user->assignRole(Permissions::ROLE_ADMIN);
            }

            if ($user->hasRole(Permissions::ROLE_SELLER)) {
                $user->removeRole(Permissions::ROLE_SELLER);
            }
        } else {
            $user->removeRole(Permissions::ROLE_ADMIN);

            // Un usuario sin roles sigue teniendo acceso de vendedor
            // (paridad con el sistema anterior, donde todo usuario del
            // panel podía vender boletos).
            if (! $user->roles()->exists()) {
                $user->assignRole(Permissions::ROLE_SELLER);
            }
        }
    }
}
