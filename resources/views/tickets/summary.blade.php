{{-- resources/views/tickets/summary.blade.php --}}

@php
    use Carbon\Carbon;

    $bus = $get('bus_id') ? \App\Models\Bus::find($get('bus_id')) : null;
    $originLocation = \App\Models\Location::find($get('origin_location_id'));
    $destinationLocation = \App\Models\Location::find($get('destination_location_id'));

    $trip = $get('trip_id') ? \App\Models\Trip::find($get('trip_id')) : null;
    $returnTrip = $get('return_trip_id') ? \App\Models\Trip::find($get('return_trip_id')) : null;

    $schedule = $get('schedule_id') ? \App\Models\Schedule::find($get('schedule_id')) : null;
    $returnSchedule = $get('return_schedule_id') ? \App\Models\Schedule::find($get('return_schedule_id')) : null;

    $route = $trip?->route ?? $schedule?->route;
    $returnRoute = $returnTrip?->route ?? $returnSchedule?->route;

    $idaBoardingDeparture = $route && $schedule && $get('origin_location_id')
        ? $route->getDepartureTimeForStop($get('origin_location_id'), $schedule)
        : $schedule?->departure_time;

    $idaBoardingArrival = $route && $schedule && $get('destination_location_id')
        ? $route->getArrivalTimeForStop($get('destination_location_id'), $schedule)
        : $schedule?->arrival_time;

    $vueltaBoardingDeparture = $returnRoute && $returnSchedule && $get('destination_location_id')
        ? $returnRoute->getDepartureTimeForStop($get('destination_location_id'), $returnSchedule)
        : $returnSchedule?->departure_time;

    $vueltaBoardingArrival = $returnRoute && $returnSchedule && $get('origin_location_id')
        ? $returnRoute->getArrivalTimeForStop($get('origin_location_id'), $returnSchedule)
        : $returnSchedule?->arrival_time;

    $passengers = $get('passengers') ?? [];
    $seatIds = $get('seat_ids') ?? [];
    $returnSeatIds = $get('return_seat_ids') ?? [];

    $seatNumbersByIda = (is_array($seatIds) && $trip?->bus_id)
        ? \App\Models\Seat::whereIn('id', $seatIds)->where('bus_id', $trip->bus_id)->pluck('seat_number', 'id')->toArray()
        : [];
    $seatNumbersByVuelta = (is_array($returnSeatIds) && $returnTrip?->bus_id)
        ? \App\Models\Seat::whereIn('id', $returnSeatIds)->where('bus_id', $returnTrip->bus_id)->pluck('seat_number', 'id')->toArray()
        : [];
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

                

                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    <strong class="font-semibold"> Ida: </strong>
                    {{ Carbon::parse($get('departure_date'))->format('d/m/Y') }}
                    •
                    {{ $idaBoardingDeparture ? Carbon::parse($idaBoardingDeparture)->format('H:i') : '--:--' }}
                    →
                    {{ $idaBoardingArrival ? Carbon::parse($idaBoardingArrival)->format('H:i') : '--:--' }}
                </p>

                @if($bus)
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <strong class="font-semibold">Colectivo:</strong>
                    <span class="text-fuchsia-600 dark:text-fuchsia-400">{{ $bus->name }}</span>
                </p>
                @endif

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-semibold"> Ruta: </span>
                    <span class="text-fuchsia-600 dark:text-fuchsia-400">
                        {{ $route?->name ?? 'No especificada' }}
                    </span>
                </p>
            </div>

            <div class="text-left md:text-right">
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase">
                    Pasajeros
                </div>
                <div class="text-3xl font-bold text-fuchsia-600 dark:text-fuchsia-400">
                    {{ $get('passengers_count') }}
                </div>
            </div>
        </div>

        @if ($get('is_round_trip'))
            <div
                class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                <strong class="font-semibold"> Vuelta: </strong>
                {{ Carbon::parse($get('return_date'))->format('d/m/Y') }}
                •
                {{ $vueltaBoardingDeparture ? Carbon::parse($vueltaBoardingDeparture)->format('H:i') : '--:--' }}
                →
                {{ $vueltaBoardingArrival ? Carbon::parse($vueltaBoardingArrival)->format('H:i') : '--:--' }}

                @if($returnTrip?->bus)
                <br> <span class="font-semibold">Colectivo:</span>
                <span class="text-fuchsia-600 dark:text-fuchsia-400">{{ $returnTrip->bus->name }}</span>
                @endif
                <br> <span class="font-semibold">Ruta:</span>
                <span class="text-fuchsia-600 dark:text-fuchsia-400">
                    {{ $returnRoute?->name ?? 'No especificada' }}</span>
            </div>
        @endif
    </div>

    {{-- ================== PASAJEROS ================== --}}
    <div class="space-y-4">

        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">
            Pasajeros
        </h3>

        @foreach ($passengers as $index => $passenger)
            @php
                static $passengerCounter = 0;
                $currentPassengerIndex = $passengerCounter++;

                $seatId = null;
                $returnSeatId = null;

                if (is_array($seatIds) && count($seatIds) > 0) {
                    if (count($seatIds) === 1) {
                        $seatId = reset($seatIds);
                    } else {
                        $seatId = $seatIds[$currentPassengerIndex] ?? null;
                    }
                }

                if (is_array($returnSeatIds) && count($returnSeatIds) > 0) {
                    if (count($returnSeatIds) === 1) {
                        $returnSeatId = reset($returnSeatIds);
                    } else {
                        $returnSeatId = $returnSeatIds[$currentPassengerIndex] ?? null;
                    }
                }

                $seatNumber = ($seatId !== null && isset($seatNumbersByIda[$seatId])) ? $seatNumbersByIda[$seatId] : $seatId;
                $returnSeatNumber = ($returnSeatId !== null && isset($seatNumbersByVuelta[$returnSeatId])) ? $seatNumbersByVuelta[$returnSeatId] : $returnSeatId;
            @endphp

            <div
                class="rounded-xl border p-5
                        bg-white dark:bg-gray-900
                        border-gray-200 dark:border-gray-700">

                {{-- Datos principales --}}
                <div class="flex justify-between gap-3 mb-1 flex-nowrap">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                            Pasajero N°{{ is_numeric($index) ? $index + 1 : 1 }}
                        </div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $passenger['last_name'] ?? '' }}
                            {{ $passenger['first_name'] ?? '' }}
                        </div>

                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <span class="font-semibold">Documento: </span>
                                {{ $passenger['dni'] ?? 'No especificado' }}
                            </div>
                        </div>

                        @if (!empty($passenger['phone_number']))
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <span class="font-semibold">Teléfono: </span> {{ $passenger['phone_number'] }}
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

                {{-- Menor acompañante --}}
                @if (!empty($passenger['travels_with_child']) && !empty($passenger['child_data']))
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
                            {{ $passenger['child_data']['last_name'] ?? '' }}
                            {{ $passenger['child_data']['first_name'] ?? '' }}

                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <span class="font-semibold"> Documento: </span>
                                {{ $passenger['child_data']['dni'] ?? 'No especificado' }}
                            </div>

                        </div>
                    </div>
                @endif

                {{-- Mascotas acompañantes --}}
                @if (!empty($passenger['travels_with_pets']) && !empty($passenger['pet_data']))
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
                                {{ $passenger['pet_data']['pet_names'] ?? 'No especificado' }}
                            </div>

                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <span class="font-semibold"> Cantidad: </span>
                                {{ $passenger['pet_data']['pet_count'] ?? 'No especificado' }}
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Asientos (más pequeños y discretos) --}}
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    <span class="font-semibold ">Asientos: </span>
                </div>
                <div class="flex gap-3 mb-2">
                    <div
                        class="flex items-center gap-2
                                px-3 py-2 rounded-lg
                                bg-gray-100 dark:bg-gray-800">
                        <span class="text-xs text-gray-500 dark:text-gray-400 uppercase">
                            Ida
                        </span>
                        <span class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            {{ $seatNumber ?? '—' }}
                        </span>
                    </div>

                    @if ($get('is_round_trip'))
                        <div
                            class="flex items-center gap-2
                                    px-3 py-2 rounded-lg
                                    bg-gray-100 dark:bg-gray-800">
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase">
                                Vuelta
                            </span>
                            <span class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                {{ $returnSeatNumber ?? '—' }}
                            </span>
                        </div>
                    @endif
                </div>

                {{-- Información de pago --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
                    <div class="flex justify-between items-center gap-4">
                        <div class="flex-1">
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                                Precio
                            </div>
                            <div class="text-lg font-semibold text-fuchsia-600 dark:text-fuchsia-400">
                                ${{ number_format($passenger['price'] ?? 0, 2, ',', '.') }}
                            </div>
                        </div>
                        <div class="flex-1 text-right">
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                                Método de pago
                            </div>
                            @if (($passenger['payment_method'] ?? null) === 'cash')
                                <span
                                    class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-lime-500 dark:text-lime-400">
                                    Efectivo
                                </span>
                            @elseif(($passenger['payment_method'] ?? null) === 'transfer')
                                <span
                                    class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-sky-500 dark:text-sky-400">
                                    Transferencia
                                </span>
                            @else
                                <span class="text-gray-500">No especificado</span>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        @endforeach


    </div>

</div>
