<?php

namespace App\Support;

use App\Models\Clients;
use Illuminate\Support\Facades\Auth;

/**
 * Identidad del cliente en el panel público (/clientes).
 *
 * ÚNICO punto donde se resuelve "quién está usando el panel de clientes":
 * todo el scoping del panel debe consultar acá y nunca confiar en IDs que
 * vengan por la URL o el formulario.
 *
 * El cliente se autentica con el login de Filament sobre el guard `client`
 * (tabla clients); esta clase es una fachada sobre ese guard.
 */
final class ClientSession
{
    public const GUARD = 'client';

    /** Normaliza un DNI: sólo dígitos (acepta puntos/espacios del teclado). */
    public static function normalizeDni(?string $dni): string
    {
        return preg_replace('/\D+/', '', (string) $dni);
    }

    /** Cliente autenticado en el guard `client` (o null). */
    public static function current(): ?Clients
    {
        $client = Auth::guard(self::GUARD)->user();

        return $client instanceof Clients ? $client : null;
    }

    /** DNI del cliente autenticado (para scoping directo). */
    public static function dni(): ?string
    {
        return self::current()?->dni;
    }

    /**
     * Cierra sólo la sesión del cliente. No invalida la sesión de Laravel
     * (como hace el logout de Filament) para no cerrar una sesión del panel
     * de administración abierta en el mismo navegador.
     */
    public static function logout(): void
    {
        Auth::guard(self::GUARD)->logout();

        session()->regenerateToken();
    }
}
