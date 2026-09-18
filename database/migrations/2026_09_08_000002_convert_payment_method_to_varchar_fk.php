<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // --- tickets.payment_method: enum -> VARCHAR(50) + FK ---
        Schema::table('tickets', function (Blueprint $table) {
            // Cambiar el tipo de columna requiere doctrine/dbal en Laravel <11;
            // aquí lo hacemos con SQL directo para no depender de cambios de DBAL.
            DB::statement("ALTER TABLE tickets MODIFY payment_method VARCHAR(50) NULL");

            $table->foreign('payment_method')
                ->references('code')
                ->on('payment_methods')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });

        // --- payments.payment_method: enum -> VARCHAR(50) + FK ---
        Schema::table('payments', function (Blueprint $table) {
            DB::statement("ALTER TABLE payments MODIFY payment_method VARCHAR(50) NOT NULL");

            $table->foreign('payment_method')
                ->references('code')
                ->on('payment_methods')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Soltar las FKs antes de restaurar los enums.
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['payment_method']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_method']);
        });

        // Restaurar los enums originales (solo es seguro si no se crearon métodos nuevos).
        DB::statement("ALTER TABLE tickets MODIFY payment_method ENUM('cash','transfer') NULL");
        DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('cash','transfer') NOT NULL");
    }
};
