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
            $table->boolean('travels_with_pets')->default(false)->after('travels_with_child');
            $table->string('pet_names')->nullable()->after('travels_with_pets');
            $table->integer('pet_count')->nullable()->after('pet_names');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['travels_with_pets', 'pet_names', 'pet_count']);
        });
    }
};
