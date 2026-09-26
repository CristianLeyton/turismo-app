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
        Schema::create('ticket_date_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            // Tramo auditado: 'outbound' (ida) o 'return' (vuelta)
            $table->string('leg', 20);

            $table->foreignId('from_trip_id')
                ->nullable()
                ->constrained('trips')
                ->nullOnDelete();

            $table->foreignId('to_trip_id')
                ->nullable()
                ->constrained('trips')
                ->nullOnDelete();

            $table->foreignId('from_schedule_id')
                ->nullable()
                ->constrained('schedules')
                ->nullOnDelete();

            $table->foreignId('to_schedule_id')
                ->nullable()
                ->constrained('schedules')
                ->nullOnDelete();

            $table->foreignId('from_seat_id')
                ->nullable()
                ->constrained('seats')
                ->nullOnDelete();

            $table->foreignId('to_seat_id')
                ->nullable()
                ->constrained('seats')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('ticket_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_date_changes');
    }
};
