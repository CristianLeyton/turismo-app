{{-- resources/views/tickets/summary.blade.php --}}

<div class="space-y-4">
    {{ json_encode($this->data, JSON_PRETTY_PRINT) }}

    <h2 class="text-xl font-bold">Resumen del boleto</h2>

    <div>
        <strong>Origen:</strong> {{ $get('origin_location_id') }}
    </div>

    <div>
        <strong>Destino:</strong> {{ $get('destination_location_id') }}
    </div>

    <div>
        <strong>Fecha ida:</strong> {{ $get('departure_date') }}
    </div>

    @if ($get('is_round_trip'))
        <div>
            <strong>Fecha vuelta:</strong> {{ $get('return_date') }}
        </div>
    @endif

    <div>
        <strong>Pasajeros:</strong> {{ $get('passengers_count') }}
    </div>

    <div>
        <strong>Asientos seleccionados:</strong>
        {{ implode(', ', $get('selected_seats') ?? []) }}
    </div>

</div>