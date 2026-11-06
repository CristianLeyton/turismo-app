@php
    use Carbon\Carbon;

    /*
     * Detalle de auditoría de un boleto (modal "Ver" y página de vista).
     *
     * Mismo vocabulario visual que tickets/infolist-summary-header y
     * filament/trips/trip-details: tarjeta principal con ruta en el color
     * de la empresa,
     * cifra grande a la derecha, tiles con labels uppercase y cajas de
     * aviso con borde de color.
     *
     * El record llega por $getRecord() cuando se renderiza dentro de un
     * schema (modal o infolist) o por $ticket en render directo (tests).
     */
    $record = isset($getRecord) && $getRecord() ? $getRecord() : $ticket ?? null;

    $passenger = $record?->passenger;
    $seat = $record?->seat;
    $trip = $record?->trip;
    $sale = $record?->sale;

    $changes = $record?->dateChanges->sortByDesc('created_at')->values() ?? collect();
    $edits = $record?->edits ?? collect();

    $isDeleted = $record?->trashed() ?? false;
    $isRescheduled = $changes->isNotEmpty();
    $isEdited = $edits->isNotEmpty();

    $estadoPartes = array_values(
        array_filter([
            $isDeleted ? 'Eliminado' : null,
            $isRescheduled ? 'Reprogramado' : null,
            $isEdited ? 'Editado' : null,
        ]),
    );

    $estadoLabel = $estadoPartes === [] ? '—' : implode(' + ', $estadoPartes);

    // Color del badge de estado: lo eliminado manda (rojo), después lo
    // reprogramado (ámbar) y, si solo se corrigieron datos, azul.
    $estadoBadge = $isDeleted
        ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300'
        : ($isRescheduled
            ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300'
            : 'bg-sky-100 dark:bg-sky-900/30 text-sky-800 dark:text-sky-300');

    $tripLabel = function (?object $t): string {
        if (!$t) {
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

    {{-- ================== ELIMINACIÓN ================== --}}
    {{-- Va primero: es lo más importante del boleto y no tiene que perderse entre los datos. --}}
    @if ($isDeleted)
        <div class="rounded-xl border border-red-300 border-l-4 border-l-red-600 bg-red-50 p-4 dark:border-red-800 dark:border-l-red-500 dark:bg-red-900/20">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-m-trash" class="h-5 w-5 shrink-0 text-red-600 dark:text-red-400" />
                <h3 class="text-lg font-bold text-red-800 dark:text-red-200">Boleto eliminado</h3>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                <div class="rounded-lg bg-white/70 p-2.5 dark:bg-white/5">
                    <div class="text-[11px] uppercase text-red-700 dark:text-red-300">Eliminado por</div>
                    <div class="text-base font-bold text-gray-900 dark:text-gray-100">
                        {{ $record->deletedBy?->name ?? 'Sin registrar' }}
                    </div>
                    @if ($record->deletedBy?->username)
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $record->deletedBy->username }}</div>
                    @endif
                </div>

                <div class="rounded-lg bg-white/70 p-2.5 dark:bg-white/5">
                    <div class="text-[11px] uppercase text-red-700 dark:text-red-300">Fecha</div>
                    <div class="text-base font-bold text-gray-900 dark:text-gray-100">
                        {{ $record->deleted_at?->format('d/m/Y H:i') ?? '—' }}
                    </div>
                </div>

                <div class="rounded-lg bg-white/70 p-2.5 dark:bg-white/5">
                    <div class="text-[11px] uppercase text-red-700 dark:text-red-300">Motivo</div>
                    <div class="text-sm font-semibold break-words text-gray-900 dark:text-gray-100">
                        {{ filled($record->deletion_reason) ? $record->deletion_reason : '—' }}
                    </div>
                </div>
            </div>

            <p class="mt-3 text-xs text-red-700 dark:text-red-300">
                Los boletos eliminados no pueden restaurarse ni editarse desde la auditoría. Esta vista es informativa.
            </p>
        </div>
    @endif

    {{-- ================== ENCABEZADO PRINCIPAL ================== --}}
    <div class="rounded-xl border p-5 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700">

        <div class="flex flex-col md:flex-row md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    <span class="text-primary-600 dark:text-primary-400">{{ $record?->origin?->name ?? 'Origen' }}</span>
                    →
                    <span class="text-primary-600 dark:text-primary-400">{{ $record?->destination?->name ?? 'Destino' }}</span>
                </h2>

                <div class="mt-2 space-y-1">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong class="font-semibold">Pasajero:</strong>
                        {{ $passenger ? $passenger->last_name . ' ' . $passenger->first_name : '—' }}
                        <span class="mx-2">•</span>
                        DNI {{ $passenger?->dni ?? '—' }}
                    </div>

                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong class="font-semibold">Salida actual:</strong>
                        {{-- Boleto vendido sin fecha: igual que en el detalle del boleto. --}}
                        @if ($record?->isPendingDate())
                            <span class="font-semibold text-amber-600 dark:text-amber-400">Fecha y horario a confirmar</span>
                        @else
                            {{ $tripLabel($trip) }}
                        @endif
                        @if ($trip?->bus)
                            <span class="mx-2">•</span>Colectivo {{ $trip->bus->name }}
                        @endif
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full {{ $estadoBadge }}">
                        {{ $estadoLabel }}
                    </span>

                    @if ($isRescheduled)
                        <span
                            class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full
                                    bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            {{ $changes->count() }} reprogramacion{{ $changes->count() === 1 ? '' : 'es' }}
                        </span>
                    @endif

                    @if ($isEdited)
                        <span
                            class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full
                                    bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            {{ \App\Support\TicketEditHistory::summary($edits) }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="text-left md:text-right">
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase">Asiento actual</div>
                <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                    {{ $seat?->seat_number ?? '—' }}
                </div>
                <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-semibold text-primary-600 dark:text-primary-400">
                        ${{ number_format((float) ($record?->display_price ?? 0), 0, ',', '.') }}
                    </span>
                    <span class="mx-1">•</span>
                    {{ \App\Models\PaymentMethod::label($record?->payment_method) ?? '—' }}
                </div>
                {{-- En la vuelta el precio es el de la ida: sin esto parece que cada tramo cuesta eso. --}}
                @if ($record?->isReturnLeg())
                    <div class="text-xs text-gray-500 dark:text-gray-400">Precio del boleto de ida</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ================== DATOS DE LA VENTA ================== --}}
    <div
        class="flex flex-wrap gap-x-8 gap-y-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4">
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
        </div>
    </div>

    {{-- ================== OBSERVACIÓN ================== --}}
    @if (filled($record?->observation) && \App\Models\Setting::observacionesActivas())
        {{-- Observación interna: nunca va en el PDF del pasajero. --}}
        <div
            class="rounded-lg border-l-4 border-amber-400 bg-amber-50 px-4 py-3 dark:border-amber-500 dark:bg-amber-900/20">
            <div class="text-xs uppercase tracking-wide text-amber-800 dark:text-amber-300">Observación</div>
            <div class="mt-1 text-sm break-words whitespace-pre-line text-gray-900 dark:text-gray-100">{{ $record->observation }}</div>
        </div>
    @endif

    {{-- ================== HISTORIAL DE REPROGRAMACIONES ================== --}}
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-2 border-b border-gray-200 dark:border-gray-700 flex justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Historial de reprogramaciones</h3>
            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 text-right">({{ $changes->count() }})
            </div>
        </div>

        @forelse ($changes as $change)
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-2">
                    <span
                        class="self-start inline-block px-2.5 py-1 text-xs font-semibold rounded-lg
                                bg-sky-100 dark:bg-sky-900/30 text-sky-800 dark:text-sky-300">
                        {{ $change->leg === \App\Models\TicketDateChange::LEG_RETURN ? 'Vuelta' : 'Ida' }}
                    </span>

                    <div class="text-sm text-gray-600 dark:text-gray-400 sm:text-right">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                            Realizado por:
                            {{ $change->user?->name ?? 'Usuario eliminado del sistema' }}{{ $change->user?->username ? ' (' . $change->user->username . ')' : '' }}
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

                    <div
                        class="rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-3">
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

    {{-- ================== HISTORIAL DE EDICIONES ================== --}}
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-2 border-b border-gray-200 dark:border-gray-700 flex justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Historial de ediciones</h3>
            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 text-right">({{ $edits->count() }})
            </div>
        </div>

        @include('tickets.partials.edit-history', ['edits' => $edits])
    </div>

</div>
