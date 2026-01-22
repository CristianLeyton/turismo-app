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
        Schema::create('seat_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trip_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('seat_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('user_session_id', 255);
            $table->timestamp('expires_at');

            $table->timestamps();

            // Índices para rendimiento
            $table->index(['trip_id', 'seat_id'], 'idx_trip_seat');
            $table->unique(['trip_id', 'seat_id'], 'unique_trip_seat_reservation');
            $table->index('expires_at', 'idx_expires_at');
            $table->index('user_session_id', 'idx_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seat_reservations');
    }
};
