<?php

namespace App\Http\Middleware;

use App\Support\ClientSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convierte "panel abierto" en "panel delimitado": cualquier ruta del panel
 * de clientes exige un cliente identificado en sesión, salvo la propia página
 * de identificación (y el action de salir).
 *
 * Funciona igual en el plan A (formulario de DNI) y en el plan B (link firmado),
 * porque ambos dejan la identidad en la misma sesión.
 */
class EnsureClientIdentified
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! $routeName || ! str_starts_with($routeName, 'filament.clientes.')) {
            return $next($request);
        }

        $publicRoutes = [
            'filament.clientes.pages.ingresar',
            'filament.clientes.pages.clave',
        ];

        if (ClientSession::current() === null) {
            if (in_array($routeName, $publicRoutes, true)) {
                return $next($request);
            }

            return redirect()->route('filament.clientes.pages.ingresar');
        }

        // Ya identificado: la página de ingreso no interesa, mandarlo a sus boletos.
        if ($routeName === 'filament.clientes.pages.ingresar') {
            return redirect()->route('filament.clientes.pages.my-tickets');
        }

        // Contraseña inicial = DNI: obligatorio cambiarla al primer ingreso.
        // Nada del panel queda accesible hasta que defina su propia clave.
        $client = ClientSession::current();

        if ($client !== null
            && $client->needsPasswordSetup()
            && $routeName !== 'filament.clientes.pages.clave') {
            return redirect()->route('filament.clientes.pages.clave');
        }

        return $next($request);
    }
}
