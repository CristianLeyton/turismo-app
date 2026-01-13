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
        Schema::create('bus_layout_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('floor')->nullable()->comment('1, 2, etc.');
            $table->string('area_type', 50)->comment('cafeteria, bathroom, storage, etc.');
            $table->string('label', 100)->nullable()->comment('Display label like "CAFETERA", "BAÑO"');
            $table->unsignedInteger('row_start')->nullable();
            $table->unsignedInteger('row_end')->nullable();
            $table->unsignedInteger('column_start')->nullable();
            $table->unsignedInteger('column_end')->nullable();
            $table->unsignedInteger('span_rows')->default(1)->comment('How many rows this area spans');
            $table->unsignedInteger('span_columns')->default(1)->comment('How many columns this area spans');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bus_id', 'floor']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bus_layout_areas');
    }
};
