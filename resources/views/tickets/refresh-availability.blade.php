@php
    use Filament\Support\Enums\Alignment;
    use Filament\Support\Enums\MaxWidth;
@endphp

<div class="fi-ta-field-group">
    <div class="fi-ta-field-group-item">
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <div class="text-sm font-medium text-gray-700">
                    Verificar disponibilidad de asientos
                </div>
                <button 
                    type="button" 
                    wire:click="refreshSeatAvailability"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 border border-blue-300 rounded-md hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refrescar
                </button>
            </div>
            <p class="text-xs text-gray-500">
                Si hace mucho tiempo que seleccionó los asientos, actualice la disponibilidad para ver si alguno fue vendido por otro usuario.
            </p>
        </div>
    </div>
</div>
