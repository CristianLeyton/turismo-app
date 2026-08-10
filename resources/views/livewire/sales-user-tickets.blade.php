<div class="space-y-4">
    {{-- Filtros: rango de fechas + método de pago --}}
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-3">
                <div>
                    <label for="tickets-from" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Desde</label>
                    <input
                        id="tickets-from"
                        type="date"
                        wire:model.live="from"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-fuchsia-500 focus:ring-1 focus:ring-fuchsia-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200"
                    >
                </div>
                <div>
                    <label for="tickets-to" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Hasta</label>
                    <input
                        id="tickets-to"
                        type="date"
                        wire:model.live="to"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-fuchsia-500 focus:ring-1 focus:ring-fuchsia-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200"
                    >
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="inline-flex rounded-lg bg-gray-200/80 p-1 dark:bg-white/10">
                    @foreach (['all' => 'Todos', 'cash' => 'Efectivo', 'transfer' => 'Transferencia'] as $value => $label)
                        <button
                            type="button"
                            wire:click="$set('payment', '{{ $value }}')"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition
                                {{ $this->payment === $value
                                    ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' }}"
                        >{{ $label }}</button>
                    @endforeach
                </div>

                <button
                    type="button"
                    wire:click="resetFilters"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 dark:border-white/10 dark:bg-transparent dark:text-gray-300 dark:hover:bg-white/5"
                >
                    <span aria-hidden="true">↺</span>
                    Restablecer
                </button>
            </div>
        </div>
    </div>

    {{-- Conteo de resultados + exportaciones --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $this->totals['count'] }}</span>
            {{ $this->totals['count'] === 1 ? 'boleto encontrado' : 'boletos encontrados' }}
            @if ($this->from || $this->to)
                · del
                <span class="font-medium text-gray-700 dark:text-gray-200">{{ $this->from ? \Carbon\Carbon::parse($this->from)->format('d/m/Y') : 'inicio' }}</span>
                al
                <span class="font-medium text-gray-700 dark:text-gray-200">{{ $this->to ? \Carbon\Carbon::parse($this->to)->format('d/m/Y') : 'hoy' }}</span>
            @endif
            @if ($this->payment !== 'all')
                · solo <span class="font-medium text-gray-700 dark:text-gray-200">{{ $this->paymentLabel($this->payment) }}</span>
            @endif
            <span wire:loading.delay class="text-xs text-gray-400 dark:text-gray-500">Actualizando…</span>
        </p>

        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="exportPdf"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 rounded-lg bg-fuchsia-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-fuchsia-700 disabled:opacity-50"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                PDF
            </button>
            <button
                type="button"
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Excel
            </button>
        </div>
    </div>

    {{-- Tabla de boletos --}}
    <div wire:loading.class.delay="opacity-50" class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2.5 font-medium">N°</th>
                    <th class="px-3 py-2.5 font-medium">Venta</th>
                    <th class="px-3 py-2.5 font-medium">Salida</th>
                    <th class="px-3 py-2.5 font-medium">Ruta</th>
                    <th class="px-3 py-2.5 font-medium">Pasajero</th>
                    <th class="px-3 py-2.5 text-center font-medium">Asiento</th>
                    <th class="px-3 py-2.5 text-center font-medium">Pago</th>
                    <th class="px-3 py-2.5 text-right font-medium">Precio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-gray-900">
                @forelse ($this->tickets as $ticket)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                        <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400">#{{ $ticket->id }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-700 dark:text-gray-300">
                            {{ $ticket->sale?->sale_date?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-700 dark:text-gray-300">
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
                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-700 dark:text-gray-300">
                            {{ $ticket->origin?->name ?? '—' }} <span aria-hidden="true" class="text-gray-400">→</span> {{ $ticket->destination?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-2.5">
                            <p class="truncate font-medium text-gray-800 dark:text-gray-200">
                                {{ $ticket->passenger?->full_name ?? 'Pasajero no disponible' }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
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
                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                            @if ($ticket->seat)
                                <span class="rounded-full bg-fuchsia-500/10 px-2 py-0.5 text-xs font-semibold text-fuchsia-700 dark:text-fuchsia-400">
                                    {{ $ticket->seat->seat_number }}
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $this->paymentBadgeClasses($ticket->payment_method) }}">
                                {{ $this->paymentLabel($ticket->payment_method) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold text-gray-900 dark:text-white">
                            {{ $this->money($ticket->price) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-12 text-center">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">No hay boletos para mostrar</p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                Probá ampliar el rango de fechas o cambiar el método de pago.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Resumen de totales --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Resumen</p>
        <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Boletos</p>
                <p class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">{{ $this->totals['count'] }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Efectivo</p>
                <p class="mt-0.5 text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $this->money($this->totals['cash']) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Transferencia</p>
                <p class="mt-0.5 text-lg font-bold text-sky-600 dark:text-sky-400">{{ $this->money($this->totals['transfer']) }}</p>
            </div>
            <div class="rounded-lg bg-fuchsia-500/10 p-2">
                <p class="text-xs text-fuchsia-700 dark:text-fuchsia-400">Total</p>
                <p class="mt-0.5 text-lg font-bold text-fuchsia-700 dark:text-fuchsia-400">{{ $this->money($this->totals['total']) }}</p>
            </div>
        </div>
    </div>
</div>
