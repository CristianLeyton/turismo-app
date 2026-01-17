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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('trip_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('origin_location_id')
                ->constrained('locations');

            $table->foreignId('destination_location_id')
                ->constrained('locations');

            $table->foreignId('return_trip_id')
                ->nullable()
                ->constrained('trips')
                ->nullOnDelete();

            $table->foreignId('passenger_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('seat_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->boolean('is_round_trip')->default(false);
            $table->boolean('travels_with_child')->default(false);

            $table->decimal('price', 10, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->index(['trip_id', 'seat_id']);
            $table->unique(['trip_id', 'seat_id'], 'tickets_trip_seat_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
