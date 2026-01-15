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
        Schema::table('passengers', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_passenger_id')->nullable()->after('id');
            $table->enum('passenger_type', ['adult', 'child'])->default('adult')->after('email');
            
            $table->foreign('parent_passenger_id')->references('id')->on('passengers')->onDelete('cascade');
            $table->index('passenger_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropForeign(['parent_passenger_id']);
            $table->dropIndex(['passenger_type']);
            $table->dropColumn(['parent_passenger_id', 'passenger_type']);
        });
    }
};
