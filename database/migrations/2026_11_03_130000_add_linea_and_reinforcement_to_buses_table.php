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
        Schema::table('buses', function (Blueprint $table) {
            $table->foreignId('linea_id')->nullable()->after('name')->constrained('lineas')->nullOnDelete();
            $table->boolean('is_reinforcement')->default(false)->after('linea_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropForeign(['linea_id']);
            $table->dropColumn(['linea_id', 'is_reinforcement']);
        });
    }
};
