{{-- Encabezado del recorrido mientras se reordena: Filament oculta las acciones
     del encabezado en ese modo, así que confirmar y descartar van acá. --}}
<div class="fi-ta-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <p class="fi-ta-header-description">
            {{ $hint }}
        </p>

        @if ($hasPendingChanges)
            <p class="fi-ta-header-description font-medium text-warning-600 dark:text-warning-400">
                Hay cambios sin guardar.
            </p>
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        <x-filament::button
            color="gray"
            icon="heroicon-m-x-mark"
            wire:click="discardStopOrder"
        >
            Descartar cambios
        </x-filament::button>

        <x-filament::button
            icon="heroicon-m-check"
            wire:click="requestStopOrderConfirmation"
        >
            Confirmar cambios
        </x-filament::button>
    </div>
</div>
