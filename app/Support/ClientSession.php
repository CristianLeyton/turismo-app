<?php

namespace App\Support;

use App\Models\Clients;
use Filament\Facades\Filament;
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

    public const PANEL_ID = 'clientes';

    /** Normaliza un DNI: sólo dígitos (acepta puntos/espacios del teclado). */
    public static function normalizeDni(?string $dni): string
    {
        return preg_replace('/\D+/', '', (string) $dni);
    }

    /**
     * URL prevista para llevar al cliente tras el ingreso: sólo si es una
     * página propia del panel de clientes. La sesión es compartida con el
     * panel de administración y Laravel guarda una sola URL "intended"
     * (`url.intended`): si quien la guardó estaba pasando por la zona
     * admin sin sesión, no debe secuestrar el ingreso del cliente.
     */
    public static function intendedLoginUrl(string $default): string
    {
        $intended = session()->pull('url.intended');

        return filled($intended) && self::isPanelUrl($intended) ? $intended : $default;
    }

    /**
     * ¿La URL apunta a una página del panel de clientes? Compara por el path
     * (ignora dominio y query) y toma el prefijo del panel configurado, no
     * un literal. También decide para URLs relativas.
     */
    public static function isPanelUrl(string $url): bool
    {
        $prefix = '/'.trim(Filament::getPanel(self::PANEL_ID)->getPath() ?: '', '/');

        if ($prefix === '/') {
            return true;
        }

        $path = '/'.trim((string) (parse_url($url, PHP_URL_PATH) ?: '/'), '/');

        return $path === $prefix || str_starts_with($path, "{$prefix}/");
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
