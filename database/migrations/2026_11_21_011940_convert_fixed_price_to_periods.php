<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Precios por período (change precios-por-periodo): el precio fijo cargado
 * (`sales.precio_fijo_boleto` y `sales.precio_fijo_seguro_menor`) pasa a ser
 * un único período que rige desde el día del deploy. Solo si había precio y
 * todavía no hay períodos guardados.
 *
 * Las claves viejas se dejan en la tabla para poder volver atrás; `down()`
 * borra solo los períodos.
 */
return new class extends Migration
{
    private const PERIODS_KEY = 'sales.precio_fijo_periodos';

    public function up(): void
    {
        if (DB::table('settings')->where('key', self::PERIODS_KEY)->exists()) {
            return;
        }

        $base = $this->storedAmount('sales.precio_fijo_boleto');

        if ($base === null || $base < 1) {
            return;
        }

        DB::table('settings')->insert([
            'key' => self::PERIODS_KEY,
            'value' => json_encode([[
                'desde' => today()->format('Y-m-d'),
                'boleto' => $base,
                'seguro' => $this->storedAmount('sales.precio_fijo_seguro_menor') ?? 0,
            ]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Setting::flushCache();
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::PERIODS_KEY)->delete();

        Setting::flushCache();
    }

    /**
     * Monto guardado en pesos enteros, o null si no hay uno válido.
     */
    private function storedAmount(string $key): ?int
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        if (! is_string($value) || ! ctype_digit(trim($value))) {
            return null;
        }

        return (int) trim($value);
    }
};
