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

    /**
     * Horas de gracia para vender un boleto cuyo colectivo YA SALIÓ.
     * Pasado ese plazo sólo puede vender quien tenga el permiso
     * `tickets.vender_pasado_limite`. 0 = sin límite.
     */
    public const VENTA_LIMITE_HORAS = 'tickets.venta_limite_horas';

    public const VENTA_LIMITE_HORAS_DEFAULT = 5;

    protected $fillable = ['key', 'value'];

    /**
     * Valor crudo cacheado de una clave (null = la clave no existe en la tabla).
     */
    private static function raw(string $key): ?string
    {
        if (! array_key_exists($key, static::$cache)) {
            $value = static::query()->where('key', $key)->value('value');

            static::$cache[$key] = $value === null ? null : (string) $value;
        }

        return static::$cache[$key];
    }

    /**
     * Valor booleano de una clave ('1'/'true' son true; clave inexistente →
     * devuelve el default).
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::raw($key);

        if ($value === null) {
            return $default;
        }

        return in_array($value, ['1', 'true'], true);
    }

    /**
     * Valor entero de una clave (clave inexistente o no numérica → default).
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = static::raw($key);

        return ($value === null || ! is_numeric($value)) ? $default : (int) $value;
    }

    /**
     * Horas configuradas para vender después de la salida del colectivo.
     * Nunca negativas; 0 significa "sin límite".
     */
    public static function ventaLimiteHoras(): int
    {
        return max(0, self::getInt(self::VENTA_LIMITE_HORAS, self::VENTA_LIMITE_HORAS_DEFAULT));
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
