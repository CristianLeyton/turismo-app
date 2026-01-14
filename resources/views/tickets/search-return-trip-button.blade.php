<div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 p-4 border -mt-6 border-gray-200 dark:border-gray-700">
{{--     <p class="text-sm text-gray-700 dark:text-gray-300 mb-3 ">
        Haga clic en el botón para buscar el viaje de vuelta.
    </p> --}}
    <button type="button" wire:click="searchReturnTrip" wire:loading.attr="disabled" wire:target="searchReturnTrip"
        class="fi-color fi-color-primary fi-bg-color-600 hover:fi-bg-color-500 dark:fi-bg-color-600 
        dark:hover:fi-bg-color-500 fi-text-color-0 hover:fi-text-color-0 dark:fi-text-color-0 
        dark:hover:fi-text-color-0 fi-btn fi-size-md  fi-ac-btn-action">
        <svg wire:loading.remove wire:target="searchReturnTrip" class="fi-icon-btn-icon w-4 h-4" fill="none"
            stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
        <svg wire:loading wire:target="searchReturnTrip" class="fi-icon-btn-icon w-4 h-4 animate-spin" fill="none"
            viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
            </circle>
            <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
            </path>
        </svg>
        <span wire:loading.remove wire:target="searchReturnTrip">Buscar viaje de vuelta</span>
        <span wire:loading wire:target="searchReturnTrip">Buscando viaje de vuelta...</span>
    </button>
</div>
