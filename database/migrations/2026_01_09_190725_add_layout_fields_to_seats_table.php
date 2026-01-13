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
        Schema::table('seats', function (Blueprint $table) {
            $table->unsignedInteger('row')->nullable()->after('floor');
            $table->unsignedInteger('column')->nullable()->after('row');
            $table->string('position', 20)->nullable()->after('column')->comment('left, right, center, aisle');
            $table->string('seat_type', 20)->default('normal')->after('position')->comment('normal, special, disabled');

            // Índices para mejorar consultas
            $table->index(['bus_id', 'floor', 'row', 'column']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropIndex(['bus_id', 'floor', 'row', 'column']);
            $table->dropColumn(['row', 'column', 'position', 'seat_type']);
        });
    }
};
