<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna un color de la paleta a los métodos de pago que hoy no tienen uno
 * (los creados desde el panel quedaban en 'gray').
 *
 * Mismo criterio que PaymentMethod::suggestedColor(): el primer color sin
 * uso, o el menos repetido. Primero los métodos vigentes (en orden) y después
 * los eliminados, que siguen apareciendo en boletos viejos. La paleta se
 * copia acá para que la migración no cambie si el modelo cambia después.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PALETTE = ['success', 'info', 'warning', 'teal', 'indigo', 'orange', 'rose', 'cyan', 'lime'];

    public function up(): void
    {
        $methods = DB::table('payment_methods')
            ->orderByRaw('deleted_at IS NOT NULL')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'color']);

        $usage = $methods
            ->whereIn('color', self::PALETTE)
            ->countBy('color')
            ->all();

        foreach ($methods as $method) {
            if (in_array($method->color, self::PALETTE, true)) {
                continue;
            }

            $color = $this->leastUsed($usage);
            $usage[$color] = ($usage[$color] ?? 0) + 1;

            DB::table('payment_methods')->where('id', $method->id)->update(['color' => $color]);
        }
    }

    /**
     * No se revierte: no hay forma de saber qué método era gris a propósito.
     */
    public function down(): void
    {
        //
    }

    /**
     * @param  array<string, int>  $usage
     */
    private function leastUsed(array $usage): string
    {
        $best = self::PALETTE[0];

        foreach (self::PALETTE as $color) {
            if (($usage[$color] ?? 0) < ($usage[$best] ?? 0)) {
                $best = $color;
            }
        }

        return $best;
    }
};
