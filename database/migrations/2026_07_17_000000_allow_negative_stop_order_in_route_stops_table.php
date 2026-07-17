<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite valores negativos en stop_order para paradas previas al inicio formal de la ruta.
     */
    public function up(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->integer('stop_order')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->unsignedInteger('stop_order')->change();
        });
    }
};
