{{-- resources/views/tickets/summary.blade.php --}}

@php
    use Carbon\Carbon;

    $originLocation = \App\Models\Location::find($get('origin_location_id'));
    $destinationLocation = \App\Models\Location::find($get('destination_location_id'));

    $trip = $get('trip_id') ? \App\Models\Trip::find($get('trip_id')) : null;
    $returnTrip = $get('return_trip_id') ? \App\Models\Trip::find($get('return_trip_id')) : null;

    $schedule = $get('schedule_id') ? \App\Models\Schedule::find($get('schedule_id')) : null;
    $returnSchedule = $get('return_schedule_id') ? \App\Models\Schedule::find($get('return_schedule_id')) : null;

    $route = $trip?->route;
    $returnRoute = $returnTrip?->route;

    $passengers = $get('passengers') ?? [];
    $seatIds = $get('seat_ids') ?? [];
    $returnSeatIds = $get('return_seat_ids') ?? [];
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
                    {{ $schedule?->departure_time ? Carbon::parse($schedule->departure_time)->format('H:i') : '--:--' }}
                    →
                    {{ $schedule?->arrival_time ? Carbon::parse($schedule->arrival_time)->format('H:i') : '--:--' }}
                </p>

                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
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
                {{ $returnSchedule?->departure_time ? Carbon::parse($returnSchedule->departure_time)->format('H:i') : '--:--' }}
                →
                {{ $returnSchedule?->arrival_time ? Carbon::parse($returnSchedule->arrival_time)->format('H:i') : '--:--' }}


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
                    $seatId = isset($seatIds[$index]) ? $seatIds[$index] : (is_array($seatIds) && count($seatIds) === 1 ? reset($seatIds) : null);
                    $returnSeatId = isset($returnSeatIds[$index]) ? $returnSeatIds[$index] : (is_array($returnSeatIds) && count($returnSeatIds) === 1 ? reset($returnSeatIds) : null);
                @endphp

                <div
                    class="rounded-xl border p-5
                        bg-white dark:bg-gray-900
                        border-gray-200 dark:border-gray-700">

                    {{-- Datos principales --}}
                    <div class="flex justify-between gap-3 mb-1 flex-nowrap">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                                Pasajero N°{{ is_numeric($index) ? ($index + 1) : 1 }}
                            </div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $passenger['first_name'] ?? '' }}
                                {{ $passenger['last_name'] ?? '' }}
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

                    {{-- Niño acompañante --}}
                    @if (!empty($passenger['travels_with_child']) && !empty($passenger['child_data']))
                        <div
                            class="border-l-4 border-fuchsia-500 pl-4 py-2 my-2
                                bg-gray-50 dark:bg-gray-800 rounded">
                            <div class="text-sm font-semibold text-fuchsia-600 dark:text-fuchsia-400 flex flex-row justify-between gap-3 mb-1 flex-nowrap">
                                <span> Acompañante </span>
                                 <span
                            class="self-start md:self-center
                                 px-3 py-1 rounded-full text-xs font-medium
                                 bg-gray-100 dark:bg-gray-800
                                 text-fuchsia-600 dark:text-fuchsia-400 mr-3">
                            Niño
                        </span>
                            </div>
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $passenger['child_data']['first_name'] ?? '' }}
                                {{ $passenger['child_data']['last_name'] ?? '' }}

                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    <span class="font-semibold"> Documento: </span>
                                    {{ $passenger['child_data']['dni'] ?? 'No especificado' }}
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
                                {{ $seatId ?? '—' }}
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
                                    {{ $returnSeatId ?? '—' }}
                                </span>
                            </div>
                        @endif
                    </div>

                    

                </div>
            @endforeach

        
    </div>

</div>
