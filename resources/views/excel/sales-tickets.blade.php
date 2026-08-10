<table>
    <tr>
        <td colspan="9" style="font-size: 14px; font-weight: bold;">
            Boletos vendidos por {{ $user->name }} {{ $user->surname }}
        </td>
    </tr>
    <tr>
        <td colspan="9" style="font-size: 11px; color: #555555;">
            Período:
            {{ $filters['from'] ? \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') : 'inicio' }}
            al
            {{ $filters['to'] ? \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') : 'hoy' }}
            | Pago:
            @php
                $paymentLabel = match ($filters['payment']) {
                    'cash' => 'Efectivo',
                    'transfer' => 'Transferencia',
                    default => 'Todos',
                };
            @endphp
            {{ $paymentLabel }}
            | Total: {{ $ticketsCount }} boletos
        </td>
    </tr>
    <tr>
        <th>Boleto</th>
        <th>Venta</th>
        <th>Salida</th>
        <th>Ruta</th>
        <th>Pasajero</th>
        <th>DNI</th>
        <th>Asiento</th>
        <th>Pago</th>
        <th>Precio</th>
    </tr>
    @forelse ($tickets as $ticket)
        <tr>
            <td>{{ $ticket->id }}</td>
            <td>{{ $ticket->sale?->sale_date?->format('d/m/Y H:i') ?? '-' }}</td>
            <td>
                {{ $ticket->trip?->trip_date?->format('d/m/Y') ?? '-' }}
                {{ $ticket->trip?->schedule?->departure_time?->format('H:i') ?? '' }}
                @if ($ticket->is_round_trip)
                    (Diferido)
                @endif
            </td>
            <td>{{ $ticket->origin?->name ?? '-' }} → {{ $ticket->destination?->name ?? '-' }}</td>
            <td>
                {{ $ticket->passenger?->full_name ?? 'Pasajero no disponible' }}
                @if ($ticket->travels_with_child)
                    (Con menor)
                @endif
                @if ($ticket->travels_with_pets)
                    (Con mascota)
                @endif
            </td>
            <td>{{ $ticket->passenger?->dni ?? '-' }}</td>
            <td>{{ $ticket->seat?->seat_number ?? 'No ocupa' }}</td>
            <td>{{ $ticket->payment_method === 'cash' ? 'Efectivo' : ($ticket->payment_method === 'transfer' ? 'Transferencia' : '-') }}</td>
            <td>{{ number_format((float) $ticket->price, 0, ',', '.') }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="9">No hay boletos para el período y método de pago seleccionados.</td>
        </tr>
    @endforelse
    <tr>
        <td colspan="3" style="font-weight: bold;">Totales</td>
        <td colspan="4"></td>
        <td style="font-weight: bold;">Efectivo</td>
        <td style="font-weight: bold;">${{ number_format((float) $cashTotal, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td colspan="3"></td>
        <td colspan="4"></td>
        <td style="font-weight: bold;">Transferencia</td>
        <td style="font-weight: bold;">${{ number_format((float) $transferTotal, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td colspan="3" style="font-weight: bold;">Total boletos: {{ $ticketsCount }}</td>
        <td colspan="4"></td>
        <td style="font-weight: bold;">Total</td>
        <td style="font-weight: bold;">${{ number_format((float) $total, 0, ',', '.') }}</td>
    </tr>
</table>
