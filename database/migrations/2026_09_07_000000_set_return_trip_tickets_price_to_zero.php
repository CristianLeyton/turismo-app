<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CORREGIDA: Solo pone en $0 los tickets de VUELTA.
 *
 * Criterio correcto:
 *   is_round_trip = true AND return_trip_id IS NULL
 *
 * Un ticket de IDA en un ida+vuelta tiene:
 *   is_round_trip = true  y  return_trip_id = <id del viaje de vuelta>
 *
 * Un ticket de VUELTA tiene:
 *   is_round_trip = true  y  return_trip_id = null
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets')
            ->where('is_round_trip', true)
            ->whereNull('return_trip_id')
            ->where('price', '!=', 0)
            ->update(['price' => 0]);
    }

    public function down(): void
    {
        // No se puede revertir sin conocer los precios originales.
    }
};
