<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Con el parámetro "Panel de clientes activo" apagado, el panel /clientes
 * (ingreso incluido) y sus rutas sueltas no existen: 404. Registrado como
 * middleware persistente del panel, también corta las peticiones Livewire
 * de una página que quedó abierta.
 */
class EnsureClientPanelIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Setting::panelClientesActivo(), 404);

        return $next($request);
    }
}
