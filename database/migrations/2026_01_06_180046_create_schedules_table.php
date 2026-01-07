<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->time('departure_time');
            $table->time('arrival_time')->nullable();
            $table->string('name')->nullable(); // "Mañana", "Tarde", "Noche", etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['route_id', 'departure_time']);
            $table->index(['route_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};