{{-- Vista previa del PDF del boleto en el "Ver boleto" del panel. Visor
     embebido solo donde el navegador lo soporta (escritorio); en el resto,
     tarjeta para abrirlo. La decisión vive en Alpine para que también corra
     al navegar dentro del panel (wire:navigate), donde un <script> inline no
     se vuelve a ejecutar. --}}
@php
    $record = $record ?? null;
    if (! $record && isset($getRecord) && is_callable($getRecord)) {
        $record = $getRecord();
    }

    $pdfUrl = route('admin.boletos.pdf', ['ticket' => $record->id, 'disposition' => 'inline']);
    $downloadUrl = route('admin.boletos.pdf', ['ticket' => $record->id, 'disposition' => 'attachment']);
    $filename = \App\Services\TicketPdfService::filenameFor($record);
@endphp

<section
    x-data="{ canEmbed: false }"
    x-init="canEmbed = navigator.pdfViewerEnabled === true && window.matchMedia('(min-width: 768px)').matches"
    class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 md:p-6 dark:bg-gray-900 dark:ring-white/10"
>
    <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">
        Vista previa
    </h3>

    <div
        x-show="! canEmbed"
        class="flex flex-col gap-4 rounded-lg bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:bg-white/5"
    >
        <div class="flex items-center gap-3">
            {{ svg('heroicon-o-document-text', 'size-8 shrink-0 text-fuchsia-600 dark:text-fuchsia-400') }}
            <span class="text-sm font-medium break-all text-gray-700 dark:text-gray-300">{{ $filename }}</span>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <x-filament::button tag="a" :href="$pdfUrl" target="_blank" rel="noopener" color="gray" icon="heroicon-m-arrow-top-right-on-square">
                Abrir PDF
            </x-filament::button>
            <x-filament::button tag="a" :href="$downloadUrl" icon="heroicon-m-arrow-down-tray">
                Descargar PDF
            </x-filament::button>
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
