{{-- Tarjeta "Detalles de venta" del infolist del boleto --}}
@php
    $record = $getRecord();
    $sale = $record?->sale;
    $methodLabel = $record ? \App\Models\PaymentMethod::label($record->payment_method) : '—';
    $methodBadge = $record
        ? \App\Models\PaymentMethod::badgeClasses($record->payment_method)
        : 'bg-gray-500/10 text-gray-600 dark:text-gray-300';
    $isReturnLegShared = $record
        ? \App\Filament\Resources\Tickets\Pages\EditTicket::isReturnLegWithSharedPrice($record)
        : false;
    $price = $isReturnLegShared ? (float) $record->display_price : (float) ($record?->price ?? 0);
@endphp

<h2 class="text-2xl font-bold">Detalles de venta</h2>

<div class="rounded-xl border p-5 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 mb-4">
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">

        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">N° Venta</div>
            <div class="text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ $sale?->id ?? '—' }}
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha de venta</div>
            <div class="text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ $sale?->sale_date ? \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y H:i') : '—' }}
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Precio</div>
            <div class="text-base font-semibold text-blue-600 dark:text-blue-400">
                ${{ number_format($price, 0, ',', '.') }}
            </div>
            @if ($isReturnLegShared)
                <div class="text-xs text-gray-500 dark:text-gray-400">Precio del boleto de ida</div>
            @endif
        </div>

        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Método de pago</div>
            <div class="text-base font-semibold">
                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $methodBadge }}">
                    {{ $methodLabel }}
                </span>
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Vendedor</div>
            <div class="text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ $sale?->user?->name ?? '—' }}
            </div>
        </div>
    </div>
</div>
