{{-- resources/views/tickets/summary.blade.php --}}

<div class="bg-white rounded-xl shadow-md p-6 space-y-6">

    <h2 class="text-2xl font-bold text-gray-800 border-b pb-3">
        Resumen del boleto
    </h2>

    {{-- Información del viaje --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
        <div>
            <span class="font-semibold">Origen:</span>
            <span>{{ $get('origin_location_id') }}</span>
        </div>

        <div>
            <span class="font-semibold">Destino:</span>
            <span>{{ $get('destination_location_id') }}</span>
        </div>

        <div>
            <span class="font-semibold">Fecha de ida:</span>
            <span>
                {{ \Carbon\Carbon::parse($get('departure_date'))->format('d/m/Y H:i') }}
            </span>
        </div>

        @if ($get('is_round_trip'))
            <div>
                <span class="font-semibold">Fecha de vuelta:</span>
                <span>
                    {{ \Carbon\Carbon::parse($get('return_date'))->format('d/m/Y H:i') }}
                </span>
            </div>
        @endif
    </div>

    {{-- Pasajeros --}}
    <div>
        <h3 class="font-semibold text-gray-800 mb-2">
            Pasajeros ({{ $get('passengers_count') }})
        </h3>

        <div class="space-y-2">
            @foreach ($get('passengers') ?? [] as $passenger)
                <div class="flex justify-between items-center bg-gray-50 rounded-lg px-4 py-2 text-sm">
                    <div>
                        <span class="font-medium">
                            {{ $passenger['first_name'] }} {{ $passenger['last_name'] }}
                        </span>
                        <span class="text-gray-500 ml-2">
                            DNI: {{ $passenger['dni'] }}
                        </span>
                    </div>

                    <span class="text-gray-600">
                        Asiento: {{ $get('seat_ids')[$loop->index] ?? '—' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Asientos --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
            <span class="font-semibold">Asientos ida:</span>
            <span>
                {{ implode(', ', $get('seat_ids') ?? []) }}
            </span>
        </div>

        @if ($get('is_round_trip'))
            <div>
                <span class="font-semibold">Asientos vuelta:</span>
                <span>
                    {{ implode(', ', $get('return_seat_ids') ?? []) }}
                </span>
            </div>
        @endif
    </div>

</div>