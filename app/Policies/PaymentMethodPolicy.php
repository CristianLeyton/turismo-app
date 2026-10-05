<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    /**
     * Los métodos de pago son configuración global del sistema:
     * solo quienes tengan el permiso pueden verlos o gestionarlos.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('payment_methods.view_any');
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('payment_methods.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payment_methods.create');
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('payment_methods.update');
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('payment_methods.delete');
    }

    public function restore(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('payment_methods.restore');
    }

    public function forceDelete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can('payment_methods.force_delete');
    }
}
