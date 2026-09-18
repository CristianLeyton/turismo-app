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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // clave estable usada por tickets/payments
            $table->string('label', 100); // nombre visible (Efectivo, Transferencia, ...)
            $table->string('color', 50)->default('gray'); // color del badge en Filament
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes(); // los boletos/pagos históricos siguen resolviendo el nombre
            $table->timestamps();
        });

        // Seed atómico: los dos métodos que hoy existen como enum ('cash', 'transfer').
        DB::table('payment_methods')->insert([
            [
                'code' => 'cash',
                'label' => 'Efectivo',
                'color' => 'success',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'transfer',
                'label' => 'Transferencia',
                'color' => 'info',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
