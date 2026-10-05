<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view_any');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('payments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->can('payments.update') && $payment->deleted_at === null;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->can('payments.delete');
    }

    public function restore(User $user, Payment $payment): bool
    {
        return $user->can('payments.restore');
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return $user->can('payments.force_delete');
    }
}
