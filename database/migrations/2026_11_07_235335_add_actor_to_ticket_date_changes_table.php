<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda quién hizo cada reprogramación (change historial-cambios-de-clientes).
 *
 * Hasta ahora un cambio "de cliente" era un `user_id` nulo, pero la FK es
 * `nullOnDelete`: borrar del todo a un usuario también dejaba sus cambios sin
 * autor y pasaban por cambios de clientes. `actor_type` dice quién actuó sin
 * depender de que exista la fila, y `client_id` enlaza al cliente (si se lo
 * conoce) para mostrar su nombre.
 *
 * El backfill asume que todo `user_id` nulo anterior es de un cliente: es
 * irrecuperable saber si fue un usuario borrado antes de este cambio.
 *
 * `down()` quita las columnas; el código anterior vuelve a inferir por `user_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_date_changes', function (Blueprint $table) {
            $table->string('actor_type', 10)->default('user')->after('user_id');
            $table->foreignId('client_id')
                ->nullable()
                ->after('actor_type')
                ->constrained('clients')
                ->nullOnDelete();

            $table->index('actor_type');
        });

        $this->backfill();
    }

    /**
     * Completa el autor de las reprogramaciones ya registradas: las sin
     * usuario son de clientes, enlazadas por el DNI del pasajero del boleto
     * con el del cliente del padrón (incluso dado de baja). Público para
     * poder probarlo sobre filas armadas como las viejas.
     */
    public function backfill(): void
    {
        DB::table('ticket_date_changes')
            ->whereNull('user_id')
            ->update(['actor_type' => 'client']);

        DB::table('ticket_date_changes')
            ->where('actor_type', 'client')
            ->whereNull('client_id')
            ->select('id', 'ticket_id')
            ->chunkById(500, function ($changes): void {
                $dniByTicket = DB::table('tickets')
                    ->join('passengers', 'passengers.id', '=', 'tickets.passenger_id')
                    ->whereIn('tickets.id', $changes->pluck('ticket_id')->all())
                    ->pluck('passengers.dni', 'tickets.id');

                $clientByDni = DB::table('clients')
                    ->whereIn('dni', $dniByTicket->filter()->unique()->values()->all())
                    ->orderByDesc('id')
                    ->pluck('id', 'dni');

                foreach ($changes as $change) {
                    $clientId = $clientByDni[$dniByTicket[$change->ticket_id] ?? ''] ?? null;

                    if ($clientId !== null) {
                        DB::table('ticket_date_changes')
                            ->where('id', $change->id)
                            ->update(['client_id' => $clientId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('ticket_date_changes', function (Blueprint $table) {
            $table->dropIndex(['actor_type']);
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('actor_type');
        });
    }
};
