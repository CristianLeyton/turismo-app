<div class="space-y-4">
    {{-- Resumen de totales (arriba de los filtros) --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Resumen</p>
            @if ($this->type === 'all')
                @php($groupedCount = $this->groupedRecords->count())
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    {{ $groupedCount }} {{ $groupedCount === 1 ? 'registro' : 'registros' }} ·
                    {{ $this->totals['tickets_count'] }} boletos
                </p>
            @endif
        </div>

        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @if ($this->type !== 'payments')
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Boletos vendidos</p>
                    <p class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">
                        {{ $this->totals['tickets_count'] }}</p>
                </div>
                <div class="rounded-lg bg-emerald-500/10 p-3">
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">Ventas efectivo</p>
                    <p class="mt-0.5 text-lg font-bold text-emerald-700 dark:text-emerald-400">
                        {{ $this->money($this->totals['cash']) }}</p>
                </div>
                <div class="rounded-lg bg-sky-500/10 p-3">
                    <p class="text-xs text-sky-700 dark:text-sky-400">Ventas transferencia</p>
                    <p class="mt-0.5 text-lg font-bold text-sky-700 dark:text-sky-400">
                        {{ $this->money($this->totals['transfer']) }}</p>
                </div>
                <div class="rounded-lg bg-fuchsia-500/10 p-3">
                    <p class="text-xs text-fuchsia-700 dark:text-fuchsia-400">Total ventas</p>
                    <p class="mt-0.5 text-lg font-bold text-fuchsia-700 dark:text-fuchsia-400">
                        {{ $this->money($this->totals['ventas_total']) }}</p>
                </div>
            @endif

            @if ($this->type !== 'tickets')
                <div class="rounded-lg bg-amber-500/10 p-3">
                    <p class="text-xs text-amber-700 dark:text-amber-400">Pagos recibidos</p>
                    <p class="mt-0.5 text-lg font-bold text-amber-700 dark:text-amber-400">
                        {{ $this->totals['payments_count'] }}</p>
                </div>
                <div class="rounded-lg bg-emerald-500/10 p-3">
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">Pagos efectivo</p>
                    <p class="mt-0.5 text-lg font-bold text-emerald-700 dark:text-emerald-400">
                        {{ $this->money($this->totals['payments_cash']) }}</p>
                </div>
                <div class="rounded-lg bg-sky-500/10 p-3">
                    <p class="text-xs text-sky-700 dark:text-sky-400">Pagos transferencia</p>
                    <p class="mt-0.5 text-lg font-bold text-sky-700 dark:text-sky-400">
                        {{ $this->money($this->totals['payments_transfer']) }}</p>
                </div>
                <div class="rounded-lg bg-amber-500/10 p-3">
                    <p class="text-xs text-amber-700 dark:text-amber-400">Total pagos</p>
                    <p class="mt-0.5 text-lg font-bold text-amber-700 dark:text-amber-400">
                        {{ $this->money($this->totals['payments_total']) }}</p>
                </div>
            @endif
        </div>

        @if ($this->type === 'all')
            <div
                class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 px-4 py-3 dark:border-white/10
                {{ $this->totals['saldo'] > 0
                    ? 'bg-yellow-500/5'
                    : ($this->totals['saldo'] < 0
                        ? 'bg-red-500/5'
                        : 'bg-gray-50 dark:bg-white/5') }}">
                <p
                    class="text-sm font-medium {{ $this->totals['saldo'] > 0
                        ? 'text-yellow-700 dark:text-yellow-400'
                        : ($this->totals['saldo'] < 0
                            ? 'text-red-700 dark:text-red-400'
                            : 'text-gray-600 dark:text-gray-300') }}">
                    Saldo (total ventas − total pagos)
                </p>
                <p
                    class="text-lg font-bold {{ $this->totals['saldo'] > 0
                        ? 'text-yellow-700 dark:text-yellow-400'
                        : ($this->totals['saldo'] < 0
                            ? 'text-red-700 dark:text-red-400'
                            : 'text-gray-600 dark:text-gray-300') }}">
                    {{ $this->money($this->totals['saldo']) }}
                </p>
            </div>
        @endif
    </div>

    {{-- Filtros: rango de fechas + tipo de registro + método de pago --}}
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-col gap-3">
                {{-- Rango de fechas --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-3">
                    <div class="w-full sm:w-auto">
                        <label for="tickets-from"
                            class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Desde</label>
                        <input id="tickets-from" type="date" wire:model.live="from"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-fuchsia-500 focus:ring-1 focus:ring-fuchsia-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 sm:w-auto">
                    </div>
                    <div class="w-full sm:w-auto">
                        <label for="tickets-to"
                            class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Hasta</label>
                        <input id="tickets-to" type="date" wire:model.live="to"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-fuchsia-500 focus:ring-1 focus:ring-fuchsia-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 sm:w-auto">
                    </div>
                </div>

                {{-- Orden por fecha (debajo de las fechas) --}}
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Orden</span>
                    <div class="inline-flex max-w-full overflow-x-auto rounded-lg bg-gray-200/80 p-1 dark:bg-white/10">
                        @foreach (['asc' => '↑ Más antiguas', 'desc' => '↓ Más recientes'] as $value => $label)
                            <button type="button" wire:click="$set('sort', '{{ $value }}')"
                                title="{{ $value === 'asc' ? 'Ordenar por fecha: más antiguas primero' : 'Ordenar por fecha: más recientes primero' }}"
                                class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition
                                    {{ $this->sort === $value
                                        ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Tipo de registro: ventas y pagos / boletos / pagos --}}
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Ver</span>
                    <div class="inline-flex max-w-full overflow-x-auto rounded-lg bg-gray-200/80 p-1 dark:bg-white/10">
                        @foreach (['all' => 'Todos', 'tickets' => 'Ventas', 'payments' => 'Pagos'] as $value => $label)
                            <button type="button" wire:click="$set('type', '{{ $value }}')"
                                class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition
                                    {{ $this->type === $value
                                        ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Método de pago --}}
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Metodo de pago</span>
                    <div class="inline-flex max-w-full overflow-x-auto rounded-lg bg-gray-200/80 p-1 dark:bg-white/10">
                        @foreach (['all' => 'Todos', 'cash' => 'Efectivo', 'transfer' => 'Transferencia'] as $value => $label)
                            <button type="button" wire:click="$set('payment', '{{ $value }}')"
                                class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition
                                    {{ $this->payment === $value
                                        ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <button type="button" wire:click="resetFilters"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 dark:border-white/10 dark:bg-transparent dark:text-gray-300 dark:hover:bg-white/5">
                    <span aria-hidden="true">↺</span>
                    Restablecer
                </button>
            </div>
        </div>
    </div>

    {{-- Conteo de resultados + exportaciones --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        @php($salesCount = $this->groupedRecords->where('type', 'sale')->count())
        @php($groupedPaymentsCount = $this->groupedRecords->where('type', 'payment')->count())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $this->groupedRecords->count() }}</span>
            {{ $this->groupedRecords->count() === 1 ? 'resultado' : 'resultados' }}
            @if ($this->type === 'all')
                (<span class="font-medium text-gray-700 dark:text-gray-200">{{ $salesCount }}</span>
                {{ $salesCount === 1 ? 'venta' : 'ventas' }}
                ·
                <span class="font-medium text-gray-700 dark:text-gray-200">{{ $groupedPaymentsCount }}</span>
                {{ $groupedPaymentsCount === 1 ? 'pago' : 'pagos' }}
                ·
                <span class="font-medium text-gray-700 dark:text-gray-200">{{ $this->totals['tickets_count'] }}</span>
                boletos)
            @elseif ($this->type === 'tickets')
                (solo ventas · {{ $this->totals['tickets_count'] }} boletos)
            @else
                (solo pagos)
            @endif
            @if ($this->from || $this->to)
                · del
                <span
                    class="font-medium text-gray-700 dark:text-gray-200">{{ $this->from ? \Carbon\Carbon::parse($this->from)->format('d/m/Y') : 'inicio' }}</span>
                al
                <span
                    class="font-medium text-gray-700 dark:text-gray-200">{{ $this->to ? \Carbon\Carbon::parse($this->to)->format('d/m/Y') : 'hoy' }}</span>
            @endif
            @if ($this->payment !== 'all')
                · solo <span
                    class="font-medium text-gray-700 dark:text-gray-200">{{ $this->paymentLabel($this->payment) }}</span>
            @endif
            <span wire:loading.delay class="text-xs text-gray-400 dark:text-gray-500">Actualizando…</span>
        </p>

        <div class="flex items-center gap-2">
            <button type="button" wire:click="exportPdf" wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 rounded-lg bg-fuchsia-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-fuchsia-700 disabled:opacity-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                PDF
            </button>
            <button type="button" wire:click="exportExcel" wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Excel
            </button>
        </div>
    </div>

    {{-- Tabla de boletos y pagos --}}
    <div wire:loading.class.delay="opacity-50"
        class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2.5 font-medium">N°</th>
                    <th class="px-3 py-2.5 font-medium">Fecha</th>
                    <th class="px-3 py-2.5 text-center font-medium">Boletos</th>
                    <th class="px-3 py-2.5 text-center font-medium">Pago</th>
                    <th class="px-3 py-2.5 text-right font-medium">Monto</th>
                    <th class="px-3 py-2.5 text-center font-medium">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-gray-900">
                @forelse ($this->records as $record)
                    @php($isPayment = $record['type'] === 'payment')
                    @php($isSale = $record['type'] === 'sale')
                    @php($saleExpanded = $isSale && $this->isSaleExpanded((int) $record['id']))
                    @php($saleTickets = $isSale ? $record['tickets'] : collect())
                    <tr
                        class="{{ $isPayment
                            ? 'bg-amber-50/40 transition hover:bg-amber-50 dark:bg-amber-500/5 dark:hover:bg-amber-500/10'
                            : ($saleExpanded
                                ? 'bg-gray-50 transition dark:bg-white/5'
                                : 'transition hover:bg-gray-50 dark:hover:bg-white/5') }}">
                        {{-- N° --}}
                        <td class="px-3 py-2.5 align-top">
                            @if ($isPayment)
                                <span class="font-medium text-gray-900 dark:text-white">#{{ $record['model']->id }}</span>
                                <span
                                    class="mt-1 block w-fit rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">
                                    Pago recibido
                                </span>
                            @else
                                <span class="font-medium text-gray-900 dark:text-white">#{{ $record['id'] }}</span>
                                <span
                                    class="mt-1 block w-fit rounded-full bg-fuchsia-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-fuchsia-700 dark:text-fuchsia-400">
                                    Venta
                                </span>
                            @endif
                        </td>
                        {{-- Fecha --}}
                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-700 dark:text-gray-300">
                            @if ($isPayment)
                                {{ $record['model']->payment_date?->format('d/m/Y') ?? '—' }}
                            @else
                                {{ $record['date']?->format('d/m/Y H:i') ?? '—' }}
                            @endif
                        </td>
                        {{-- Boletos --}}
                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                            @if ($isPayment)
                                <span class="text-gray-300 dark:text-gray-600">—</span>
                            @else
                                <span class="rounded-full bg-gray-500/10 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    {{ $record['tickets_count'] }} {{ $record['tickets_count'] === 1 ? 'boleto' : 'boletos' }}
                                </span>
                            @endif
                        </td>
                        {{-- Pago --}}
                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                            @forelse ($record['payment_methods'] as $method)
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $this->paymentBadgeClasses($method) }}">
                                    {{ $this->paymentLabel($method) }}
                                </span>
                            @empty
                                <span class="text-gray-300 dark:text-gray-600">—</span>
                            @endforelse
                        </td>
                        {{-- Monto --}}
                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-bold text-gray-900 dark:text-white">
                            {{ $this->money($record['amount']) }}
                        </td>
                        {{-- Acción: ver detalle --}}
                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                            @if ($isSale)
                                <button type="button"
                                    wire:click="toggleSale({{ $record['id'] }})"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-100 dark:border-white/10 dark:bg-transparent dark:text-gray-300 dark:hover:bg-white/5">
                                    @if ($saleExpanded)
                                        <span aria-hidden="true">▲</span> Ocultar detalle
                                    @else
                                        <span aria-hidden="true">▼</span> Ver detalle
                                    @endif
                                </button>
                            @else
                                <span class="text-gray-300 dark:text-gray-600">—</span>
                            @endif
                        </td>
                    </tr>

                    {{-- Detalle de boletos de la venta --}}
                    @if ($saleExpanded)
                        <tr class="bg-gray-50/60 dark:bg-white/[0.02]">
                            <td colspan="6" class="px-3 pb-4 pt-1">
                                <div class="ml-4 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-gray-50 text-[10px] uppercase tracking-wide text-gray-400 dark:bg-white/5 dark:text-gray-500">
                                            <tr>
                                                <th class="px-3 py-2 font-medium">Pasajero</th>
                                                <th class="px-3 py-2 text-center font-medium">Asiento</th>
                                                <th class="px-3 py-2 font-medium">Salida</th>
                                                <th class="px-3 py-2 font-medium">Ruta</th>
                                                <th class="px-3 py-2 text-center font-medium">Pago</th>
                                                <th class="px-3 py-2 text-right font-medium">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                            @foreach ($saleTickets as $ticket)
                                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                                                    <td class="px-3 py-2">
                                                        <p class="truncate font-medium text-gray-800 dark:text-gray-200">
                                                            {{ $ticket->passenger?->full_name ?? 'Pasajero no disponible' }}
                                                        </p>
                                                        <p class="text-[11px] text-gray-400 dark:text-gray-500">
                                                            @if ($ticket->passenger?->dni)
                                                                DNI {{ $ticket->passenger->dni }}
                                                            @else
                                                                —
                                                            @endif
                                                            @if ($ticket->travels_with_child || $ticket->travels_with_pets)
                                                                <span class="text-gray-400 dark:text-gray-500">·</span>
                                                                {{ $ticket->travels_with_child ? 'Con menor' : '' }}
                                                                {{ $ticket->travels_with_child && $ticket->travels_with_pets ? ' · ' : '' }}
                                                                {{ $ticket->travels_with_pets ? 'Con mascota' : '' }}
                                                            @endif
                                                        </p>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-2 text-center">
                                                        @if ($ticket->seat)
                                                            <span class="rounded-full bg-fuchsia-500/10 px-2 py-0.5 text-[11px] font-semibold text-fuchsia-700 dark:text-fuchsia-400">
                                                                {{ $ticket->seat->seat_number }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">
                                                        @if ($ticket->trip)
                                                            {{ $ticket->trip->trip_date?->format('d/m/Y') }}
                                                            {{ $ticket->trip->schedule?->departure_time?->format('H:i') }} hs
                                                        @else
                                                            —
                                                        @endif
                                                        @if ($ticket->is_round_trip)
                                                            <span class="ml-1 rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400">Diferido</span>
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">
                                                        {{ $ticket->origin?->name ?? '—' }} <span aria-hidden="true" class="text-gray-400">→</span> {{ $ticket->destination?->name ?? '—' }}
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-2 text-center">
                                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $this->paymentBadgeClasses($ticket->payment_method) }}">
                                                            {{ $this->paymentLabel($ticket->payment_method) }}
                                                        </span>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">
                                                        {{ $this->money((float) $ticket->price) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-12 text-center">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">No hay ventas ni pagos para
                                mostrar</p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                Probá ampliar el rango de fechas o cambiar los filtros.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pie de tabla: contador + por página + paginación --}}
        @if ($this->records->total() > 0)
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                    <span>
                        Mostrando
                        <span
                            class="font-semibold text-gray-700 dark:text-gray-200">{{ $this->records->firstItem() }}</span>
                        a
                        <span
                            class="font-semibold text-gray-700 dark:text-gray-200">{{ $this->records->lastItem() }}</span>
                        de
                        <span
                            class="font-semibold text-gray-700 dark:text-gray-200">{{ $this->records->total() }}</span>
                        {{ $this->records->total() === 1 ? 'registro' : 'registros' }}
                    </span>
                    <label class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <span>Por página</span>
                        <select wire:model.live="perPage"
                            class="rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 focus:border-fuchsia-500 focus:ring-1 focus:ring-fuchsia-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </label>
                </div>

                {{ $this->records->links('livewire.sales-pagination') }}
            </div>
        @endif
    </div>
</div>
