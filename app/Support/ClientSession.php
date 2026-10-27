<?php

namespace App\Support;

use App\Models\Clients;

/**
 * Identidad del cliente en el panel público (/clientes).
 *
 * ÚNICO punto donde se resuelve "quién está usando el panel de clientes":
 * todo el scoping del panel debe consultar acá y nunca confiar en IDs que
 * vengan por la URL o el formulario.
 *
 * Plan A: la sesión se escribe desde el formulario de identificación (DNI).
 * Plan B (futuro): un link firmado escribirá esta misma sesión; el resto del
 * panel no necesita saber cómo se llegó acá.
 */
final class ClientSession
{
    public const SESSION_KEY = 'client_panel_dni';

    /** Normaliza un DNI: sólo dígitos (acepta puntos/espacios del teclado). */
    public static function normalizeDni(?string $dni): string
    {
        return preg_replace('/\D+/', '', (string) $dni);
    }

    /**
     * Identifica al cliente por DNI y lo deja en la sesión.
     * Devuelve el cliente o null si el DNI no existe en el padron.
     */
    public static function identifyByDni(string $dni): ?Clients
    {
        $dni = self::normalizeDni($dni);

        if ($dni === '') {
            return null;
        }

        $client = Clients::query()
            ->where('dni', $dni)
            ->first();

        if ($client) {
            session([self::SESSION_KEY => $client->dni]);
        }

        return $client;
    }

    /**
     * Cliente autenticado en la sesión (o null). Si el cliente fue borrado
     * después de identificarse, la sesión queda inválida.
     */
    public static function current(): ?Clients
    {
        $dni = session(self::SESSION_KEY);

        if (! $dni) {
            return null;
        }

        return Clients::query()->where('dni', $dni)->first();
    }

    /** DNI identificado en la sesión (para scoping directo). */
    public static function dni(): ?string
    {
        $dni = session(self::SESSION_KEY);

        return filled($dni) ? Clients::query()->where('dni', $dni)->value('dni') : null;
    }

    public static function logout(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
