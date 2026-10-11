<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plantillas de layout reutilizables al crear colectivos: guardan el
     * diseño completo (asientos + áreas + cantidad de pisos) con un nombre
     * amigable para el usuario.
     */
    public function up(): void
    {
        Schema::create('bus_layout_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nombre visible, ej: "Colectivo 2 pisos con baño"');
            $table->unsignedTinyInteger('floors')->default(1);
            $table->json('layout')->comment('Asientos + áreas en el formato del editor');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_layout_templates');
    }
};
