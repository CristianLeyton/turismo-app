<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un solo boleto VIGENTE por viaje y asiento, garantizado por la base.
 *
 * El índice anterior (trip_id, seat_id, deleted_at) no frenaba duplicados
 * vigentes: deleted_at es NULL en los boletos vigentes y un índice único
 * admite varios NULL. Se reemplaza por (trip_id, seat_id, active_seat_guard),
 * donde active_seat_guard es una columna generada que vale 1 en los boletos
 * vigentes y NULL en los dados de baja (que así no ocupan el asiento).
 * Los boletos sin fecha tienen trip_id y seat_id en NULL y no participan.
 *
 * Antes de cualquier cambio verifica que no existan duplicados vigentes
 * (el DDL de MySQL no es transaccional). Consulta para revisar a mano:
 *
 *   SELECT trip_id, seat_id, GROUP_CONCAT(id) ids, COUNT(*) total
 *   FROM tickets
 *   WHERE deleted_at IS NULL AND trip_id IS NOT NULL AND seat_id IS NOT NULL
 *   GROUP BY trip_id, seat_id HAVING COUNT(*) > 1;
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoActiveDuplicates();

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('tickets_trip_seat_unique');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_seat_guard')
                ->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN 1 END');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['trip_id', 'seat_id', 'active_seat_guard'], 'tickets_active_trip_seat_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('tickets_active_trip_seat_unique');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('active_seat_guard');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['trip_id', 'seat_id', 'deleted_at'], 'tickets_trip_seat_unique');
        });
    }

    /**
     * Detiene la migración, sin modificar nada, si hay boletos vigentes
     * duplicados en un mismo viaje y asiento.
     */
    private function assertNoActiveDuplicates(): void
    {
        $duplicates = DB::table('tickets')
            ->select('trip_id', 'seat_id')
            ->whereNull('deleted_at')
            ->whereNotNull('trip_id')
            ->whereNotNull('seat_id')
            ->groupBy('trip_id', 'seat_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $details = $duplicates->map(function (object $duplicate): string {
            $ids = DB::table('tickets')
                ->where('trip_id', $duplicate->trip_id)
                ->where('seat_id', $duplicate->seat_id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->pluck('id')
                ->implode(', ');

            return "viaje {$duplicate->trip_id}, asiento (seat_id) {$duplicate->seat_id}: boletos {$ids}";
        })->implode('; ');

        throw new RuntimeException(
            'No se puede agregar la restricción de un solo boleto vigente por asiento: hay boletos vigentes duplicados. '
            ."Corregilos (dar de baja o reprogramar uno de cada grupo) y volvé a migrar. Conflictos: {$details}."
        );
    }
};
