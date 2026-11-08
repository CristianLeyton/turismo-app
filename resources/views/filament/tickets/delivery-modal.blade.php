{{--
    Modal de entrega de boletos por link (App\Filament\Actions\TicketDeliveryAction).
    Una tarjeta por boleto, con el mismo estilo que el modal de pasajero
    duplicado de la venta. Todo se resuelve en el navegador: el número
    elegido arma la URL de wa.me y cada acción es un enlace real, así el
    bloqueador de ventanas no la frena.

    Los enlaces no usan wire:navigate (el panel está en modo SPA): WhatsApp y
    "Ver PDF" se abren en otra pestaña y "Descargar PDF" es una descarga
    directa del navegador.

    normalize() repite la regla de App\Support\WhatsAppShare::normalizePhone
    para los números escritos a mano: si cambia allá, cambiala acá también.
--}}
@php
    $firstPhone = $phones[0]['phone'] ?? null;

    $initialState = collect($rows)
        ->mapWithKeys(fn (array $row): array => ['t'.$row['id'] => [
            'selected' => $row['defaultPhone'] ?? $firstPhone ?? 'other',
            'manual' => '',
        ]])
        ->put('all', ['selected' => $firstPhone ?? 'other', 'manual' => ''])
        ->all();

    $allLines = collect($rows)
        ->map(fn (array $row): string => ($row['name'] !== '' ? "{$row['name']} (N°{$row['id']})" : "Boleto N°{$row['id']}").": {$row['link']}")
        ->implode("\n");
@endphp

<div
    x-data="{
        template: @js($template),
        allLines: @js($allLines),
        state: @js($initialState),
        copied: null,

        normalize(value) {
            let digits = String(value ?? '').replace(/\D+/g, '');

            if (digits === '') {
                return '';
            }

            digits = digits.replace(/^0+/, '');

            if (! digits.startsWith('54') && digits.length <= 10) {
                return '54' + digits;
            }

            return digits;
        },

        target(key) {
            const entry = this.state[key];

            return entry.selected === 'other' ? this.normalize(entry.manual) : entry.selected;
        },

        isValid(key) {
            const entry = this.state[key];

            if (entry.selected !== 'other') {
                return entry.selected !== '';
            }

            return String(entry.manual ?? '').replace(/\D+/g, '').length >= 8;
        },

        whatsappUrl(key, replacement) {
            if (! this.isValid(key)) {
                return null;
            }

            const text = this.template.split('{link}').join(replacement);

            return 'https://wa.me/' + this.target(key) + '?text=' + encodeURIComponent(text);
        },

        copy(key, link) {
            const done = () => {
                this.copied = key;
                setTimeout(() => { if (this.copied === key) this.copied = null }, 2000);
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
    class="flex flex-col gap-3"
>
    @foreach ($rows as $row)
        @php($key = 't'.$row['id'])

        <div wire:key="delivery-{{ $row['id'] }}"
            class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-2 dark:border-gray-700">
                <div class="min-w-0">
                    <p class="truncate font-semibold text-gray-900 dark:text-gray-100">{{ $row['title'] }}</p>

                    @if ($row['route'])
                        <p class="text-sm font-medium text-primary-600 dark:text-primary-400">{{ $row['route'] }}</p>
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-3 px-4 py-3">
                @include('filament.tickets.delivery-modal-phone', [
                    'key' => $key,
                    'label' => 'Enviar a',
                    'buttonLabel' => 'Enviar',
                    'replacement' => \Illuminate\Support\Js::from($row['link']),
                ])

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <x-filament::button tag="a" :href="$row['link']" target="_blank" :spa-mode="false"
                        size="sm" color="gray" outlined icon="heroicon-m-eye">
                        Ver PDF
                    </x-filament::button>

                    <x-filament::button type="button" size="sm" color="primary" outlined
                        icon="heroicon-m-clipboard-document"
                        x-on:click="copy('{{ $key }}', {{ \Illuminate\Support\Js::from($row['link']) }})">
                        <span x-text="copied === '{{ $key }}' ? '¡Copiado!' : 'Copiar link'">Copiar link</span>
                    </x-filament::button>

                    <x-filament::button tag="a" :href="$row['download']" :spa-mode="false" download
                        size="sm" color="primary" icon="heroicon-m-arrow-down-tray">
                        Descargar PDF
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endforeach

    @if (count($rows) > 1)
        <div class="rounded-lg border border-gray-200 bg-gray-50 shadow-sm dark:border-gray-700 dark:bg-white/5">
            <div class="border-b border-gray-200 px-4 py-2 dark:border-gray-700">
                <p class="font-semibold text-gray-900 dark:text-gray-100">Enviar todos a un número</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Un solo mensaje con los links de los {{ count($rows) }} boletos.</p>
            </div>

            <div class="px-4 py-3">
                @include('filament.tickets.delivery-modal-phone', [
                    'key' => 'all',
                    'label' => 'Número',
                    'buttonLabel' => 'Enviar todos',
                    'replacement' => "'\\n' + allLines",
                ])
            </div>
        </div>
    @endif
</div>
