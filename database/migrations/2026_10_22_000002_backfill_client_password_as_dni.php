<?php

use App\Models\Clients;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contraseña inicial de TODO cliente = su DNI (hasheado), más la bandera
     * must_change_password: al primer ingreso el panel le exige elegir una
     * propia (cartel "Protegé tu cuenta") antes de mostrarle nada. Una vez
     * elegida, la bandera queda en false y el ingreso exige DNI + contraseña.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(true);
        });

        // Sin eventos (updateQuietly) para no disparar observers: puro backfill.
        Clients::withTrashed()
            ->chunkById(200, function ($clients): void {
                /** @var Clients $client */
                foreach ($clients as $client) {
                    $client->forceFill([
                        // cast 'hashed': guarda DPR bcrypt del DNI.
                        'password' => $client->dni,
                        'must_change_password' => true,
                    ])->updateQuietly();
                }
            });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
