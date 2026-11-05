<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens de compartición para los links públicos de boletos.
 *
 * Con el flujo "emisión por link" (Setting::DESCARGA_AUTOMATICA_PDF off),
 * cada boleto y cada venta (PDF combinado) llevan un token aleatorio e
 * unguessable que arma URLs tipo /boletos/{token}: quien tenga el link
 * puede previsualizar/compartir el PDF, sin login ni sesión. El token vive
 * mientras exista el registro (boleto borrado → 404); se genera al vender
 * y de forma lazy para registros anteriores a la feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('deletion_reason');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
