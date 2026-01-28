<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Eliminar la restricción unique actual
            $table->dropUnique('tickets_trip_seat_unique');

            // Crear la nueva restricción unique que incluye deleted_at
            $table->unique(['trip_id', 'seat_id', 'deleted_at'], 'tickets_trip_seat_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Eliminar la restricción unique nueva
            $table->dropUnique('tickets_trip_seat_unique');

            // Restaurar la restricción original
            $table->unique(['trip_id', 'seat_id'], 'tickets_trip_seat_unique');
        });
    }
};
