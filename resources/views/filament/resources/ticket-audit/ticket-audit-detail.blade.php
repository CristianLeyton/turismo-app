@php
    use Carbon\Carbon;

    /*
     * Detalle de auditoría de un boleto (modal "Ver" y página de vista).
     *
     * Mismo vocabulario visual que tickets/infolist-summary-header y
     * filament/trips/trip-details: tarjeta principal con ruta en fucsia,
     * cifra grande a la derecha, tiles con labels uppercase y cajas de
     * aviso con borde de color.
     *
     * El record llega por $getRecord() cuando se renderiza dentro de un
     * schema (modal o infolist) o por $ticket en render directo (tests).
     */
    $record = (isset($getRecord) && $getRecord()) ? $getRecord() : ($ticket ?? null);

    $passenger = $record?->passenger;
    $seat = $record?->seat;
    $trip = $record?->trip;
    $sale = $record?->sale;

    $changes = $record?->dateChanges->sortByDesc('created_at')->values() ?? collect();

    $isDeleted = $record?->trashed() ?? false;
    $isRescheduled = $changes->isNotEmpty();

    $estadoLabel = $isDeleted && $isRescheduled
        ? 'Eliminado + Reprogramado'
        : ($isDeleted ? 'Eliminado' : 'Reprogramado');

    $tripLabel = function (?object $t): string {
        if (! $t) {
            return '—';
        }

        $label = $t->trip_date?->format('d/m/Y') ?? '—';
        if ($t->schedule?->departure_time) {
            $label .= ' • ' . $t->schedule->departure_time->format('H:i');
        }

        return $label;
    };

    $changeLabel = function (object $change, string $prefix) use ($tripLabel): string {
        $trip = $change->{"{$prefix}Trip"};
        $seat = $change->{"{$prefix}Seat"};

        $label = $tripLabel($trip);
        if ($seat) {
            $label .= ' · Asiento ' . $seat->seat_number;
        }

        return $label;
    };
@endphp

<div class="space-y-4">

    {{-- ================== ENCABEZADO PRINCIPAL ================== --}}
    <div class="rounded-xl border p-5 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700">

        <div class="flex flex-col md:flex-row md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    <span class="text-fuchsia-600">{{ $record?->origin?->name ?? 'Origen' }}</span>
                    →
                    <span class="text-fuchsia-600">{{ $record?->destination?->name ?? 'Destino' }}</span>
                </h2>

                <div class="mt-2 space-y-1">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong class="font-semibold">Pasajero:</strong>
                        {{ $passenger ? ($passenger->last_name . ' ' . $passenger->first_name) : '—' }}
                        <span class="mx-2">•</span>
                        DNI {{ $passenger?->dni ?? '—' }}
                    </div>

                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong class="font-semibold">Salida actual:</strong>
                        {{ $tripLabel($trip) }}
                        @if ($trip?->bus)
                            <span class="mx-2">•</span>Colectivo {{ $trip->bus->name }}
                        @endif
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full
                                {{ $isDeleted
                                    ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300'
                                    : 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300' }}">
                        {{ $estadoLabel }}
                    </span>

                    @if ($isRescheduled)
                        <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full
                                    bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            {{ $changes->count() }} reprogramacion{{ $changes->count() === 1 ? '' : 'es' }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="text-left md:text-right">
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase">Asiento actual</div>
                <div class="text-3xl font-bold text-fuchsia-600 dark:text-fuchsia-400">
                    {{ $seat?->seat_number ?? '—' }}
                </div>
                <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-semibold text-fuchsia-600 dark:text-fuchsia-400">
                        ${{ number_format((float) ($record?->display_price ?? 0), 0, ',', '.') }}
                    </span>
                    <span class="mx-1">•</span>
                    {{ \App\Models\PaymentMethod::label($record?->payment_method) ?? '—' }}
                </div>
            </div>
        </div>
    </div>

    {{-- ================== DATOS DE LA VENTA ================== --}}
    <div class="flex flex-wrap gap-x-8 gap-y-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4">
        <div>
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Fecha de venta</div>
            <div class="font-semibold text-gray-900 dark:text-gray-100">
                {{ $sale?->sale_date?->format('d/m/Y H:i') ?? '—' }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Venta N° {{ $sale?->id ?? '—' }}</div>
        </div>

        <div>
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Vendedor</div>
            <div class="font-semibold text-gray-900 dark:text-gray-100">
                {{ $sale?->user?->name ?? '—' }}
            </div>
            @if ($sale?->user?->username)
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $sale->user->username }}</div>
            @endif
        </div>

        <div>
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Ruta</div>
            <div class="font-semibold text-gray-900 dark:text-gray-100">
                {{ $trip?->route?->name ?? '—' }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Colectivo {{ $trip?->bus?->name ?? '—' }}
            </div>
        </div>
    </div>

    {{-- ================== ELIMINACIÓN ================== --}}
    @if ($isDeleted)
        <div class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-700 px-4 py-3 text-red-800 dark:text-red-200">
            <div class="text-sm font-semibold">
                ⚠ Este boleto fue ELIMINADO el {{ $record->deleted_at?->format('d/m/Y H:i') ?? '—' }}
                @if ($record->deletedBy)
                    por {{ $record->deletedBy->name }}{{ $record->deletedBy->username ? ' (' . $record->deletedBy->username . ')' : '' }}
                @endif
            </div>
            <div class="mt-1 text-xs">
                Los boletos eliminados no pueden restaurarse ni editarse desde la auditoría. Esta vista es informativa.
            </div>
        </div>
    @endif

    {{-- ================== HISTORIAL DE REPROGRAMACIONES ================== --}}
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-2 border-b border-gray-200 dark:border-gray-700 flex justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Historial de reprogramaciones</h3>
            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 text-right">({{ $changes->count() }})</div>
        </div>

        @forelse ($changes as $change)
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-2">
                    <span class="self-start inline-block px-2.5 py-1 text-xs font-semibold rounded-lg
                                bg-sky-100 dark:bg-sky-900/30 text-sky-800 dark:text-sky-300">
                        {{ $change->leg === \App\Models\TicketDateChange::LEG_RETURN ? 'Vuelta' : 'Ida' }}
                    </span>

                    <div class="text-sm text-gray-600 dark:text-gray-400 sm:text-right">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                            Realizado por: {{ $change->user?->name ?? 'Usuario eliminado del sistema' }}{{ $change->user?->username ? ' (' . $change->user->username . ')' : '' }}
                        </span>
                        <span class="mx-1">•</span>
                        {{ $change->created_at?->format('d/m/Y H:i') ?? '—' }}
                    </div>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                    <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-3">
                        <div class="text-xs uppercase text-red-700 dark:text-red-400">Antes</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ $changeLabel($change, 'from') }}
                        </div>
                        @if ($change->fromTrip?->bus)
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Colectivo {{ $change->fromTrip->bus->name }}
                            </div>
                        @endif
                    </div>

                    <div class="hidden sm:block text-gray-400 dark:text-gray-500 text-lg">→</div>

                    <div class="rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-3">
                        <div class="text-xs uppercase text-green-700 dark:text-green-400">Después</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ $changeLabel($change, 'to') }}
                        </div>
                        @if ($change->toTrip?->bus)
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Colectivo {{ $change->toTrip->bus->name }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="px-6 py-8">
                <p class="text-gray-500 dark:text-gray-400">Este boleto nunca fue reprogramado.</p>
            </div>
        @endforelse
    </div>

</div>
