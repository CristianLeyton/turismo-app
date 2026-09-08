<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Detalle de ventas</title>

    <style>
        @page {
            margin: 5mm;
            size: A4 landscape;
        }

        body {
            font-family: "Arial", sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        .card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            color: #c026d3;
        }

        .subtitle {
            margin-top: 4px;
            font-size: 11px;
            color: #6b7280;
        }

        .subtitle strong {
            color: #374151;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f9fafb;
        }

        th {
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            color: #6b7280;
            padding: 5px;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            font-size: 10px;
            padding: 5px;
            border-bottom: 1px solid #f3f4f6;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .sale-row td {
            background: #faf5ff;
            font-weight: bold;
        }

        .payment-row {
            background: #fffbeb;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-cash {
            background: #dcfce7;
            color: #166534;
        }

        .badge-transfer {
            background: #e0f2fe;
            color: #075985;
        }

        .badge-seat {
            background: #f5d0fe;
            color: #86198f;
        }

        .badge-round {
            background: #ffedd5;
            color: #9a3412;
        }

        .badge-payment {
            background: #fde68a;
            color: #92400e;
        }

        .price {
            font-weight: bold;
            color: #1f2937;
        }

        .payment-note {
            color: #b45309;
            font-style: italic;
        }

        .resumen {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
        }

        .resumen-head {
            width: 100%;
            border-collapse: collapse;
        }

        .resumen-head td {
            border-bottom: none;
            padding: 0;
        }

        .resumen-title {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #9ca3af;
        }

        .resumen-count {
            font-size: 9px;
            color: #9ca3af;
            text-align: right;
        }

        .rgrid {
            width: 100%;
            border-collapse: collapse;
        }

        .rgrid td {
            width: 25%;
            padding: 3px;
            border-bottom: none;
        }

        .rbox {
            border-radius: 6px;
            padding: 7px 9px;
        }

        .rlabel {
            font-size: 8.5px;
            margin: 0;
        }

        .rvalue {
            font-size: 13px;
            font-weight: bold;
            margin: 2px 0 0;
        }

        .rb-gray { background: #f9fafb; }
        .rb-gray .rlabel { color: #6b7280; }
        .rb-gray .rvalue { color: #111827; }

        .rb-emerald { background: #ecfdf5; }
        .rb-emerald .rlabel, .rb-emerald .rvalue { color: #047857; }

        .rb-sky { background: #e0f2fe; }
        .rb-sky .rlabel, .rb-sky .rvalue { color: #0369a1; }

        .rb-fuchsia { background: #fdf4ff; }
        .rb-fuchsia .rlabel, .rb-fuchsia .rvalue { color: #a21caf; }

        .rb-amber { background: #fffbeb; }
        .rb-amber .rlabel, .rb-amber .rvalue { color: #b45309; }

        .saldo {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            border: 1px solid #e5e7eb;
        }

        .saldo td {
            border-bottom: none;
            padding: 8px 10px;
        }

        .saldo-label {
            font-size: 10px;
            font-weight: bold;
            text-align: left;
        }

        .saldo-value {
            font-size: 13px;
            font-weight: bold;
            text-align: right;
        }

        .saldo-pos { background: #fefce8; }
        .saldo-pos .saldo-label, .saldo-pos .saldo-value { color: #a16207; }

        .saldo-neg { background: #fef2f2; }
        .saldo-neg .saldo-label, .saldo-neg .saldo-value { color: #b91c1c; }

        .saldo-zero { background: #f9fafb; }
        .saldo-zero .saldo-label, .saldo-zero .saldo-value { color: #4b5563; }

        .empty {
            padding: 20px;
            font-size: 11px;
            color: #6b7280;
        }
    </style>
</head>

<body>

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
        $fmt = fn ($value) => '$' . number_format((float) $value, 0, ',', '.');
        $salesCount = $records->where('type', 'sale')->count();
        $groupedPaymentsCount = $records->where('type', 'payment')->count();
    @endphp

    <div style="margin-bottom: 3mm;">
        <h1 style="font-size: 13px; color: #2b2b2b; margin: 0; text-align: left;">
            Detalle de ventas de <span style="color: #c026d3;">{{ $user->name }} {{ $user->surname }}</span>
        </h1>
    </div>

    <div class="card">
        <div class="title">Boletos vendidos y pagos recibidos</div>
        <div class="subtitle">
            <strong>Período:</strong>
            {{ $filters['from'] ? \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') : 'inicio' }}
            al
            {{ $filters['to'] ? \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') : 'hoy' }}
            •
            <strong>Tipo:</strong> {{ $typeLabel }}
            •
            <strong>Pago:</strong> {{ $paymentLabel }}
            •
            <strong>Total:</strong>
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
        </div>
    </div>

    {{-- Resumen (igual al del modal), primero en el PDF --}}
    <div class="resumen">
        <table class="resumen-head">
            <tr>
                <td class="resumen-title">Resumen</td>
                <td class="resumen-count">
                    @if ($filters['type'] === 'all')
                        {{ $records->count() }} {{ $records->count() === 1 ? 'registro' : 'registros' }} · {{ $totals['tickets_count'] }} boletos
                    @elseif ($filters['type'] === 'tickets')
                        {{ $totals['tickets_count'] }} boletos
                    @else
                        {{ $groupedPaymentsCount }} {{ $groupedPaymentsCount === 1 ? 'pago' : 'pagos' }}
                    @endif
                </td>
            </tr>
        </table>

        @if ($filters['type'] !== 'payments')
            <table class="rgrid">
                <tr>
                    <td><div class="rbox rb-gray"><p class="rlabel">Boletos vendidos</p><p class="rvalue">{{ $totals['tickets_count'] }}</p></div></td>
                    <td><div class="rbox rb-emerald"><p class="rlabel">Ventas efectivo</p><p class="rvalue">{{ $fmt($totals['cash']) }}</p></div></td>
                    <td><div class="rbox rb-sky"><p class="rlabel">Ventas transferencia</p><p class="rvalue">{{ $fmt($totals['transfer']) }}</p></div></td>
                    <td><div class="rbox rb-fuchsia"><p class="rlabel">Total ventas</p><p class="rvalue">{{ $fmt($totals['ventas_total']) }}</p></div></td>
                </tr>
            </table>
        @endif

        @if ($filters['type'] !== 'tickets')
            <table class="rgrid">
                <tr>
                    <td><div class="rbox rb-amber"><p class="rlabel">Pagos recibidos</p><p class="rvalue">{{ $totals['payments_count'] }}</p></div></td>
                    <td><div class="rbox rb-emerald"><p class="rlabel">Pagos efectivo</p><p class="rvalue">{{ $fmt($totals['payments_cash']) }}</p></div></td>
                    <td><div class="rbox rb-sky"><p class="rlabel">Pagos transferencia</p><p class="rvalue">{{ $fmt($totals['payments_transfer']) }}</p></div></td>
                    <td><div class="rbox rb-amber"><p class="rlabel">Total pagos</p><p class="rvalue">{{ $fmt($totals['payments_total']) }}</p></div></td>
                </tr>
            </table>
        @endif

        @if ($filters['type'] === 'all')
            <table class="saldo {{ $totals['saldo'] > 0 ? 'saldo-pos' : ($totals['saldo'] < 0 ? 'saldo-neg' : 'saldo-zero') }}">
                <tr>
                    <td class="saldo-label">Saldo (total ventas − total pagos)</td>
                    <td class="saldo-value">{{ $fmt($totals['saldo']) }}</td>
                </tr>
            </table>
        @endif
    </div>

    @if ($totals['count'] > 0)
        <table>
            <thead>
                <tr>
                    <th width="10%">N°</th>
                    <th width="14%">Fecha</th>
                    <th width="12%" style="text-align: center;">Boletos</th>
                    <th width="18%" style="text-align: center;">Pago</th>
                    <th width="14%" style="text-align: right;">Monto</th>
                    <th width="32%">Tipo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $record)
                    @if ($record['type'] === 'payment')
                        <tr class="payment-row">
                            <td style="text-align: center;">
                                <span class="badge badge-payment">Pago #{{ $record['id'] }}</span>
                            </td>
                            <td>{{ $record['model']->payment_date?->format('d/m/Y') ?? '—' }}</td>
                            <td style="text-align: center;">—</td>
                            <td style="text-align: center;">
                                @php
                                    $method = $record['payment_methods'][0] ?? null;
                                @endphp
                                <span class="badge {{ $method === 'cash' ? 'badge-cash' : 'badge-transfer' }}">
                                    {{ $method === 'cash' ? 'Efectivo' : ($method === 'transfer' ? 'Transferencia' : '—') }}
                                </span>
                            </td>
                            <td class="price" style="text-align: right;">{{ $fmt($record['amount']) }}</td>
                            <td class="payment-note">Pago recibido</td>
                        </tr>
                    @else
                        <tr class="sale-row">
                            <td style="text-align: center;">Venta #{{ $record['id'] }}</td>
                            <td>{{ $record['date']?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td style="text-align: center;">{{ $record['tickets_count'] }} {{ $record['tickets_count'] === 1 ? 'boleto' : 'boletos' }}</td>
                            <td style="text-align: center;">
                                @foreach ($record['payment_methods'] as $method)
                                    <span class="badge {{ $method === 'cash' ? 'badge-cash' : 'badge-transfer' }}" style="margin-right: 3px;">
                                        {{ $method === 'cash' ? 'Efectivo' : ($method === 'transfer' ? 'Transferencia' : '—') }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="price" style="text-align: right;">{{ $fmt($record['amount']) }}</td>
                            <td>Venta</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">
            No hay registros para el período, tipo y método de pago seleccionados.
        </div>
    @endif

</body>

</html>
