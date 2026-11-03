<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja `is_return_leg` como única fuente de verdad del tramo de vuelta
 * (change marca-tramo-vuelta, hallazgo H2):
 *
 *  1. Completa el backfill de `2026_10_06_000002`, que solo marcó boletos
 *     vigentes: marca los dados de baja que cumplen la convención histórica
 *     (ida y vuelta + sin viaje de vuelta + precio 0).
 *  2. Corrige las ventas donde la ida a precio 0 quedó marcada como vuelta:
 *     por cada (venta, pasajero) con exactamente dos boletos, ambos de ida y
 *     vuelta y marcados como vuelta, el de menor id vuelve a ser la ida (la
 *     emisión siempre crea la ida primero). Otras formas no se tocan.
 *
 * `down()` no revierte datos: dejaría marcas incorrectas a propósito.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets')
            ->whereNotNull('deleted_at')
            ->where('is_round_trip', true)
            ->whereNull('return_trip_id')
            ->where('price', 0)
            ->where('is_return_leg', false)
            ->update(['is_return_leg' => true]);

        $outboundIds = DB::table('tickets')
            ->selectRaw('MIN(id) as outbound_id')
            ->groupBy('sale_id', 'passenger_id')
            ->havingRaw('COUNT(*) = 2')
            ->havingRaw('SUM(CASE WHEN is_round_trip = 1 AND is_return_leg = 1 THEN 1 ELSE 0 END) = 2')
            ->pluck('outbound_id');

        foreach ($outboundIds->chunk(500) as $chunk) {
            DB::table('tickets')
                ->whereIn('id', $chunk->all())
                ->update(['is_return_leg' => false]);
        }
    }

    public function down(): void
    {
        //
    }
};
