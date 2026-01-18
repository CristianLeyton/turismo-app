@php
    use Filament\Support\Enums\Alignment;
    use Filament\Support\Enums\MaxWidth;
@endphp

<div class="fi-ta-field-group">
    <div class="fi-ta-field-group-item">
        <div class="space-y-0">
            <div class="flex items-center justify-between">
                <div class="text-xs font-medium text-gray-600 dark:text-gray-300">
                    Disponibilidad de asientos
                </div>
                <button 
                    type="button" 
                    wire:click="refreshSeatAvailability"
                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-fuchsia-700 dark:text-fuchsia-300 bg-fuchsia-50 dark:bg-fuchsia-900/20 border border-fuchsia-200 dark:border-fuchsia-700 rounded hover:bg-fuchsia-100 dark:hover:bg-fuchsia-900/30 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-fuchsia-500 transition-colors"
                >
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Actualizar
                </button>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500">
                Actualice para verificar si algún asiento fue vendido recientemente.
            </p>
        </div>
    </div>
</div>
