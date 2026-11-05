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
     * Pedir la contraseña del admin logueado antes de guardar una edición
     * de datos de boleto (permiso tickets.edit).
     */
    public const REQUIRE_PASSWORD_TICKET_EDIT = 'tickets.require_password_edit';

    /**
     * Horas de gracia para vender un boleto cuyo colectivo YA SALIÓ.
     * Pasado ese plazo sólo puede vender quien tenga el permiso
     * `tickets.vender_pasado_limite`. 0 = sin límite.
     */
    public const VENTA_LIMITE_HORAS = 'tickets.venta_limite_horas';

    public const VENTA_LIMITE_HORAS_DEFAULT = 5;

    /**
     * Permitir vender boletos con acompañante (menor y/o mascota). Es un
     * parámetro visual: si está desactivado, el paso Pasajeros no muestra
     * los switches "¿Viaja con un menor?" / "¿Viaja con mascotas?".
     */
    public const PERMITIR_ACOMPANANTES = 'tickets.permitir_acompanantes';

    /**
     * Emisión de boletos al finalizar la venta. Activado (default): la venta
     * descarga el PDF automáticamente como siempre. Desactivado: NO hay
     * auto-descarga; la venta abre/presenta la página de previsualización del
     * PDF con link compartible (botón WhatsApp y descarga).
     */
    public const DESCARGA_AUTOMATICA_PDF = 'tickets.descarga_automatica_pdf';

    /**
     * Texto del mensaje de WhatsApp al compartir el link del boleto.
     * Placeholders disponibles: {link} (y cae bien el nombre de la venta).
     */
    public const WHATSAPP_MENSAJE = 'tickets.whatsapp_mensaje';

    public const WHATSAPP_MENSAJE_DEFAULT = 'Podés acceder a tu boleto desde aquí: {link}';

    /**
     * Días de la semana con servicio (JSON con enteros ISO 1 = lunes … 7 = domingo).
     */
    public const SERVICE_WEEKDAYS = 'service_days.weekdays';

    /** @var list<int> */
    public const SERVICE_WEEKDAYS_DEFAULT = [1, 2, 3, 4, 5, 6];

    /**
     * Excepciones por fecha a los días de servicio (JSON con una lista de
     * {date: Y-m-d, has_service: bool, reason: ?string}).
     */
    public const SERVICE_EXCEPTIONS = 'service_days.exceptions';

    /** @var list<array{date: string, has_service: bool, reason: ?string}> */
    public const SERVICE_EXCEPTIONS_DEFAULT = [
        ['date' => '2026-09-13', 'has_service' => true, 'reason' => null],
    ];

    /**
     * Líneas de contacto que se muestran en el PDF del boleto (JSON con una
     * lista de {name: ?string, phone: string}).
     */
    public const CONTACT_LINES = 'tickets.contact_lines';

    /**
     * Cantidad máxima de líneas de contacto. Es el único lugar que la define:
     * la página de parámetros y la lectura la respetan.
     */
    public const CONTACT_LINES_MAX = 3;

    /**
     * Panel de clientes (/clientes) disponible. Desactivado (default): el
     * panel y sus rutas responden 404, el login de administración no muestra
     * el aviso para pasajeros y el logo del boleto público va al inicio.
     */
    public const PANEL_CLIENTES_ACTIVO = 'clients.panel_activo';

    /**
     * Estilo de los selectores Origen y Destino de la venta. Apagado
     * (default): selects nativos con lista plana. Prendido: select con
     * buscador, paradas numeradas y destinos agrupados por sentido.
     */
    public const SELECTORES_DETALLADOS = 'sales.selectores_detallados';

    /**
     * Color principal de la empresa (hex `#RRGGBB`). De él sale la paleta
     * `primary` de los paneles, la página pública del boleto y los PDFs
     * (ver App\Support\BrandPalette).
     */
    public const PRIMARY_COLOR = 'appearance.primary_color';

    /** Fucsia histórico del sistema: sin configurar, todo se ve como antes. */
    public const PRIMARY_COLOR_DEFAULT = '#c026d3';

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
     * ¿Se puede vender con acompañante (menor/mascota)? Activado por defecto
     * para conservar el comportamiento actual cuando la clave no existe.
     */
    public static function permiteAcompanantes(): bool
    {
        return self::getBool(self::PERMITIR_ACOMPANANTES, true);
    }

    /**
     * ¿La venta descarga el PDF automáticamente al finalizar? Activado por
     * defecto: si la clave no existe, sigue el comportamiento histórico.
     * Desactivado = emisión por link (preview compartible, sin auto-descarga).
     */
    public static function descargaAutomaticaPdf(): bool
    {
        return self::getBool(self::DESCARGA_AUTOMATICA_PDF, true);
    }

    /**
     * ¿El panel de clientes está disponible? Desactivado por defecto: mientras
     * nadie lo active, /clientes no existe para el público.
     */
    public static function panelClientesActivo(): bool
    {
        return self::getBool(self::PANEL_CLIENTES_ACTIVO, false);
    }

    /**
     * ¿La venta muestra los selectores de origen y destino detallados?
     * Apagado por defecto: sin configurar, se ven los selects simples.
     */
    public static function selectoresDetallados(): bool
    {
        return self::getBool(self::SELECTORES_DETALLADOS, false);
    }

    /**
     * Color principal guardado, en minúsculas. Un valor que no sea `#RRGGBB`
     * (nunca guardado, o corrupto) cae al fucsia por defecto para que el
     * sitio nunca se quede sin paleta.
     */
    public static function primaryColor(): string
    {
        $value = self::raw(self::PRIMARY_COLOR);

        if ($value !== null && preg_match('/^#[0-9a-fA-F]{6}$/', trim($value)) === 1) {
            return strtolower(trim($value));
        }

        return self::PRIMARY_COLOR_DEFAULT;
    }

    /**
     * Texto del mensaje de WhatsApp al compartir el link del boleto.
     */
    public static function whatsappMensaje(): string
    {
        $value = self::raw(self::WHATSAPP_MENSAJE);

        return filled($value) ? $value : self::WHATSAPP_MENSAJE_DEFAULT;
    }

    /**
     * Líneas de contacto configuradas, en el orden cargado y como máximo
     * CONTACT_LINES_MAX. `dial` es el destino del enlace tel:: solo los
     * dígitos, con el "+" inicial si lo tiene. Sin configuración, ninguna.
     *
     * @return list<array{name: ?string, phone: string, dial: string}>
     */
    public static function contactLines(): array
    {
        $lines = [];

        foreach (self::decodeJson(self::CONTACT_LINES) ?? [] as $line) {
            $phone = is_array($line) ? trim((string) ($line['phone'] ?? '')) : '';

            if ($phone === '') {
                continue;
            }

            $name = trim((string) ($line['name'] ?? ''));

            $lines[] = [
                'name' => $name !== '' ? $name : null,
                'phone' => $phone,
                'dial' => self::dialablePhone($phone),
            ];

            if (count($lines) === self::CONTACT_LINES_MAX) {
                break;
            }
        }

        return $lines;
    }

    /**
     * Teléfono para un enlace tel:: "+54 9 (3878) 804-506" → "+5493878804506".
     */
    public static function dialablePhone(string $phone): string
    {
        $phone = trim($phone);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return (str_starts_with($phone, '+') ? '+' : '').$digits;
    }

    /**
     * Días de la semana con servicio (ISO 1-7). Sin configuración guardada,
     * lunes a sábado.
     *
     * @return list<int>
     */
    public static function serviceWeekdays(): array
    {
        $decoded = self::decodeJson(self::SERVICE_WEEKDAYS);

        if ($decoded === null) {
            return self::SERVICE_WEEKDAYS_DEFAULT;
        }

        $weekdays = array_values(array_unique(array_filter(
            array_map('intval', $decoded),
            fn (int $day): bool => $day >= 1 && $day <= 7,
        )));
        sort($weekdays);

        return $weekdays;
    }

    /**
     * Excepciones por fecha a los días de servicio. Sin configuración
     * guardada, el domingo 13/09/2026 con servicio.
     *
     * @return list<array{date: string, has_service: bool, reason: ?string}>
     */
    public static function serviceExceptions(): array
    {
        $decoded = self::decodeJson(self::SERVICE_EXCEPTIONS);

        if ($decoded === null) {
            return self::SERVICE_EXCEPTIONS_DEFAULT;
        }

        $exceptions = [];

        foreach ($decoded as $exception) {
            if (! is_array($exception) || blank($exception['date'] ?? null)) {
                continue;
            }

            $exceptions[] = [
                'date' => (string) $exception['date'],
                'has_service' => (bool) ($exception['has_service'] ?? false),
                'reason' => filled($exception['reason'] ?? null) ? (string) $exception['reason'] : null,
            ];
        }

        return $exceptions;
    }

    /**
     * Lista decodificada de una clave JSON, o null si la clave no existe o no
     * contiene una lista válida.
     *
     * @return array<int, mixed>|null
     */
    private static function decodeJson(string $key): ?array
    {
        $value = static::raw($key);

        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
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
