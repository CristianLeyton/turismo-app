@php
    /*
     * Resumen de totales de la tabla de Ventas (todos los breakpoints).
     *
     * Reemplaza a la fila "Resumen" del footer de la tabla: los mismos números
     * en un formato compacto y legible, también en mobile donde las columnas
     * de totales están ocultas (visibleFrom('md')).
     * SalesResource::mobileSummaryTotals() usa los MISMOS helpers que usaba el
     * footer (misma fuente de verdad) sobre la query ya filtrada de la tabla.
     *
     * Se inyecta vía render hook TOOLBAR_AFTER dentro del componente Livewire
     * (reacciona a los filtros sin eventos extra); el guard "¿estoy en la
     * página de Ventas?" vive en App\View\Components\TableSummaryCard.
     */
    use App\Filament\Resources\Sales\SalesResource;

    $t = SalesResource::mobileSummaryTotals();
    $saldo = $t['saldo'];
@endphp

<div>
    <div class="border-b p-3 border-gray-200 dark:border-white/10">
        {{-- grilla 2x2. --}}
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-2.5 dark:bg-white/5">
                <p class="text-[11px] text-gray-500 dark:text-gray-400">Boletos</p>
                <p class="text-base font-bold text-gray-900 dark:text-white">{{ $t['boletos'] }}</p>
            </div>
            <div class="rounded-lg bg-fuchsia-500/10 p-2.5">
                <p class="text-[11px] text-fuchsia-700 dark:text-fuchsia-400">Ventas</p>
                <p class="text-base font-bold text-fuchsia-700 dark:text-fuchsia-400">{{ SalesResource::formatMoneyPublic($t['ventas']) }}</p>
            </div>
            <div class="rounded-lg bg-amber-500/10 p-2.5">
                <p class="text-[11px] text-amber-700 dark:text-amber-400">Pagos</p>
                <p class="text-base font-bold text-amber-700 dark:text-amber-400">{{ SalesResource::formatMoneyPublic($t['pagos']) }}</p>
            </div>
            <div class="rounded-lg {{ $saldo > 0 ? 'bg-yellow-500/10' : ($saldo < 0 ? 'bg-red-500/10' : 'bg-gray-50 dark:bg-white/5') }} p-2.5">
                <p class="text-[11px] {{ $saldo > 0 ? 'text-yellow-700 dark:text-yellow-400' : ($saldo < 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400') }}">Saldo</p>
                <p class="text-base font-bold {{ $saldo > 0 ? 'text-yellow-700 dark:text-yellow-400' : ($saldo < 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-600 dark:text-gray-300') }}">{{ SalesResource::formatMoneyPublic($saldo) }}</p>
            </div>
        </div>
        <p class="mt-2 text-center text-[11px] text-gray-400 dark:text-gray-500 sm:text-right">
            {{ $t['from'] ? \Carbon\Carbon::parse($t['from'])->format('d/m/Y') : 'inicio' }} →
            {{ $t['to'] ? \Carbon\Carbon::parse($t['to'])->format('d/m/Y') : 'hoy' }}
        </p>
    </div>
</div>
