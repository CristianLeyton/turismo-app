<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `is_return_leg` marca explícitamente el boleto que representa el TRAMO DE
 * VUELTA de un diferido. Antes se identificaba por convención
 * (is_round_trip + return_trip_id NULL + price 0), que queda ambigua a partir
 * de ahora: una venta "ida con fecha + vuelta sin fecha" tiene el boleto de ida
 * con return_trip_id NULL. La convención queda como fallback en el modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('is_return_leg')->default(false)->after('is_round_trip');

            $table->index('is_return_leg');
        });

        // Backfill con la convención existente del tramo vuelta.
        DB::table('tickets')
            ->where('is_round_trip', true)
            ->whereNull('return_trip_id')
            ->where('price', 0)
            ->whereNull('deleted_at')
            ->update(['is_return_leg' => true]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['is_return_leg']);
            $table->dropColumn('is_return_leg');
        });
    }
};
