<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite activar/desactivar rutas sin afectar viajes y boletos existentes.
     */
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
            $table->index(['bus_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropIndex(['bus_id', 'is_active']);
            $table->dropColumn('is_active');
        });
    }
};
