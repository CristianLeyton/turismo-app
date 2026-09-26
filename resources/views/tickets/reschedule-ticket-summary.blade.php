@php
    use Carbon\Carbon;

    // El ticket llega por viewData desde el formulario; fallback al record del esquema.
    $record = $ticket ?? $getRecord();

    $passenger = $record?->passenger;
    $trip = $record?->trip;
    $returnTrip = $record?->returnTrip;
    $seat = $record?->seat;

    $isReturnLegTicket = $record && $record->is_round_trip && is_null($record->return_trip_id);

    $idaDeparture = $record && ! $isReturnLegTicket
        ? ($record->getBoardingDepartureTime() ?? $trip?->schedule?->departure_time)
        : null;
    $idaArrival = $record && ! $isReturnLegTicket
        ? ($record->getBoardingArrivalTime() ?? $trip?->schedule?->arrival_time)
        : null;

    $vueltaDeparture = $isReturnLegTicket
        ? ($record->getBoardingDepartureTime(null, true) ?? $trip?->schedule?->departure_time)
        : ($record?->is_round_trip ? ($record->getBoardingDepartureTime(null, true) ?? $returnTrip?->schedule?->departure_time) : null);
    $vueltaArrival = $isReturnLegTicket
        ? ($record->getBoardingArrivalTime(null, true) ?? $trip?->schedule?->arrival_time)
        : ($record?->is_round_trip ? ($record->getBoardingArrivalTime(null, true) ?? $returnTrip?->schedule?->arrival_time) : null);
@endphp

<div class="space-y-2 text-sm">
    <div class="flex flex-wrap gap-x-8 gap-y-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4">
        <div>
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Pasajero</div>
            <div class="font-semibold text-gray-900 dark:text-gray-100">
                {{ $passenger ? ($passenger->last_name . ' ' . $passenger->first_name) : '—' }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">DNI {{ $passenger?->dni ?? '—' }}</div>
        </div>

        <div>
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Tramo</div>
            <div class="font-semibold text-gray-900 dark:text-gray-100">
                {{ $record?->origin?->name }} → {{ $record?->destination?->name }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ $isReturnLegTicket ? 'Tramo de vuelta (diferido)' : ($record?->is_round_trip ? 'Ida de un diferido' : 'Solo ida') }}
            </div>
        </div>

        <div>
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">
                {{ $isReturnLegTicket ? 'Vuelta actual' : 'Ida actual' }}
            </div>
            <div class="font-semibold text-gray-900 dark:text-gray-100">
                @if ($isReturnLegTicket)
                    {{ $trip?->trip_date?->format('d/m/Y') }} •
                    {{ $vueltaDeparture ? Carbon::parse($vueltaDeparture)->format('H:i') : '--:--' }}
                @else
                    {{ $trip?->trip_date?->format('d/m/Y') }} •
                    {{ $idaDeparture ? Carbon::parse($idaDeparture)->format('H:i') : '--:--' }}
                @endif
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ $trip?->bus?->name }} — {{ $trip?->route?->name }}
            </div>
        </div>

        @if ($record?->is_round_trip && ! $isReturnLegTicket && $returnTrip)
            <div>
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Vuelta actual</div>
                <div class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ $returnTrip->trip_date?->format('d/m/Y') }} •
                    {{ $vueltaDeparture ? Carbon::parse($vueltaDeparture)->format('H:i') : '--:--' }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $returnTrip->bus?->name }} — {{ $returnTrip->route?->name }}
                </div>
            </div>
        @endif

        <div class="text-right">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Asiento actual</div>
            <div class="text-2xl font-bold text-fuchsia-600 dark:text-fuchsia-400">
                {{ $seat?->seat_number ?? '—' }}
            </div>
        </div>
    </div>

    @if ($record?->wasRescheduled())
        <div class="rounded-lg border border-orange-300 bg-orange-50 dark:bg-orange-900/20 dark:border-orange-700 px-4 py-2 text-orange-800 dark:text-orange-200 text-xs font-semibold">
            ⚠ Este boleto fue reprogramado anteriormente
            ({{ $record->dateChanges()->count() }} cambio(s) registrados).
        </div>
    @endif
</div>
