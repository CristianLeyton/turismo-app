@php
    use Carbon\Carbon;

    // El ticket llega por viewData desde el formulario; fallback al record del esquema.
    $record = $ticket ?? $getRecord();

    $passenger = $record?->passenger;
    $trip = $record?->trip;
    $returnTrip = $record?->returnTrip;
    $seat = $record?->seat;

    $isReturnLegTicket = $record?->isReturnLeg() ?? false;
    $isPendingDate = $record?->isPendingDate() ?? false;

    $idaDeparture =
        $record && !$isReturnLegTicket
            ? $record->getBoardingDepartureTime() ?? $trip?->schedule?->departure_time
            : null;
    $idaArrival =
        $record && !$isReturnLegTicket ? $record->getBoardingArrivalTime() ?? $trip?->schedule?->arrival_time : null;

    $vueltaDeparture = $isReturnLegTicket
        ? $record->getBoardingDepartureTime(null, true) ?? $trip?->schedule?->departure_time
        : ($record?->is_round_trip
            ? $record->getBoardingDepartureTime(null, true) ?? $returnTrip?->schedule?->departure_time
            : null);
    $vueltaArrival = $isReturnLegTicket
        ? $record->getBoardingArrivalTime(null, true) ?? $trip?->schedule?->arrival_time
        : ($record?->is_round_trip
            ? $record->getBoardingArrivalTime(null, true) ?? $returnTrip?->schedule?->arrival_time
            : null);
@endphp


{{-- Datos del boleto en una grilla que se reparte sola en el ancho disponible
     (con o sin vuelta), y el asiento fijo a la derecha como dato principal. --}}
<div class="space-y-2 text-sm">
    <div
        class="flex items-stretch gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-white/5">
        <dl class="grid min-w-0 flex-1 grid-cols-[repeat(auto-fit,minmax(10rem,1fr))] gap-x-6 gap-y-3">
            <div class="min-w-0">
                <dt class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Pasajero</dt>
                <dd class="font-semibold break-words text-gray-900 dark:text-gray-100">
                    {{ $passenger ? $passenger->last_name . ' ' . $passenger->first_name : '—' }}
                </dd>
                <dd class="text-xs text-gray-500 dark:text-gray-400">DNI {{ $passenger?->dni ?? '—' }}</dd>
            </div>

            <div class="min-w-0">
                <dt class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Tramo</dt>
                <dd class="font-semibold break-words text-gray-900 dark:text-gray-100">
                    {{ $record?->origin?->name }} → {{ $record?->destination?->name }}
                </dd>
                <dd class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $isReturnLegTicket ? 'Tramo de vuelta (diferido)' : ($record?->is_round_trip ? 'Ida de un diferido' : 'Solo ida') }}
                </dd>
            </div>

            <div class="min-w-0">
                <dt class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $isReturnLegTicket ? 'Vuelta actual' : 'Ida actual' }}
                </dt>
                <dd class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                    @if ($isPendingDate)
                        <span class="text-amber-600 dark:text-amber-400">Sin fecha — a confirmar</span>
                    @elseif ($isReturnLegTicket)
                        {{ $trip?->trip_date?->format('d/m/Y') }} •
                        {{ $vueltaDeparture ? Carbon::parse($vueltaDeparture)->format('H:i') : '--:--' }}
                    @else
                        {{ $trip?->trip_date?->format('d/m/Y') }} •
                        {{ $idaDeparture ? Carbon::parse($idaDeparture)->format('H:i') : '--:--' }}
                    @endif
                </dd>
                @if ($trip)
                    <dd class="text-xs break-words text-gray-500 dark:text-gray-400">
                        {{ $trip->bus?->name }} — {{ $trip->route?->name }}
                    </dd>
                @endif
            </div>

            @if ($record?->is_round_trip && !$isReturnLegTicket && $returnTrip)
                <div class="min-w-0">
                    <dt class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Vuelta actual</dt>
                    <dd class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                        {{ $returnTrip->trip_date?->format('d/m/Y') }} •
                        {{ $vueltaDeparture ? Carbon::parse($vueltaDeparture)->format('H:i') : '--:--' }}
                    </dd>
                    <dd class="text-xs break-words text-gray-500 dark:text-gray-400">
                        {{ $returnTrip->bus?->name }} — {{ $returnTrip->route?->name }}
                    </dd>
                </div>
            @endif
        </dl>

        <div class="flex shrink-0 flex-col items-end justify-center border-l border-gray-200 pl-4 text-right dark:border-gray-700">
            <div class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Asiento</div>
            @if ($seat)
                <div class="text-3xl font-bold leading-none tabular-nums text-primary-600 dark:text-primary-400">
                    {{ $seat->seat_number }}
                </div>
            @else
                <div class="mt-1 font-semibold text-amber-600 dark:text-amber-400">
                    {{ $isPendingDate ? 'A asignar' : '—' }}
                </div>
            @endif
        </div>
    </div>

    @if ($record?->wasRescheduled())
        <div
            class="flex items-center gap-2 rounded-lg border border-orange-300 bg-orange-50 px-4 py-2 text-xs font-semibold text-orange-800 dark:border-orange-700 dark:bg-orange-900/20 dark:text-orange-200">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4 shrink-0 text-orange-600 dark:text-orange-400" />
            Este boleto fue reprogramado anteriormente
            ({{ $record->dateChanges()->count() }} cambio(s) registrados).
        </div>
    @endif
</div>
