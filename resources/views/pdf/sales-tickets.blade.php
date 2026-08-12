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

        .summary {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            margin-top: 10px;
            font-size: 11px;
        }

        .summary-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 6px 10px;
            text-align: center;
        }

        .summary-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #9ca3af;
        }

        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #c026d3;
        }

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
            <strong>Total:</strong> {{ $totals['count'] }}
            {{ $totals['count'] === 1 ? 'registro' : 'registros' }}
            ({{ $totals['tickets_count'] }} boletos · {{ $totals['payments_count'] }} pagos)
        </div>
    </div>

    @if ($totals['count'] > 0)
        <table>
            <thead>
                <tr>
                    <th width="8%">N°</th>
                    <th width="11%">Fecha</th>
                    <th width="12%">Salida</th>
                    <th width="18%">Ruta</th>
                    <th width="17%">Pasajero</th>
                    <th width="9%">DNI</th>
                    <th width="7%" style="text-align: center;">Asiento</th>
                    <th width="10%" style="text-align: center;">Pago</th>
                    <th width="8%" style="text-align: right;">Monto</th>
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
                            <td>—</td>
                            <td colspan="3" class="payment-note">Pago recibido</td>
                            <td>—</td>
                            <td style="text-align: center;">
                                @php
                                    $method = $record['payment_method'];
                                @endphp
                                <span class="badge {{ $method === 'cash' ? 'badge-cash' : 'badge-transfer' }}">
                                    {{ $method === 'cash' ? 'Efectivo' : ($method === 'transfer' ? 'Transferencia' : '—') }}
                                </span>
                            </td>
                            <td class="price" style="text-align: right;">{{ $fmt($record['amount']) }}</td>
                        </tr>
                    @else
                        @php
                            $ticket = $record['model'];
                        @endphp
                        <tr>
                            <td style="text-align: center;">{{ $ticket->id }}</td>
                            <td>{{ $ticket->sale?->sale_date?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                {{ $ticket->trip?->trip_date?->format('d/m/Y') ?? '—' }}
                                {{ $ticket->trip?->schedule?->departure_time?->format('H:i') ?? '' }} hs
                                @if ($ticket->is_round_trip)
                                    <span class="badge badge-round">Diferido</span>
                                @endif
                            </td>
                            <td>{{ $ticket->origin?->name ?? '—' }} → {{ $ticket->destination?->name ?? '—' }}</td>
                            <td>
                                {{ $ticket->passenger?->full_name ?? 'Pasajero no disponible' }}
                                @if ($ticket->travels_with_child)
                                    <div style="font-size: 8px; color: #6b7280;">Con menor</div>
                                @endif
                                @if ($ticket->travels_with_pets)
                                    <div style="font-size: 8px; color: #6b7280;">Con mascota</div>
                                @endif
                            </td>
                            <td>{{ $ticket->passenger?->dni ?? '—' }}</td>
                            <td style="text-align: center;">
                                @if ($ticket->seat)
                                    <span class="badge badge-seat">{{ $ticket->seat->seat_number }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @php
                                    $method = $ticket->payment_method;
                                @endphp
                                <span class="badge {{ $method === 'cash' ? 'badge-cash' : 'badge-transfer' }}">
                                    {{ $method === 'cash' ? 'Efectivo' : ($method === 'transfer' ? 'Transferencia' : '—') }}
                                </span>
                            </td>
                            <td class="price" style="text-align: right;">{{ $fmt($record['amount']) }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        @if ($filters['type'] !== 'payments')
            <div class="summary">
                <div class="summary-box">
                    <div class="summary-label">Boletos</div>
                    <div class="summary-value">{{ $totals['tickets_count'] }}</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Ventas efectivo</div>
                    <div class="summary-value" style="color: #166534;">{{ $fmt($totals['cash']) }}</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Ventas transferencia</div>
                    <div class="summary-value" style="color: #075985;">{{ $fmt($totals['transfer']) }}</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Total ventas</div>
                    <div class="summary-value">{{ $fmt($totals['ventas_total']) }}</div>
                </div>
            </div>
        @endif

        @if ($filters['type'] !== 'tickets')
            <div class="summary" style="margin-top: 6px;">
                <div class="summary-box">
                    <div class="summary-label">Pagos</div>
                    <div class="summary-value" style="color: #92400e;">{{ $totals['payments_count'] }}</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Pagos efectivo</div>
                    <div class="summary-value" style="color: #166534;">{{ $fmt($totals['payments_cash']) }}</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Pagos transferencia</div>
                    <div class="summary-value" style="color: #075985;">{{ $fmt($totals['payments_transfer']) }}</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Total pagos</div>
                    <div class="summary-value" style="color: #92400e;">{{ $fmt($totals['payments_total']) }}</div>
                </div>
            </div>
        @endif

        @if ($filters['type'] === 'all')
            <div class="summary" style="margin-top: 6px;">
                <div class="summary-box" style="border-color: #c026d3;">
                    <div class="summary-label">Saldo (ventas − pagos)</div>
                    <div class="summary-value">{{ $fmt($totals['saldo']) }}</div>
                </div>
            </div>
        @endif
    @else
        <div class="empty">
            No hay registros para el período, tipo y método de pago seleccionados.
        </div>
    @endif

</body>

</html>
