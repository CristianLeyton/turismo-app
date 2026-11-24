{{-- Aviso de boletos sin fecha de "Mis boletos", visible en cualquier pestaña.
     Recibe $pendingCount, $singlePending (el boleto, solo si hay uno) y
     $onPendingTab (la pestaña "Pendientes" ya está abierta).
     Un pendiente: lo nombra y "Elegir fecha" lo abre. Varios: dice cuántos y
     "Ver pendientes" lleva a la pestaña, sin elegir por el cliente. --}}

@if ($pendingCount > 0)
    <div
        role="status"
        class="flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 sm:flex-row sm:items-center sm:justify-between">
        <p class="flex items-center gap-2 text-sm font-medium">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-5 shrink-0" />
            @if ($singlePending)
                <span>
                    Tenés 1 boleto sin fecha:
                    {{ $singlePending->origin?->name ?? '?' }} → {{ $singlePending->destination?->name ?? '?' }}
                    (N°{{ $singlePending->id }}). Elegí cuándo querés viajar.
                </span>
            @else
                <span>
                    Tenés {{ $pendingCount }} boletos sin fecha.
                    {{ $onPendingTab ? 'Elegí la fecha de cada uno con su botón «Elegir fecha».' : 'Elegí la fecha de cada uno en «Pendientes».' }}
                </span>
            @endif
        </p>

        @if ($singlePending)
            <x-filament::button
                type="button"
                color="warning"
                size="sm"
                icon="heroicon-m-calendar-days"
                wire:click="chooseDateOfOldestPending">
                Elegir fecha
            </x-filament::button>
        @elseif (! $onPendingTab)
            <x-filament::button
                type="button"
                color="warning"
                size="sm"
                icon="heroicon-m-list-bullet"
                wire:click="showPendingTickets">
                Ver pendientes
            </x-filament::button>
        @endif
    </div>
@endif
