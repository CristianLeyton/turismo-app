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
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('floor')->nullable();
            $table->unsignedInteger('row')->nullable();
            $table->unsignedInteger('column')->nullable();
            $table->string('position', 20)->nullable()->comment('left, right, center, aisle');
            $table->string('seat_type', 20)->default('normal')->comment('normal, special, disabled');

            $table->unsignedInteger('seat_number');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->unique(['bus_id', 'seat_number']);
            $table->index(['bus_id', 'floor', 'row', 'column']);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
