<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de ediciones de boletos (corrección de errores de venta).
 *
 * Separado de `ticket_date_changes` (eso es reprogramación: fecha/horario/
 * colectivo/asiento). Acá se guardan una fila por CAMPO editado:
 * pasajero, menor/mascota, método de pago y precio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_edits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('field');           // first_name, last_name, dni...
            $table->string('field_label');     // "Nombre", "Apellido", "DNI"...
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable(); // motivo opcional
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
            $table->index('field');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_edits');
    }
};
