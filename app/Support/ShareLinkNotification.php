<?php

namespace App\Support;

use App\Models\Sale;
use Filament\Actions\Action;

/**
 * Acciones de la notification de "emisión por link" (parámetro
 * DESCARGA_AUTOMATICA_PDF desactivado). Reutilizada por la venta
 * (CreateTicket) y la reprogramación (RescheduleTicket) para que ambos
 * caminos ofrezcan exactamente lo mismo, en este orden:
 *
 *  - "Abrir link": la página compartible, en otra pestaña.
 *  - "Copiar link": dispara el evento de navegador COPY_EVENT con la URL;
 *    el listener del panel (AdminPanelProvider, BODY_END) la copia. Va por
 *    evento porque la notification se serializa y solo conserva url/evento,
 *    no un handler de Alpine.
 *  - "Descargar PDF": attachment del link compartido (boleto o combinado).
 *  - "Compartir por WhatsApp": un solo candidato de teléfono → wa.me
 *    directo; varios → abre la página del link, donde se elige a quién
 *    enviar; ninguno → la acción no existe (queda copiar el link).
 */
final class ShareLinkNotification
{
    public const COPY_EVENT = 'copy-share-link';

    /**
     * @param  string  $sharePageUrl  Página de previsualización compartible.
     * @param  string  $downloadUrl  PDF en modo attachment del mismo link.
     * @return array<Action>
     */
    public static function actionsFor(Sale $sale, string $sharePageUrl, string $downloadUrl): array
    {
        $targets = WhatsAppShare::forSale($sale, $sharePageUrl);

        $actions = [
            Action::make('abrir_link')
                ->label('Abrir link')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->button()
                ->url($sharePageUrl, shouldOpenInNewTab: true),

            Action::make('copiar_link')
                ->label('Copiar link')
                ->icon('heroicon-m-clipboard-document')
                ->color('gray')
                ->button()
                ->dispatch(self::COPY_EVENT, ['url' => $sharePageUrl]),

            Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->button()
                ->url($downloadUrl, shouldOpenInNewTab: true),
        ];

        if (count($targets) > 0) {
            // Con varios teléfonos la elección de destinatario vive en la
            // página del link (un botón por persona).
            $actions[] = Action::make('compartir_whatsapp')
                ->label('Compartir por WhatsApp')
                ->icon('heroicon-m-paper-airplane')
                ->color('success')
                ->button()
                ->url(count($targets) === 1 ? $targets[0]['url'] : $sharePageUrl, shouldOpenInNewTab: true);
        }

        return $actions;
    }

    /**
     * Listener del panel para "Copiar link": portapapeles con fallback para
     * contextos no seguros (http local) y aviso corto de confirmación.
     */
    public static function copyListenerScript(): string
    {
        $event = self::COPY_EVENT;

        return <<<HTML
            <script>
                window.addEventListener('{$event}', (event) => {
                    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
                    const url = detail?.url;

                    if (! url) {
                        return;
                    }

                    const done = () => new FilamentNotification()
                        .title('Link copiado')
                        .success()
                        .duration(2500)
                        .send();

                    const fallbackCopy = () => {
                        const input = document.createElement('textarea');
                        input.value = url;
                        input.setAttribute('readonly', '');
                        input.style.position = 'fixed';
                        input.style.opacity = '0';
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        input.remove();
                        done();
                    };

                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(url).then(done, fallbackCopy);
                    } else {
                        fallbackCopy();
                    }
                });
            </script>
            HTML;
    }
}
