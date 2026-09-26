<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración simple clave/valor del sistema (sin dependencias externas).
 *
 * Uso:
 *   Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE)
 *   Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1')
 *
 * La caché vive por request (array estático) para evitar queries repetidas
 * dentro de la misma request; set() y flushCache() la limpian al escribir.
 */
class Setting extends Model
{
    /**
     * Pedir la contraseña del admin logueado antes de borrar un boleto
     * (DeleteAction / ForceDeleteAction en la vista del boleto).
     */
    public const REQUIRE_PASSWORD_TICKET_DELETE = 'tickets.require_password_delete';

    /**
     * Pedir la contraseña del admin logueado antes de confirmar una
     * reprogramación de boleto.
     */
    public const REQUIRE_PASSWORD_TICKET_RESCHEDULE = 'tickets.require_password_reschedule';

    protected $fillable = ['key', 'value'];

    /**
     * Valor booleano de una clave ('1'/'true' son true; clave inexistente →
     * devuelve el default).
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        if (! array_key_exists($key, static::$cache)) {
            $value = static::query()->where('key', $key)->value('value');

            // null = la clave no existe en la tabla.
            static::$cache[$key] = $value === null ? null : (string) $value;
        }

        $value = static::$cache[$key];

        if ($value === null) {
            return $default;
        }

        return in_array($value, ['1', 'true'], true);
    }

    /**
     * Guarda una clave y limpia la caché del request.
     */
    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        unset(static::$cache[$key]);
    }

    /**
     * Limpia la caché por request (útil en tests y tras escrituras externas).
     */
    public static function flushCache(): void
    {
        static::$cache = [];
    }

    /** @var array<string, string|null> */
    protected static array $cache = [];
}
