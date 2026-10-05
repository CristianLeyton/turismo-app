<?php

namespace App\Policies;

use App\Models\Clients;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view_any');
    }

    public function view(User $user, Clients $clients): bool
    {
        return $user->can('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('clients.create');
    }

    public function update(User $user, Clients $clients): bool
    {
        return $user->can('clients.update');
    }

    public function delete(User $user, Clients $clients): bool
    {
        return $user->can('clients.delete');
    }

    public function restore(User $user, Clients $clients): bool
    {
        return $user->can('clients.restore');
    }

    public function forceDelete(User $user, Clients $clients): bool
    {
        return $user->can('clients.force_delete');
    }
}
