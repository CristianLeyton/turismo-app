<?php

namespace App\Models;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class PaymentMethod extends Model
{
    use SoftDeletes;

    /**
     * Paleta de colores de los métodos de pago, en orden de asignación.
     * Mismo estilo que los badges del panel (fondo suave, texto del tono y
     * variante oscura). Sin rojo (error), gris (inactivo) ni el color de la
     * empresa (cambia por instalación). `filament` es null cuando la clave ya
     * es un color registrado en el panel; si no, la constante de Color.
     *
     * @var array<string, array{label: string, filament: ?string, classes: string}>
     */
    public const PALETTE = [
        'success' => ['label' => 'Verde', 'filament' => null, 'classes' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'],
        'info' => ['label' => 'Celeste', 'filament' => null, 'classes' => 'bg-sky-500/10 text-sky-700 dark:text-sky-400'],
        'warning' => ['label' => 'Ámbar', 'filament' => null, 'classes' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400'],
        'teal' => ['label' => 'Verde azulado', 'filament' => 'Teal', 'classes' => 'bg-teal-500/10 text-teal-700 dark:text-teal-400'],
        'indigo' => ['label' => 'Índigo', 'filament' => 'Indigo', 'classes' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400'],
        'orange' => ['label' => 'Naranja', 'filament' => 'Orange', 'classes' => 'bg-orange-500/10 text-orange-700 dark:text-orange-400'],
        'rose' => ['label' => 'Rosa', 'filament' => 'Rose', 'classes' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400'],
        'cyan' => ['label' => 'Cian', 'filament' => 'Cyan', 'classes' => 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400'],
        'lime' => ['label' => 'Lima', 'filament' => 'Lime', 'classes' => 'bg-lime-500/10 text-lime-700 dark:text-lime-400'],
    ];

    /** Badge para códigos o colores desconocidos. */
    public const NEUTRAL_BADGE_CLASSES = 'bg-gray-500/10 text-gray-600 dark:text-gray-300';

    protected $fillable = [
        'code',
        'label',
        'color',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Métodos disponibles para NUEVOS registros: no eliminados y activos.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('label');
    }

    /**
     * Alta: genera el código (slug único del nombre) y el orden (máx + 1).
     * El usuario nunca los carga ni los edita: se derivan solos y quedan
     * inmutables después de crear.
     */
    protected static function booted(): void
    {
        static::creating(function (PaymentMethod $method) {
            $method->code = static::uniqueCodeFrom((string) $method->label);
            $method->sort_order = (int) static::withTrashed()->max('sort_order') + 1;

            // Sin color (o con uno fuera de la paleta): el sugerido, también
            // para altas que no pasan por el panel.
            // Se lee el atributo crudo: sin cargar, `$method->color` choca con
            // el helper estático color() (Eloquent lo toma por una relación).
            if (static::paletteEntry($method->getAttributes()['color'] ?? null) === null) {
                $method->color = static::suggestedColor();
            }
        });

        // Inmutabilidad: code y sort_order no cambian nunca tras el alta.
        static::updating(function (PaymentMethod $method) {
            $method->code = $method->getOriginal('code');
            $method->sort_order = $method->getOriginal('sort_order');
        });

        // Mantener los helpers consistentes ante cualquier cambio.
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
        static::restored(fn () => static::clearCache());
    }

    /**
     * Slug del nombre, con sufijo -2, -3... si el código ya existe
     * (considerando también los soft-deleted: los códigos históricos
     * de tickets/pagos nunca deben poder reutilizarse).
     */
    protected static function uniqueCodeFrom(string $label): string
    {
        $base = static::slugify($label);
        $code = $base;
        $attempt = 2;

        while (static::withTrashed()->where('code', $code)->exists()) {
            $code = $base.'-'.$attempt;
            $attempt++;
        }

        return $code;
    }

    public static function slugify(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        $ascii = ($ascii === false || $ascii === null) ? $value : $ascii;
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $ascii), '_'));

        return $slug !== '' ? $slug : 'metodo';
    }

    /**
     * Resuelve el label visible de un código, aunque el método esté
     * soft-deleted (los boletos históricos deben seguir mostrándolo).
     * Fallback gris si el código ya no existe en la tabla.
     */
    public static function label(?string $code): string
    {
        if ($code === null || $code === '') {
            return '—';
        }

        $method = static::resolve($code);

        return $method['label'] ?? ucfirst($code);
    }

    /**
     * Color de badge de Filament para un código: el nombre registrado en el
     * panel (success/info/warning) o el array de tonos de Filament para los
     * colores que el panel no trae. Gris si el código o su color no existen.
     *
     * @return string|array<int, string>
     */
    public static function color(?string $code): string|array
    {
        return static::paletteEntry(static::resolve($code)['color'] ?? null)['filament'] ?? 'gray';
    }

    /**
     * Clases Tailwind del badge para vistas Blade (detalle de ventas, boleto,
     * resumen). Escritas completas en PALETTE para que Tailwind las compile.
     */
    public static function badgeClasses(?string $code): string
    {
        return static::paletteEntry(static::resolve($code)['color'] ?? null)['classes'] ?? self::NEUTRAL_BADGE_CLASSES;
    }

    /**
     * Entrada de la paleta para una clave de color, o null si no pertenece.
     *
     * @return array{label: string, filament: string|array<int, string>, classes: string}|null
     */
    public static function paletteEntry(?string $color): ?array
    {
        if ($color === null || ! array_key_exists($color, self::PALETTE)) {
            return null;
        }

        $entry = self::PALETTE[$color];

        return [
            'label' => $entry['label'],
            'filament' => $entry['filament'] === null ? $color : constant(Color::class.'::'.$entry['filament']),
            'classes' => $entry['classes'],
        ];
    }

    /**
     * Opciones [clave => label] de la paleta para el formulario.
     *
     * @return array<string, string>
     */
    public static function paletteOptions(): array
    {
        return array_map(fn (array $entry): string => $entry['label'], self::PALETTE);
    }

    /**
     * Color que recibe un método nuevo: el primero de la paleta que no usa
     * ningún método vigente; si están todos en uso, el menos repetido (en
     * empate, el primero de la paleta).
     */
    public static function suggestedColor(): string
    {
        $usage = static::query()
            ->whereIn('color', array_keys(self::PALETTE))
            ->pluck('color')
            ->countBy()
            ->all();

        return static::leastUsedColor($usage);
    }

    /**
     * La clave de la paleta con menos usos según el conteo dado.
     *
     * @param  array<string, int>  $usage
     */
    public static function leastUsedColor(array $usage): string
    {
        $best = array_key_first(self::PALETTE);

        foreach (array_keys(self::PALETTE) as $color) {
            if (($usage[$color] ?? 0) < ($usage[$best] ?? 0)) {
                $best = $color;
            }
        }

        return $best;
    }

    /**
     * Opciones [code => label] para Selects/Radios de formularios (solo activos).
     */
    public static function options(): array
    {
        return Cache::remember('payment_methods.options', 300, function () {
            return static::query()
                ->active()
                ->get()
                ->mapWithKeys(fn (PaymentMethod $m) => [$m->code => $m->label])
                ->all();
        });
    }

    /**
     * Todos los métodos con el estado de activo para desgloses/exports:
     * [code => ['label' => ..., 'color' => ..., 'is_active' => ...]].
     * Incluye soft-deleted (con deleted_at no nulo) para histórico.
     */
    public static function allWithState(): array
    {
        return Cache::remember('payment_methods.all', 300, function () {
            return static::withTrashed()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->mapWithKeys(fn (PaymentMethod $m) => [
                    $m->code => [
                        'label' => $m->label,
                        'color' => $m->color,
                        'is_active' => $m->is_active,
                        'is_deleted' => $m->trashed(),
                    ],
                ])
                ->all();
        });
    }

    /**
     * Resuelve un código buscando también entre los eliminados. Cacheado 5 min;
     * el cache se limpia al guardar/borrar/eliminar desde el Resource.
     */
    protected static function resolve(?string $code): ?array
    {
        $all = static::allWithState();

        return $all[$code] ?? null;
    }

    /**
     * Limpia los caches del modelo (llamar tras cualquier CRUD).
     */
    public static function clearCache(): void
    {
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');
    }
}
