{{-- Bloque "¿Necesitás ayuda?" de "Mis boletos". Recibe $contactLines de
     Setting::contactLines(): cada línea con "Llamar" (tel:) y "WhatsApp"
     (wa.me). Sin líneas cargadas no muestra nada. --}}

@if ($contactLines !== [])
    <div
        class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
        <p class="flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
            <x-filament::icon icon="heroicon-m-phone" class="size-4 shrink-0 text-gray-400 dark:text-gray-500" />
            <span>¿Necesitás ayuda? Comunicate con nosotros:</span>
        </p>

        <ul class="flex flex-col gap-2">
            @foreach ($contactLines as $line)
                <li wire:key="help-line-{{ $loop->index }}" class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm text-gray-600 dark:text-gray-300">
                        @if ($line['name'] !== null)
                            {{ $line['name'] }}:
                        @endif
                        <span class="font-semibold text-gray-950 dark:text-white">{{ $line['phone'] }}</span>
                    </span>

                    <span class="flex gap-2">
                        <x-filament::button tag="a" :href="'tel:'.$line['dial']" color="gray" size="sm"
                            icon="heroicon-m-phone">
                            Llamar
                        </x-filament::button>
                        <x-filament::button tag="a"
                            :href="'https://wa.me/'.\App\Models\Setting::whatsappNumber($line['phone'])"
                            target="_blank" rel="noopener" color="success" size="sm">
                            <x-icons.whatsapp class="size-4" />
                            WhatsApp
                        </x-filament::button>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
