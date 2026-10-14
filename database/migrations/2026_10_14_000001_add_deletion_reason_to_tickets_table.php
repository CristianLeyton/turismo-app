<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `deletion_reason` guarda el motivo obligatorio que se ingresa en el modal
 * de eliminación de un boleto (`ViewTicket::DeleteAction`).
 *
 * El motivo se muestra en el detalle de la auditoría de boletos
 * (`ticket-audit-detail`) junto con la marca "ELIMINADO". Los boletos
 * anteriores a esta migración quedan con NULL y el detalle muestra "—".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->text('deletion_reason')
                ->nullable()
                ->after('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('deletion_reason');
        });
    }
};
