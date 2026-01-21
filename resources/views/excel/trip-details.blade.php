<table>
    <thead>
        <tr>
            <th>Boleto</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Teléfono</th>
            <th>Asiento</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Viaja con</th>
        </tr>
    </thead>
    <tbody>
        @if($passengersCount > 0)
            @foreach($passengers as $passenger)
                <tr>
                    <td>{{ $passenger['ticket_id'] }}</td>
                    <td>{{ $passenger['name'] }}</td>
                    <td>{{ $passenger['dni'] }}</td>
                    <td>{{ $passenger['phone'] == 'N/A' ? '-' : $passenger['phone'] }}</td>
                    <td>{{ is_numeric($passenger['seat_number']) ? $passenger['seat_number'] : 'No ocupa' }}</td>
                    <td>{{ $passenger['origin'] }}</td>
                    <td>{{ $passenger['destination'] }}</td>
                    <td>{{ $passenger['type'] === 'child' && isset($passenger['parent_name']) ? $passenger['parent_name'] : '-' }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="8">No hay pasajeros registrados para este viaje.</td>
            </tr>
        @endif
    </tbody>
</table>
