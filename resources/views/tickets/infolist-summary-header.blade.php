{{-- resources/views/tickets/infolist-summary-header.blade.php --}}

@php
    use Carbon\Carbon;

    // Obtener el registro actual del infolist
    $record = $getRecord();

    // Si no hay registro, mostrar valores por defecto
    if (!$record) {
        $originLocation = null;
        $destinationLocation = null;
        $trip = null;
        $returnTrip = null;
        $schedule = null;
        $returnSchedule = null;
        $route = null;
        $returnRoute = null;
    } else {
        $originLocation = $record->origin;
        $destinationLocation = $record->destination;
        $trip = $record->trip;
        $returnTrip = $record->returnTrip;
        $schedule = $trip?->schedule;
        $returnSchedule = $returnTrip?->schedule;
        $route = $trip?->route;
        $returnRoute = $returnTrip?->route;
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
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <strong class="font-semibold"> Ida: </strong>
                        {{ $trip->trip_date ? Carbon::parse($trip->trip_date)->format('d/m/Y') : '--/--/----' }}
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
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <strong class="font-semibold"> Ida: </strong>
                        No hay información del viaje
                    </p>
                @endif
            </div>
            <div class="text-left md:text-right">
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase">
                    Asiento ida
                </div>
                <div class="text-3xl font-bold text-fuchsia-600 dark:text-fuchsia-400">
                    {{ $record->seat?->seat_number ?? ($record->seat?->seat_number ?? '—') }}
                </div>
            </div>
        </div>

        @if ($record?->is_round_trip)
            @if ($record->return_trip_id && $returnTrip)
                <div class="flex flex-col md:flex-row gap-3 justify-between border-t border-gray-200 dark:border-gray-700 md:items-center mt-3 pt-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <strong class="font-semibold"> Vuelta: </strong>
                        {{ $returnTrip->trip_date ? Carbon::parse($returnTrip->trip_date)->format('d/m/Y') : '--/--/----' }}
                        •
                        {{ $returnSchedule?->departure_time ? Carbon::parse($returnSchedule->departure_time)->format('H:i') : '--:--' }}
                        →
                        {{ $returnSchedule?->arrival_time ? Carbon::parse($returnSchedule->arrival_time)->format('H:i') : '--:--' }}


                        <br>
                        <p class="mt-1">
                            <span class="font-semibold ">Ruta:</span>
                            <span class="text-fuchsia-600 dark:text-fuchsia-400">
                                {{ $returnRoute?->name ?? 'No especificada' }}</span>
                        </p>
                    </div>

                    <div class="text-left md:text-right">
                        <div class="text-sm text-gray-500 dark:text-gray-400 uppercase">
                            Asiento vuelta
                        </div>
                        <div class="text-3xl font-bold text-fuchsia-600 dark:text-fuchsia-400">
                            {{-- Buscar el asiento asignado a este ticket en el viaje de vuelta --}}
                            @php
                                $returnSeatNumber = '—';
                                if ($returnTrip && $record) {
                                    // Buscar si este ticket tiene un asiento asignado en el viaje de vuelta
                                    $returnTicketForSeat = \App\Models\Ticket::where(
                                        'passenger_id',
                                        $record->passenger_id,
                                    )
                                        ->where('trip_id', $returnTrip->id)
                                        ->whereNotNull('seat_id')
                                        ->first();

                                    if ($returnTicketForSeat && $returnTicketForSeat->seat) {
                                        $returnSeatNumber = $returnTicketForSeat->seat->seat_number;
                                    }
                                }
                            @endphp
                            {{ $returnSeatNumber }}
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="bg-sky-100 border border-sky-400 text-sky-700 px-4 py-3 rounded dark:bg-sky-800 dark:border-sky-700 dark:text-sky-300">
                        <strong class="font-semibold">Viaje de vuelta</strong><br>
                        <span class="text-sm">Este ticket es el viaje de vuelta de un pasaje diferido. </span>
                    </div>
                </div>
            @endif
        @endif
    </div>

    <h2 class="text-2xl font-bold">Pasajero</h2>
    <div class="rounded-xl border p-5 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 mb-4">

        @if ($record->passenger)
            <div class="flex justify-between gap-3 mb-1 flex-nowrap">
                <div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $record->passenger->first_name ?? '' }} {{ $record->passenger->last_name ?? '' }}
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
                            {{ $child->first_name ?? '' }}
                            {{ $child->last_name ?? '' }}

                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <span class="font-semibold"> Documento: </span>
                               {{ $child->dni ?? 'No especificado' }}
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
