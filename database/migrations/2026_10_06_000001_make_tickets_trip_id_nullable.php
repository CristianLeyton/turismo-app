<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venta de pasajes sin fecha/horario (permiso `tickets.vender_sin_fecha`):
 * un boleto pendiente no pertenece a ningún viaje, así que `tickets.trip_id`
 * pasa a admitir NULL. `seat_id` y `return_trip_id` ya eran nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Seguridad: no restringir si hay boletos pendientes.
        $pendientes = DB::table('tickets')->whereNull('trip_id')->count();

        if ($pendientes > 0) {
            throw new RuntimeException(
                "No se puede revertir: hay {$pendientes} boleto(s) sin viaje asignado. asígnale fecha y horario primero."
            );
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable(false)->change();
        });
    }
};
