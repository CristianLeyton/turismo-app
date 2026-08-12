@php
    $typeLabel = match ($filters['type']) {
        'tickets' => 'Solo boletos',
        'payments' => 'Solo pagos',
        default => 'Ventas y pagos',
    };
    $paymentLabel = match ($filters['payment']) {
        'cash' => 'Efectivo',
        'transfer' => 'Transferencia',
        default => 'Todos',
    };
    $fmt = fn ($value) => number_format((float) $value, 0, ',', '.');
@endphp
<table>
    <tr>
        <td colspan="9" style="font-size: 14px; font-weight: bold;">
            Detalle de ventas de {{ $user->name }} {{ $user->surname }}
        </td>
    </tr>
    <tr>
        <td colspan="9" style="font-size: 11px; color: #555555;">
            Período:
            {{ $filters['from'] ? \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') : 'inicio' }}
            al
            {{ $filters['to'] ? \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') : 'hoy' }}
            | Tipo: {{ $typeLabel }}
            | Pago: {{ $paymentLabel }}
            | Total: {{ $totals['count'] }}
            {{ $totals['count'] === 1 ? 'registro' : 'registros' }}
            ({{ $totals['tickets_count'] }} boletos · {{ $totals['payments_count'] }} pagos)
        </td>
    </tr>
    <tr>
        <th>N°</th>
        <th>Fecha</th>
        <th>Salida</th>
        <th>Ruta</th>
        <th>Pasajero</th>
        <th>DNI</th>
        <th>Asiento</th>
        <th>Pago</th>
        <th>Monto</th>
    </tr>
    @forelse ($records as $record)
        @if ($record['type'] === 'payment')
            <tr>
                <td>Pago #{{ $record['id'] }}</td>
                <td>{{ $record['model']->payment_date?->format('d/m/Y') ?? '-' }}</td>
                <td>-</td>
                <td>Pago recibido</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>{{ $record['payment_method'] === 'cash' ? 'Efectivo' : ($record['payment_method'] === 'transfer' ? 'Transferencia' : '-') }}</td>
                <td>${{ $fmt($record['amount']) }}</td>
            </tr>
        @else
            @php($ticket = $record['model'])
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
                <td>${{ $fmt($record['amount']) }}</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="9">No hay registros para el período, tipo y método de pago seleccionados.</td>
        </tr>
    @endforelse
    @if ($filters['type'] !== 'payments')
        <tr>
            <td colspan="3" style="font-weight: bold;">Totales ventas</td>
            <td colspan="4"></td>
            <td style="font-weight: bold;">Efectivo</td>
            <td style="font-weight: bold;">${{ $fmt($totals['cash']) }}</td>
        </tr>
        <tr>
            <td colspan="3"></td>
            <td colspan="4"></td>
            <td style="font-weight: bold;">Transferencia</td>
            <td style="font-weight: bold;">${{ $fmt($totals['transfer']) }}</td>
        </tr>
        <tr>
            <td colspan="3" style="font-weight: bold;">Total boletos: {{ $totals['tickets_count'] }}</td>
            <td colspan="4"></td>
            <td style="font-weight: bold;">Total ventas</td>
            <td style="font-weight: bold;">${{ $fmt($totals['ventas_total']) }}</td>
        </tr>
    @endif
    @if ($filters['type'] !== 'tickets')
        <tr>
            <td colspan="3" style="font-weight: bold;">Pagos recibidos: {{ $totals['payments_count'] }}</td>
            <td colspan="4"></td>
            <td style="font-weight: bold;">Pagos efectivo</td>
            <td style="font-weight: bold;">${{ $fmt($totals['payments_cash']) }}</td>
        </tr>
        <tr>
            <td colspan="3"></td>
            <td colspan="4"></td>
            <td style="font-weight: bold;">Pagos transferencia</td>
            <td style="font-weight: bold;">${{ $fmt($totals['payments_transfer']) }}</td>
        </tr>
        <tr>
            <td colspan="3" style="font-weight: bold;">Total pagos</td>
            <td colspan="4"></td>
            <td style="font-weight: bold;">Total pagos</td>
            <td style="font-weight: bold;">${{ $fmt($totals['payments_total']) }}</td>
        </tr>
    @endif
    @if ($filters['type'] === 'all')
        <tr>
            <td colspan="3" style="font-weight: bold;">Saldo (ventas − pagos)</td>
            <td colspan="4"></td>
            <td style="font-weight: bold;">Saldo</td>
            <td style="font-weight: bold;">${{ $fmt($totals['saldo']) }}</td>
        </tr>
    @endif
</table>
