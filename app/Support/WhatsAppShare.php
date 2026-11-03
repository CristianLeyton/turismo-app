<?php

namespace App\Support;

use App\Models\Sale;
use App\Models\Setting;

/**
 * Armado de links de compartir por WhatsApp (wa.me) para los boletos.
 *
 * Reglas decididas con el usuario:
 *  - Candidatos: TODOS los teléfonos de la venta (teléfono de cada pasajero
 *    + el que el padrón tenga por el DNI del pasajero), sin duplicados.
 *  - Un solo candidato → link directo wa.me/{tel}?text=...
 *  - Ninguno → no se muestra botón de WhatsApp; la página queda con el
 *    link, el preview y la descarga.
 *  - El texto viene de Parametros (Setting::WHATSAPP_MENSAJE) con {link}.
 */
final class WhatsAppShare
{
    /**
     * @param  string  $link  URL del boleto que se comparte (en el texto).
     * @return array<int, array{name: string, phone: string, url: string}>
     */
    public static function forSale(Sale $sale, string $link): array
    {
        // Pasajeros de la venta con su teléfono cargado en el wizard.
        $passengers = collect($sale->tickets()->with('passenger')->get())
            ->pluck('passenger')
            ->filter()
            ->unique('id');

        // Teléfono contenido en el padrón, cruzando por DNI.
        $dnis = $passengers->pluck('dni')->filter()->unique()->values();

        $padronByDni = $dnis->isNotEmpty()
            ? \App\Models\Clients::query()->whereIn('dni', $dnis->all())->get()->keyBy('dni')
            : collect();

        $candidates = collect();

        foreach ($passengers as $passenger) {
            if (filled($passenger->phone_number)) {
                $candidates->push([
                    'name' => $passenger->full_name,
                    'phone' => $passenger->phone_number,
                ]);
            }

            $client = $padronByDni->get($passenger->dni);

            if ($client && filled($client->telefono)) {
                $candidates->push([
                    'name' => trim($client->nombre.' '.$client->apellido),
                    'phone' => $client->telefono,
                ]);
            }
        }

        // Normalizar y quitar duplicados por teléfono normalizado.
        return $candidates
            ->map(function (array $candidate) use ($link): array {
                $phone = self::normalizePhone($candidate['phone']);

                return [
                    'name' => $candidate['name'],
                    'phone' => $phone !== '' ? $phone : $candidate['phone'],
                    'url' => $phone !== '' ? self::url($phone, $link) : null,
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['url'] !== null)
            ->unique('phone')
            ->values()
            ->all();
    }

    /**
     * wa.me con país argentino por defecto: los teléfonos se cargan como
     * códigos de área locales (p. ej. 3881234567); wa.me exige país, así
     * que se antepone 54 a los cortos sin código país.
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
     * URL de wa.me con el texto configurado (o genérica sin teléfono).
     */
    public static function url(?string $phone, string $link): string
    {
        $text = str_replace('{link}', $link, Setting::whatsappMensaje());

        return filled($phone)
            ? 'https://wa.me/'.$phone.'?text='.rawurlencode($text)
            : 'https://wa.me/?text='.rawurlencode($text);
    }
}
