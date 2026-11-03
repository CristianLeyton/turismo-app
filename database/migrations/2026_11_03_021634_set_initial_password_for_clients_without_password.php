<?php

use App\Models\Clients;
use Illuminate\Database\Migrations\Migration;

/**
 * Clientes dados de alta sin contraseña (alta rápida desde la venta) no podían
 * entrar al panel. Reciben la inicial (su DNI, hasheado por el cast) y la
 * obligación de cambiarla, igual que el backfill de 2026_10_22_000002.
 *
 * `down()` no revierte datos: dejaría a esos clientes otra vez sin acceso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Clients::withTrashed()
            ->whereNull('password')
            ->chunkById(200, function ($clients): void {
                /** @var Clients $client */
                foreach ($clients as $client) {
                    $client->forceFill([
                        'password' => $client->dni,
                        'must_change_password' => true,
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        //
    }
};
