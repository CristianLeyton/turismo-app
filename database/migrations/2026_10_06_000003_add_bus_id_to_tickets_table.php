<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `bus_id` guarda en el boleto el COLECTIVO elegido al vender. Con la venta
 * sin fecha el boleto nace sin viaje (trip_id NULL): el colectivo elegido
 * sólo queda registrado acá, y la reprogramación posterior
 * (`tickets.reschedule`) sólo acepta horarios de rutas de ese colectivo.
 *
 * Los boletos anteriores a esta migración quedan con bus_id NULL y la
 * reprogramación NO los restringe a un colectivo (comportamiento histórico:
 * permite mover el boleto a cualquier horario que cubra el segmento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('bus_id')
                ->nullable()
                ->after('seat_id')
                ->constrained('buses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bus_id');
        });
    }
};
