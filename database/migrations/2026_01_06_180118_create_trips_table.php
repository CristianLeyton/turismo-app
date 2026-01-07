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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('trip_date');
            $table->foreignId('schedule_id')
            ->nullable()
            ->constrained()
            ->cascadeOnDelete();

            $table->foreignId('route_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->softDeletes();
            $table->index(['trip_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
