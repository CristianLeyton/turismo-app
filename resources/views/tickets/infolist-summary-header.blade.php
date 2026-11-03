{{-- resources/views/tickets/infolist-summary-header.blade.php --}}

@php
    use Carbon\Carbon;

    /* Resuelve el registro tanto desde el infolist Filament (ViewEntry le
       pasa $getRecord() como closure) como al incluir la vista manualmente
       con ['record' => $ticket] (p. ej. la página pública /boletos/{token}). */
    $record = $record ?? null;
    if (! $record && isset($getRecord) && is_callable($getRecord)) {
        $record = $getRecord();
    }

    $originLocation = $record?->origin;
    $destinationLocation = $record?->destination;
    $trip = $record?->trip;
    $schedule = $trip?->schedule;
    $route = $trip?->route;
    $bus = $trip?->bus;

    /* El tramo sale sólo de la marca del boleto (is_return_leg): ni el
       precio ni el viaje lo deciden. */
    $isReturnLeg = $record?->isReturnLeg() ?? false;
    $isRoundTrip = $record && ($isReturnLeg || (bool) $record->is_round_trip);
    $legLabel = $isReturnLeg ? 'Vuelta' : 'Ida';
    $seatLabel = $isReturnLeg ? 'Asiento vuelta' : 'Asiento ida';

    /* Ida de un diferido: la sección "Vuelta" se arma con el boleto de
       vuelta hermano, que refleja su viaje y asiento actuales (aunque se
       haya reprogramado). Sin hermano (datos viejos), cae en el viaje de
       vuelta guardado en la ida. */
    $returnTicket = null;
    $outboundTicket = null;
    $returnLegTrip = null;
    $returnLegSeat = null;
    $returnLegDeparture = null;
    $returnLegArrival = null;

    if ($record && $isRoundTrip && ! $isReturnLeg) {
        $returnTicket = $record->roundTripReturnTicket();

        if ($returnTicket) {
            $returnLegTrip = $returnTicket->trip;
            $returnLegSeat = $returnTicket->seat?->seat_number;
            $returnLegDeparture = $returnTicket->getBoardingDepartureTime() ?? $returnLegTrip?->schedule?->departure_time;
            $returnLegArrival = $returnTicket->getBoardingArrivalTime() ?? $returnLegTrip?->schedule?->arrival_time;
        } elseif ($record->return_trip_id && $record->returnTrip) {
            $returnLegTrip = $record->returnTrip;
            $returnLegSeat = \App\Models\Ticket::query()
                ->where('passenger_id', $record->passenger_id)
                ->where('trip_id', $returnLegTrip->id)
                ->whereNotNull('seat_id')
                ->first()?->seat?->seat_number;
            $returnLegDeparture = $record->getBoardingDepartureTime(null, true) ?? $returnLegTrip->schedule?->departure_time;
            $returnLegArrival = $record->getBoardingArrivalTime(null, true) ?? $returnLegTrip->schedule?->arrival_time;
        }
    }

    if ($record && $isReturnLeg) {
        $outboundTicket = $record->roundTripOutboundTicket();
    }
@endphp

<div class="space-y-6">

    {{-- ================== RESUMEN GENERAL ================== --}}
    <div
        class="rounded-xl border p-5
                bg-white dark:bg-gray-900
                border-gray-200 dark:border-gray-700">

        <div class="flex flex-col md:flex-row md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    <span class="text-fuchsia-600">{{ $originLocation?->name ?? 'Origen' }} </span>
                    →
                    <span class="text-fuchsia-600">{{ $destinationLocation?->name ?? 'Destino' }}</span>
                </h2>

                @if ($trip)
                    @php
                        $legDepartureTime = $record->getBoardingDepartureTime() ?? $schedule?->departure_time;
                        $legArrivalTime = $record->getBoardingArrivalTime() ?? $schedule?->arrival_time;
                    @endphp
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <strong class="font-semibold"> {{ $legLabel }}: </strong>
                        {{ $trip->trip_date ? Carbon::parse($trip->trip_date)->format('d/m/Y') : '--/--/----' }}
                        •
                        {{ $legDepartureTime ? Carbon::parse($legDepartureTime)->format('H:i') : '--:--' }}
                        →
                        {{ $legArrivalTime ? Carbon::parse($legArrivalTime)->format('H:i') : '--:--' }}
                    </p>

                    @if ($bus)
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-semibold">Colectivo:</span>
                        <span class="text-fuchsia-600 dark:text-fuchsia-400">{{ $bus->name }}</span>
                    </p>
                    @endif

                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-semibold"> Ruta: </span>
                        <span class="text-fuchsia-600 dark:text-fuchsia-400">
                            {{ $route?->name ?? 'No especificada' }}
                        </span>
                    </p>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <strong class="font-semibold"> {{ $legLabel }}: </strong>
                        @if ($record?->isPendingDate())
                            <span class="font-semibold text-amber-600 dark:text-amber-400">Fecha y horario a confirmar</span>
                        @else
                            No hay información del viaje
                        @endif
                    </p>
                @endif
            </div>
            <div class="text-left md:text-right">
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase">
                    {{ $seatLabel }}
                </div>
                <div class="text-3xl font-bold text-fuchsia-600 dark:text-fuchsia-400">
                    {{ $record?->seat?->seat_number ?? ($record?->isPendingDate() ? 'A asignar' : '—') }}
                </div>
            </div>
        </div>

        {{-- Ida de un diferido: datos de su vuelta --}}
        @if ($isRoundTrip && ! $isReturnLeg)
            <div
                class="flex flex-col md:flex-row gap-3 justify-between border-t border-gray-200 dark:border-gray-700 md:items-center mt-3 pt-3">
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    <strong class="font-semibold"> Vuelta: </strong>
                    @if ($returnLegTrip)
                        {{ $returnLegTrip->trip_date ? Carbon::parse($returnLegTrip->trip_date)->format('d/m/Y') : '--/--/----' }}
                        •
                        {{ $returnLegDeparture ? Carbon::parse($returnLegDeparture)->format('H:i') : '--:--' }}
                        →
                        {{ $returnLegArrival ? Carbon::parse($returnLegArrival)->format('H:i') : '--:--' }}

                        @if ($returnLegTrip->bus)
                        <br> <span class="font-semibold">Colectivo:</span>
                        <span class="text-fuchsia-600 dark:text-fuchsia-400">{{ $returnLegTrip->bus->name }}</span>
                        @endif
                        <br>
                        <span class="font-semibold">Ruta:</span>
                        <span class="text-fuchsia-600 dark:text-fuchsia-400">
                            {{ $returnLegTrip->route?->name ?? 'No especificada' }}</span>
                    @else
                        <span class="font-semibold text-amber-600 dark:text-amber-400">Fecha y horario a confirmar</span>
                    @endif

                    @if ($returnTicket)
                        <br> <span class="font-semibold">Boleto de vuelta N°{{ $returnTicket->id }}</span>
                    @endif
                </div>

                @if ($returnLegTrip)
                    <div class="text-left md:text-right">
                        <div class="text-sm text-gray-500 dark:text-gray-400 uppercase">
                            Asiento vuelta
                        </div>
                        <div class="text-3xl font-bold text-fuchsia-600 dark:text-fuchsia-400">
                            {{ $returnLegSeat ?? '—' }}
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Vuelta de un diferido: referencia a su boleto de ida --}}
        @if ($isReturnLeg)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div
                    class="bg-sky-100 border border-sky-400 text-sky-700 px-4 py-3 rounded dark:bg-sky-800 dark:border-sky-700 dark:text-sky-300">
                    <strong class="font-semibold">Tramo de vuelta de un pasaje diferido</strong><br>
                    @if ($outboundTicket)
                        <span class="text-sm">Boleto de ida N°{{ $outboundTicket->id }}. El precio del pasaje está en ese boleto.</span>
                    @else
                        <span class="text-sm">El boleto de ida de este pasaje no está disponible.</span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <h2 class="text-2xl font-bold">Pasajero</h2>
    <div class="rounded-xl border p-5 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 mb-4">

        @if ($record->passenger)
            <div class="flex justify-between gap-3 mb-1 flex-nowrap">
                <div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $record->passenger->last_name ?? '' }} {{ $record->passenger->first_name ?? '' }} 
                    </div>

                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            <span class="font-semibold">Documento: </span>
                            {{ $record->passenger->dni ?? 'No especificado' }}
                        </div>
                    </div>

                    @if (!empty($record->passenger->phone_number))
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            <span class="font-semibold">Teléfono: </span> {{ $record->passenger->phone_number }}
                        </div>
                    @endif
                </div>

                <span
                    class="self-start md:self-center
                                 px-3 py-1 rounded-full text-xs font-medium
                                 bg-gray-100 dark:bg-gray-800
                                 text-fuchsia-600 dark:text-fuchsia-400">
                    Adulto
                </span>
            </div>
        @endif
        {{-- Información del menor acompañante --}}
        @if ($record->travels_with_child && $record->passenger && $record->passenger->children->isNotEmpty())
            <div class="mt-4">
                @foreach ($record->passenger->children as $child)
                    <div
                        class="border-l-4 border-fuchsia-500 pl-4 py-2 my-2
                                bg-gray-50 dark:bg-gray-800 rounded">
                        <div
                            class="text-sm font-semibold text-fuchsia-600 dark:text-fuchsia-400 flex flex-row justify-between gap-3 mb-1 flex-nowrap">
                            <span> Acompañante </span>
                            <span
                                class="self-start md:self-center
                                 px-3 py-1 rounded-full text-xs font-medium
                                 bg-gray-100 dark:bg-gray-900
                                 text-fuchsia-600 dark:text-fuchsia-400 mr-3">
                                Menor
                            </span>
                        </div>
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            {{ $child->last_name ?? '' }}
                            {{ $child->first_name ?? '' }}

                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <span class="font-semibold"> Documento: </span>
                                {{ $child->dni ?? 'No especificado' }}
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Información de mascotas acompañantes --}}
        @if ($record->travels_with_pets && !empty($record->pet_names))
            <div class="mt-4">
                <div
                    class="border-l-4 border-orange-500 pl-4 py-2 my-2
                            bg-gray-50 dark:bg-gray-800 rounded">
                    <div
                        class="text-sm font-semibold text-orange-600 dark:text-orange-400 flex flex-row justify-between gap-3 mb-1 flex-nowrap">
                        <span> Acompañante </span>
                        <span
                            class="self-start md:self-center
                             px-3 py-1 rounded-full text-xs font-medium
                             bg-gray-100 dark:bg-gray-900
                             text-orange-600 dark:text-orange-400 mr-3">
                            Mascota(s)
                        </span>
                    </div>
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-semibold"> Mascotas: </span>
                            {{ $record->pet_names ?? 'No especificado' }}
                        </div>

                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            <span class="font-semibold"> Cantidad: </span>
                            {{ $record->pet_count ?? 'No especificado' }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

</div>
