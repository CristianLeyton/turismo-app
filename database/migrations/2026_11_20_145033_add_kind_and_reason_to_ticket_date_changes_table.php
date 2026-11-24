<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de cada cambio de fecha y su motivo (change boleto-fecha-abierta).
 *
 * `kind` distingue una reprogramación (`reschedule`) de dejar el boleto con
 * fecha abierta (`open_date`). No se deduce de `to_trip_id` NULL porque la FK
 * es `nullOnDelete`: borrar el viaje de destino también lo deja en NULL. Las
 * filas existentes quedan como reprogramación por el default.
 *
 * `down()` quita las columnas; las filas `open_date` quedarían como
 * reprogramaciones a un viaje vacío.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_date_changes', function (Blueprint $table) {
            $table->string('kind', 20)->default('reschedule')->after('leg');
            $table->text('reason')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_date_changes', function (Blueprint $table) {
            $table->dropColumn(['kind', 'reason']);
        });
    }
};
