<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Token compartible para los links públicos de boletos (/boletos/{token}).
 *
 * El token se genera bajo demanda (lazy) para cubrir también los registros
 * anteriores a la feature: nunca hay que rellenar historial. Los prefijos
 * separan el namespace de tokens entre tickets y ventas, así que el
 * controlador resuelve la tabla de destino sin ambigüedad:
 *
 *   t_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx  → Ticket (boleto individual)
 *   s_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx  → Sale   (PDF combinado de la venta)
 */
trait HasShareToken
{
    public const SHARE_TOKEN_LENGTH = 32;

    /**
     * Token del registro (lo persiste la primera vez que se pide).
     */
    public function shareToken(): string
    {
        if (filled($this->share_token)) {
            return $this->share_token;
        }

        // Sin lock: la colisión es infima (32 alfanuméricos) y única en DB.
        $this->share_token = $this->generateShareToken();

        while (static::query()->where('share_token', $this->share_token)->exists()) {
            $this->share_token = $this->generateShareToken();
        }

        $this->save();

        return $this->share_token;
    }

    /**
     * URL pública de la página de previsualización del PDF.
     */
    public function shareUrl(): string
    {
        return route('boletos.share', ['token' => $this->shareToken()]);
    }

    /**
     * URL pública del PDF en sí (inline para embed, attachment para descargar).
     */
    public function sharePdfUrl(string $disposition = 'inline'): string
    {
        return route('boletos.share.pdf', [
            'token' => $this->shareToken(),
            'disposition' => $disposition,
        ]);
    }

    /**
     * Encuentra el Ticket o Sale dueño de un token de la ruta pública, o null.
     * El prefijo ('t_'/'s_') determina de qué tabla se busca.
     *
     * @return \App\Models\Ticket|\App\Models\Sale|null
     */
    public static function findByShareToken(string $token): ?static
    {
        if (! static::tokenMatchesNamespace($token)) {
            return null;
        }

        return static::query()->where('share_token', $token)->first();
    }

    /**
     * ¿El token tiene el prefijo de namespace de ESTA clase?
     */
    public static function tokenMatchesNamespace(string $token): bool
    {
        return str_starts_with($token, static::shareTokenPrefix().'_');
    }

    /**
     * Prefijo de tokens de esta clase ('s' para Sale, 't' para Ticket).
     */
    protected static function shareTokenPrefix(): string
    {
        return static::class === \App\Models\Sale::class ? 's' : 't';
    }

    /**
     * Genera el token completo con su prefijo de tabla.
     */
    public function generateShareToken(): string
    {
        return static::shareTokenPrefix().'_'.Str::random(static::SHARE_TOKEN_LENGTH);
    }
}
