@php
    /*
     * Resumen de totales de la tabla de Pagos (todos los breakpoints).
     *
     * Mismo patrón que la tarjeta de Ventas: PaymentResource::mobileSummaryTotals()
     * usa la MISMA fuente de verdad que los exports (footerTotals) sobre la query
     * ya filtrada de la tabla, así que reacciona a fechas, vendedor y pagos
     * eliminados sin eventos extra.
     *
     * Se inyecta vía render hook TOOLBAR_AFTER dentro del componente Livewire;
     * el guard "¿estoy en la página de Pagos?" vive en TableSummaryCard.
     *
     * @var array{count: int, total: float, from: ?string, to: ?string} $totals
     */
    use App\Filament\Resources\Payments\PaymentResource;
@endphp

<div>
    <div class="border-b p-3 border-gray-200 dark:border-white/10">
        {{-- grilla 2x2. --}}
        <div class="grid grid-cols-2 gap-2">
            <div class="rounded-lg bg-fuchsia-50 p-2.5 dark:bg-fuchsia-900/50">
                <p class="text-[11px] text-fuchsia-500 dark:text-fuchsia-400">Pagos recibidos</p>
                <p class="text-base font-bold text-fuchsia-600 dark:text-fuchsia-500">{{ $totals['count'] }}</p>
            </div>
            <div class="rounded-lg bg-amber-500/10 p-2.5">
                <p class="text-[11px] text-amber-700 dark:text-amber-400">Total cobrado</p>
                <p class="text-base font-bold text-amber-700 dark:text-amber-400">{{ PaymentResource::formatMoneyPublic($totals['total']) }}</p>
            </div>
            <div class="mt-2 text-center text-[11px] text-gray-400 dark:text-gray-500 sm:text-right col-span-2">
                <p class="text-[11px] text-gray-400 dark:text-gray-500 sm:text-right">
                    {{ $totals['from'] ? \Carbon\Carbon::parse($totals['from'])->format('d/m/Y') : 'inicio' }} →
                    {{ $totals['to'] ? \Carbon\Carbon::parse($totals['to'])->format('d/m/Y') : 'hoy' }}
                </p>
            </div>
        </div>
    </div>
</div>
