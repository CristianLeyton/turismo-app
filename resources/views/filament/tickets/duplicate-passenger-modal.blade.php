{{-- Modal del paso "Pasajeros": una tarjeta por persona que ya tiene boleto en el viaje. --}}
<div class="flex flex-col gap-3">
    @foreach ($rows as $row)
        <div wire:key="duplicate-{{ $row['ticket_id'] }}-{{ $row['dni'] }}"
            class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-2 dark:border-gray-700">
                <div class="min-w-0">
                    <p class="truncate font-semibold text-gray-900 dark:text-gray-100">
                        {{ $row['name'] !== '' ? $row['name'] : 'Pasajero' }}
                        @if ($row['is_child'])
                            <span class="ml-1 rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">Menor</span>
                        @endif
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">DNI {{ $row['dni'] }}</p>
                </div>

                <x-filament::button tag="a" :href="$row['url']" target="_blank" size="sm"
                    icon="heroicon-m-arrow-top-right-on-square" icon-position="after">
                    Ver boleto
                </x-filament::button>
            </div>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-3 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Boleto</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">N°{{ $row['ticket_id'] }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Tramo</dt>
                    <dd class="font-medium text-fuchsia-600 dark:text-fuchsia-400">{{ $row['route'] !== '' ? $row['route'] : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Asiento</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $row['seat'] ?? '—' }}</dd>
                </div>
                <div class="col-span-2 sm:col-span-4">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Salida</dt>
                    <dd class="text-gray-900 tabular-nums dark:text-gray-100">
                        {{ trim($row['date'].' '.$row['time']) }}
                        @if ($row['bus'])
                            <span class="text-gray-500 dark:text-gray-400">· {{ $row['bus'] }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    @endforeach
</div>
