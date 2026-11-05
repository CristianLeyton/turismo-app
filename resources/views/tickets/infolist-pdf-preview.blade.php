{{-- Vista previa del PDF del boleto en el "Ver boleto" del panel. Visor
     embebido solo donde el navegador lo soporta (escritorio); en el resto
     (Chrome en Android no muestra PDFs embebidos), tarjeta para abrirlo.
     La decisión vive en Alpine para que también corra al navegar dentro del
     panel (wire:navigate), donde un <script> inline no se vuelve a ejecutar.

     En modo link y con el boleto vigente se puede copiar el link público
     (el mismo que se envía por WhatsApp): en el encabezado con el visor, y
     en la tarjeta en lugar de "Descargar PDF". --}}
@php
    $record = $record ?? null;
    if (! $record && isset($getRecord) && is_callable($getRecord)) {
        $record = $getRecord();
    }

    $pdfUrl = route('admin.boletos.pdf', ['ticket' => $record->id, 'disposition' => 'inline']);
    $downloadUrl = route('admin.boletos.pdf', ['ticket' => $record->id, 'disposition' => 'attachment']);
    $filename = \App\Services\TicketPdfService::filenameFor($record);
    $shareUrl = \App\Filament\Resources\Tickets\Pages\ViewTicket::hasShareLink($record) ? $record->shareUrl() : null;
@endphp

<section
    x-data="{
        canEmbed: false,
        copied: false,

        copy(link) {
            const done = () => {
                this.copied = true;
                setTimeout(() => { this.copied = false }, 2000);
            };

            const fallback = () => {
                const input = document.createElement('textarea');
                input.value = link;
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
                navigator.clipboard.writeText(link).then(done, fallback);
            } else {
                fallback();
            }
        },
    }"
    x-init="canEmbed = navigator.pdfViewerEnabled === true && window.matchMedia('(min-width: 768px)').matches"
    class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 md:p-6 dark:bg-gray-900 dark:ring-white/10"
>
    <div class="mb-3 flex items-center justify-between gap-3">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
            Vista previa
        </h3>

        @if ($shareUrl)
            <div x-show="canEmbed" x-cloak>
                <x-filament::button type="button" size="sm" color="info" outlined
                    icon="heroicon-m-clipboard-document"
                    x-on:click="copy({{ \Illuminate\Support\Js::from($shareUrl) }})">
                    <span x-text="copied ? '¡Copiado!' : 'Copiar link'">Copiar link</span>
                </x-filament::button>
            </div>
        @endif
    </div>

    <div
        x-show="! canEmbed"
        class="flex flex-col gap-4 rounded-lg bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:bg-white/5"
    >
        <div class="flex items-center gap-3">
            {{ svg('heroicon-o-document-text', 'size-8 shrink-0 text-primary-600 dark:text-primary-400') }}
            <span class="text-sm font-medium break-all text-gray-700 dark:text-gray-300">{{ $filename }}</span>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <x-filament::button tag="a" :href="$pdfUrl" target="_blank" rel="noopener" color="gray" icon="heroicon-m-arrow-top-right-on-square">
                Abrir PDF
            </x-filament::button>

            @if ($shareUrl)
                <x-filament::button type="button" color="info" outlined icon="heroicon-m-clipboard-document"
                    x-on:click="copy({{ \Illuminate\Support\Js::from($shareUrl) }})">
                    <span x-text="copied ? '¡Copiado!' : 'Copiar link'">Copiar link</span>
                </x-filament::button>
            @else
                <x-filament::button tag="a" :href="$downloadUrl" :spa-mode="false" download icon="heroicon-m-arrow-down-tray">
                    Descargar PDF
                </x-filament::button>
            @endif
        </div>
    </div>

    <template x-if="canEmbed">
        <iframe
            src="{{ $pdfUrl }}"
            title="Vista previa del boleto"
            class="w-full rounded-lg border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5"
            style="height: 75vh"
        ></iframe>
    </template>
</section>
