<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    /**
     * Los métodos de pago son configuración global del sistema:
     * solo los administradores pueden verlos o gestionarlos.
     */
    public function before(User $user, string $ability): bool|null
    {
        if (! (bool) $user->is_admin) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return (bool) $user->is_admin;
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return (bool) $user->is_admin;
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_admin;
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return (bool) $user->is_admin;
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return (bool) $user->is_admin;
    }

    public function restore(User $user, PaymentMethod $paymentMethod): bool
    {
        return (bool) $user->is_admin;
    }

    public function forceDelete(User $user, PaymentMethod $paymentMethod): bool
    {
        return (bool) $user->is_admin;
    }
}
