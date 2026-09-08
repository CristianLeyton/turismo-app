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
    $salesCount = $records->where('type', 'sale')->count();
    $groupedPaymentsCount = $records->where('type', 'payment')->count();
@endphp
<table>
    <tr>
        <td colspan="6" style="font-size: 14px; font-weight: bold;">
            Detalle de ventas de {{ $user->name }} {{ $user->surname }}
        </td>
    </tr>
    <tr>
        <td colspan="6" style="font-size: 11px; color: #555555;">
            Período:
            {{ $filters['from'] ? \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') : 'inicio' }}
            al
            {{ $filters['to'] ? \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') : 'hoy' }}
            | Tipo: {{ $typeLabel }}
            | Pago: {{ $paymentLabel }}
            | Total:
            @if ($filters['type'] !== 'payments')
                {{ $salesCount }} {{ $salesCount === 1 ? 'venta' : 'ventas' }}
                ({{ $totals['tickets_count'] }} boletos)
            @endif
            @if ($filters['type'] === 'all')
                ·
            @endif
            @if ($filters['type'] !== 'tickets')
                {{ $groupedPaymentsCount }} {{ $groupedPaymentsCount === 1 ? 'pago' : 'pagos' }}
            @endif
        </td>
    </tr>
    <tr>
        <th>N°</th>
        <th>Fecha</th>
        <th style="text-align: center;">Boletos</th>
        <th style="text-align: center;">Pago</th>
        <th style="text-align: right;">Monto</th>
        <th>Tipo</th>
    </tr>
    @forelse ($records as $record)
        @if ($record['type'] === 'payment')
            <tr>
                <td>Pago #{{ $record['id'] }}</td>
                <td>{{ $record['model']->payment_date?->format('d/m/Y') ?? '-' }}</td>
                <td>-</td>
                <td>{{ ($record['payment_methods'][0] ?? null) === 'cash' ? 'Efectivo' : (($record['payment_methods'][0] ?? null) === 'transfer' ? 'Transferencia' : '-') }}</td>
                <td>${{ $fmt($record['amount']) }}</td>
                <td>Pago recibido</td>
            </tr>
        @else
            <tr>
                <td>Venta #{{ $record['id'] }}</td>
                <td>{{ $record['date']?->format('d/m/Y H:i') ?? '-' }}</td>
                <td>{{ $record['tickets_count'] }}</td>
                <td>
                    @php($methods = collect($record['payment_methods'])->map(fn ($m) => $m === 'cash' ? 'Efectivo' : ($m === 'transfer' ? 'Transferencia' : '-'))->implode(' / '))
                    {{ $methods !== '' ? $methods : '-' }}
                </td>
                <td>${{ $fmt($record['amount']) }}</td>
                <td>Venta</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="6">No hay registros para el período, tipo y método de pago seleccionados.</td>
        </tr>
    @endforelse
    @if ($filters['type'] !== 'payments')
        <tr>
            <td colspan="3" style="font-weight: bold;">Total boletos: {{ $totals['tickets_count'] }}</td>
            <td style="font-weight: bold;">Efectivo</td>
            <td style="font-weight: bold;">${{ $fmt($totals['cash']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3"></td>
            <td style="font-weight: bold;">Transferencia</td>
            <td style="font-weight: bold;">${{ $fmt($totals['transfer']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" style="font-weight: bold;">Ventas: {{ $salesCount }}</td>
            <td style="font-weight: bold;">Total ventas</td>
            <td style="font-weight: bold;">${{ $fmt($totals['ventas_total']) }}</td>
            <td></td>
        </tr>
    @endif
    @if ($filters['type'] !== 'tickets')
        <tr>
            <td colspan="3" style="font-weight: bold;">Pagos recibidos: {{ $groupedPaymentsCount }}</td>
            <td style="font-weight: bold;">Pagos efectivo</td>
            <td style="font-weight: bold;">${{ $fmt($totals['payments_cash']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3"></td>
            <td style="font-weight: bold;">Pagos transferencia</td>
            <td style="font-weight: bold;">${{ $fmt($totals['payments_transfer']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" style="font-weight: bold;">Total pagos</td>
            <td style="font-weight: bold;">Total pagos</td>
            <td style="font-weight: bold;">${{ $fmt($totals['payments_total']) }}</td>
            <td></td>
        </tr>
    @endif
    @if ($filters['type'] === 'all')
        <tr>
            <td colspan="3" style="font-weight: bold;">Saldo (ventas − pagos)</td>
            <td style="font-weight: bold;">Saldo</td>
            <td style="font-weight: bold;">${{ $fmt($totals['saldo']) }}</td>
            <td></td>
        </tr>
    @endif
</table>
