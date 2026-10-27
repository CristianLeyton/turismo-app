<x-filament-panels::page>
    <div class="flex justify-end items-center gap-3 mb-4">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Mostrar boletos ya con fecha
        </span>

        <button
            type="button"
            role="switch"
            aria-checked="{{ $mostrarFechados ? 'true' : 'false' }}"
            wire:click="$toggle('mostrarFechados')"
            @class([
                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none',
                'bg-primary-600' => $mostrarFechados,
                'bg-gray-200 dark:bg-gray-700' => ! $mostrarFechados,
            ])
        >
            <span
                @class([
                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200',
                    'translate-x-5' => $mostrarFechados,
                    'translate-x-0' => ! $mostrarFechados,
                ])
                aria-hidden="true"
            ></span>
        </button>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
