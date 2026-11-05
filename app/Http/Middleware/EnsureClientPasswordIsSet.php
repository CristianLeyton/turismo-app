<?php

namespace App\Http\Middleware;

use App\Support\ClientSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contraseña inicial = DNI: el cliente autenticado tiene que elegir la suya
 * antes de usar el panel. Corre después de `Authenticate` de Filament, que ya
 * resuelve la redirección al login cuando no hay sesión.
 */
class EnsureClientPasswordIsSet
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = ClientSession::current();

        if (
            $client !== null
            && $client->needsPasswordSetup()
            && $request->route()?->getName() !== 'filament.clientes.pages.clave'
        ) {
            return redirect()->route('filament.clientes.pages.clave');
        }

        return $next($request);
    }
}
