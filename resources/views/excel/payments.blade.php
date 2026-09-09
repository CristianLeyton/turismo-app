@php
    $fmt = fn ($value) => number_format((float) $value, 0, ',', '.');
@endphp
<table>
    <tr>
        <td colspan="4" style="font-size: 14px; font-weight: bold;">Pagos recibidos</td>
    </tr>
    <tr>
        <td colspan="4" style="font-size: 11px; color: #555555;">
            Período:
            {{ $filters['from'] ? \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') : 'inicio' }}
            al
            {{ $filters['to'] ? \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') : 'hoy' }}
        </td>
    </tr>
    <tr>
        <th>Fecha de pago</th>
        <th>Vendedor</th>
        <th style="text-align: center;">Método</th>
        <th style="text-align: right;">Monto</th>
    </tr>
    @forelse ($payments as $payment)
        <tr>
            <td>{{ $payment->payment_date?->format('d/m/Y') ?? '-' }}</td>
            <td>{{ $payment->user ? trim($payment->user->name . ' ' . ($payment->user->surname ?? '')) : '-' }}</td>
            <td style="text-align: center;">{{ $payment->payment_method === 'cash' ? 'Efectivo' : 'Transferencia' }}</td>
            <td style="text-align: right;">${{ $fmt($payment->amount) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="4">No hay pagos para el período seleccionado.</td>
        </tr>
    @endforelse
    <tr>
        <td colspan="2" style="font-weight: bold;">Totales</td>
        <td style="font-weight: bold;">Cantidad: {{ $totals['count'] }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2"></td>
        <td style="font-weight: bold;">Efectivo</td>
        <td style="font-weight: bold; text-align: right;">${{ $fmt($totals['cash']) }}</td>
    </tr>
    <tr>
        <td colspan="2"></td>
        <td style="font-weight: bold;">Transferencia</td>
        <td style="font-weight: bold; text-align: right;">${{ $fmt($totals['transfer']) }}</td>
    </tr>
    <tr>
        <td colspan="2"></td>
        <td style="font-weight: bold;">Total cobrado</td>
        <td style="font-weight: bold; text-align: right;">${{ $fmt($totals['total']) }}</td>
    </tr>
</table>
