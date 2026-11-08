{{-- Líneas de contacto debajo del ingreso de clientes, las mismas del PDF y
     del link del boleto. Sin líneas cargadas no se muestra nada.
     Recibe $contactLines de Setting::contactLines(). --}}

@if ($contactLines !== [])
    <div
        class="mt-4 flex flex-col gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
        <p class="flex items-center gap-1.5 font-medium">
            {{ svg('heroicon-m-phone', 'size-4 shrink-0 text-gray-400 dark:text-gray-500') }}
            <span>¿No podés entrar? Comunicate con nosotros:</span>
        </p>

        <ul class="flex flex-col gap-1">
            @foreach ($contactLines as $line)
                <li class="flex flex-wrap items-center justify-between gap-2">
                    <span class="flex flex-wrap items-center gap-1">
                        @if ($line['name'] !== null)
                            <span>{{ $line['name'] }}:</span>
                        @endif
                        <a href="tel:{{ $line['dial'] }}"
                            class="font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ $line['phone'] }}</a>
                    </span>

                    <x-filament::button tag="a"
                        :href="'https://wa.me/'.\App\Models\Setting::whatsappNumber($line['phone'])"
                        target="_blank" rel="noopener" color="success" size="sm">
                        <x-icons.whatsapp class="size-4" />
                        WhatsApp
                    </x-filament::button>
                </li>
            @endforeach
        </ul>
    </div>
@endif
