<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view_any');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /** Al primer administrador solo lo edita un administrador. */
    public function update(User $user, User $model): bool
    {
        if ($model->isFirstAdmin() && ! $user->isAdministrator()) {
            return false;
        }

        return $user->can('users.update');
    }

    /**
     * Al primer administrador no lo elimina nadie. Para el superadministrador
     * lo frena el Gate::before de AppServiceProvider, que si no lo dejaría pasar.
     */
    public function delete(User $user, User $model): bool
    {
        if ($model->isFirstAdmin()) {
            return false;
        }

        return $user->can('users.delete');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can('users.restore');
    }

    public function forceDelete(User $user, User $model): bool
    {
        if ($model->isFirstAdmin()) {
            return false;
        }

        return $user->can('users.force_delete');
    }
}
