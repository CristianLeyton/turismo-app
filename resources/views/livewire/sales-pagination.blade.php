@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="flex flex-wrap items-center justify-between gap-3">
        {{-- Anterior --}}
        <div>
            @if ($paginator->onFirstPage())
                <span class="inline-flex cursor-default items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-300 dark:border-white/10 dark:bg-transparent dark:text-gray-600">← Anterior</span>
            @else
                <button
                    type="button"
                    wire:click="$set('page', {{ max($paginator->currentPage() - 1, 1) }})"
                    wire:loading.attr="disabled"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 dark:border-white/10 dark:bg-transparent dark:text-gray-300 dark:hover:bg-white/5"
                >← Anterior</button>
            @endif
        </div>

        {{-- Números de página --}}
        <div class="flex items-center gap-1">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1 text-sm text-gray-400 dark:text-gray-500">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-fuchsia-600 px-2.5 text-sm font-semibold text-white">{{ $page }}</span>
                        @else
                            <button
                                type="button"
                                wire:click="$set('page', {{ $page }})"
                                wire:loading.attr="disabled"
                                class="inline-flex h-8 min-w-8 cursor-pointer items-center justify-center rounded-lg px-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                            >{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        {{-- Siguiente --}}
        <div>
            @if ($paginator->hasMorePages())
                <button
                    type="button"
                    wire:click="$set('page', {{ $paginator->currentPage() + 1 }})"
                    wire:loading.attr="disabled"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 dark:border-white/10 dark:bg-transparent dark:text-gray-300 dark:hover:bg-white/5"
                >Siguiente →</button>
            @else
                <span class="inline-flex cursor-default items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-300 dark:border-white/10 dark:bg-transparent dark:text-gray-600">Siguiente →</span>
            @endif
        </div>
    </nav>
@endif
