<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contraseña del cliente para el panel público (/clientes).
     *
     * NULL = todavía no eligió su propia contraseña: ingresa con el DNI solo
     * (igual que siempre) y el panel lo obliga a elegir una al entrar. Una vez
     * definida, el ingreso exige DNI + contraseña. Nunca se guarda el DNI como
     * hash: la "contraseña inicial = DNI" vive sólo en la regla anterior.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
