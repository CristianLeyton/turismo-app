{{-- Aviso de boletos sin fecha de "Mis boletos". Recibe $pendingCount; sin
     pendientes no muestra nada. El botón abre "Elegir fecha" del más antiguo. --}}

@if ($pendingCount > 0)
    <div
        role="status"
        class="flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 sm:flex-row sm:items-center sm:justify-between">
        <p class="flex items-center gap-2 text-sm font-medium">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-5 shrink-0" />
            <span>
                {{ $pendingCount === 1
                    ? 'Tenés 1 boleto sin fecha.'
                    : "Tenés {$pendingCount} boletos sin fecha." }}
                Elegí cuándo querés viajar.
            </span>
        </p>

        <x-filament::button
            type="button"
            color="warning"
            size="sm"
            icon="heroicon-m-calendar-days"
            wire:click="chooseDateOfOldestPending">
            Elegir fecha
        </x-filament::button>
    </div>
@endif
