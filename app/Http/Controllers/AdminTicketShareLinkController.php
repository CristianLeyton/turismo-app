<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * "Ver link" del detalle del boleto: el token público se genera recién al
 * hacer click, no al abrir el detalle. Un boleto eliminado no tiene link.
 */
class AdminTicketShareLinkController extends Controller
{
    public function __invoke(Ticket $ticket): RedirectResponse
    {
        Gate::authorize('view', $ticket);

        return redirect()->away($ticket->shareUrl());
    }
}
