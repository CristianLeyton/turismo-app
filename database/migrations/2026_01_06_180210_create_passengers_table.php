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
        Schema::create('passengers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_passenger_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('dni')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->enum('passenger_type', ['adult', 'child'])->default('adult');
            
            $table->timestamps();

            $table->foreign('parent_passenger_id')->references('id')->on('passengers')->onDelete('cascade');
            $table->index(['last_name', 'dni']);
            $table->index('passenger_type');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passengers');
    }
};
