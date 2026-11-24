<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo de precio y seguro para menores con que se vendió el boleto de ida.
 * Solo agrega columnas: los boletos existentes quedan en null y no se toca
 * ningún dato (ni el precio) de boletos ni ventas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('price_mode', 10)->nullable()->after('price');
            $table->unsignedInteger('child_insurance')->nullable()->after('price_mode');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['price_mode', 'child_insurance']);
        });
    }
};
