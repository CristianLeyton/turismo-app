<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class PaymentMethod extends Model
{
    use SoftDeletes;

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
     * Color de badge de Filament para un código.
     */
    public static function color(?string $code): string
    {
        if ($code === null || $code === '') {
            return 'gray';
        }

        $method = static::resolve($code);

        return $method['color'] ?? 'gray';
    }

    /**
     * Clases CSS para badges en vistas blade custom (modal, exports).
     * Mapa fijo de colores de Filament -> clases Tailwind.
     */
    public static function badgeClasses(?string $code): string
    {
        $map = [
            'success' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
            'info' => 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
            'warning' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
            'danger' => 'bg-red-500/10 text-red-700 dark:text-red-400',
            'primary' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400',
            'gray' => 'bg-gray-500/10 text-gray-600 dark:text-gray-300',
        ];

        if ($code === null || $code === '') {
            return $map['gray'];
        }

        $method = static::resolve($code);
        $color = $method['color'] ?? 'gray';

        return $map[$color] ?? $map['gray'];
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
