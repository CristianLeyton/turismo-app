<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view_any');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.create');
    }

    /**
     * NO habilita la edición genérica del boleto: los boletos son inmutables
     * por diseño (sólo se reprograman vía RescheduleTicket). No existe el
     * permiso `tickets.update` en el catálogo.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return false;
    }

    /**
     * Reprogramar un boleto (mover a otra fecha/horario del mismo origen→destino).
     */
    public function reschedule(User $user): bool
    {
        return $user->can('tickets.reschedule');
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.delete');
    }

    public function restore(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.restore');
    }

    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.force_delete');
    }
}
