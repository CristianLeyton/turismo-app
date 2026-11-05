<?php

namespace App\Support;

use App\Models\Clients;
use App\Models\Sale;
use App\Models\Setting;

/**
 * Teléfonos y links de compartir por WhatsApp (wa.me) para los boletos.
 *
 * Reglas decididas con el usuario:
 *  - Candidatos: TODOS los teléfonos de la venta (teléfono de cada pasajero
 *    + el que el padrón tenga por el DNI del pasajero), sin duplicados.
 *  - Cada candidato recuerda de qué pasajero salió, para que el modal de
 *    entrega preseleccione el teléfono del pasajero de cada boleto.
 *  - El texto viene de Parámetros (Setting::WHATSAPP_MENSAJE) con {link}.
 *    El modal de entrega arma sus links de wa.me en el navegador; el
 *    detalle del boleto usa url().
 */
final class WhatsAppShare
{
    /**
     * Teléfonos de la venta, normalizados y sin repetir. Si el mismo número
     * aparece en dos pasajeros, queda con el primero que lo tiene. Con
     * `$unique = false` se conservan las repeticiones, para saber todos los
     * teléfonos de cada pasajero.
     *
     * @return list<array{passenger_id: int, name: string, phone: string, source: 'passenger'|'padron'}>
     */
    public static function candidatesForSale(Sale $sale, bool $unique = true): array
    {
        $passengers = $sale->tickets()
            ->with('passenger')
            ->orderBy('id')
            ->get()
            ->pluck('passenger')
            ->filter()
            ->unique('id');

        $dnis = $passengers->pluck('dni')->filter()->unique()->values();

        $padronByDni = $dnis->isNotEmpty()
            ? Clients::query()->whereIn('dni', $dnis->all())->get()->keyBy('dni')
            : collect();

        $candidates = collect();

        foreach ($passengers as $passenger) {
            if (filled($passenger->phone_number)) {
                $candidates->push([
                    'passenger_id' => $passenger->id,
                    'name' => $passenger->full_name,
                    'phone' => self::normalizePhone($passenger->phone_number),
                    'source' => 'passenger',
                ]);
            }

            $client = $padronByDni->get($passenger->dni);

            if ($client && filled($client->telefono)) {
                $candidates->push([
                    'passenger_id' => $passenger->id,
                    'name' => trim($client->nombre.' '.$client->apellido),
                    'phone' => self::normalizePhone($client->telefono),
                    'source' => 'padron',
                ]);
            }
        }

        $candidates = $candidates->filter(fn (array $candidate): bool => $candidate['phone'] !== '');

        if ($unique) {
            $candidates = $candidates->unique('phone');
        }

        return $candidates->values()->all();
    }

    /**
     * wa.me con país argentino por defecto: los teléfonos se cargan como
     * códigos de área locales (p. ej. 3881234567); wa.me exige país, así
     * que se antepone 54 a los cortos sin código país.
     *
     * La vista del modal de entrega (filament.tickets.delivery-modal) repite
     * esta regla en JS para los números escritos a mano: si cambia acá,
     * cambiala allá también.
     */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }

        // 54 = Argentina. Los números ya traídos con país quedan como están.
        if (! str_starts_with($digits, '54') && strlen($digits) <= 10) {
            return '54'.$digits;
        }

        return $digits;
    }

    /**
     * URL de wa.me hacia un teléfono ya normalizado, con el mensaje de
     * Parámetros y {link} reemplazado por el link del boleto.
     */
    public static function url(string $phone, string $link): string
    {
        $text = str_replace('{link}', $link, Setting::whatsappMensaje());

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }
}
