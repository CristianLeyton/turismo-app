<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Boletos vendidos</title>

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

        .price {
            font-weight: bold;
            color: #1f2937;
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

    <div style="margin-bottom: 3mm;">
        <h1 style="font-size: 13px; color: #2b2b2b; margin: 0; text-align: left;">
            Boletos vendidos por <span style="color: #c026d3;">{{ $user->name }} {{ $user->surname }}</span>
        </h1>
    </div>

    <div class="card">
        <div class="title">Boletos vendidos</div>
        <div class="subtitle">
            <strong>Período:</strong>
            {{ $filters['from'] ? \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') : 'inicio' }}
            al
            {{ $filters['to'] ? \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') : 'hoy' }}
            •
            <strong>Pago:</strong>
            @php
                $paymentLabel = match ($filters['payment']) {
                    'cash' => 'Efectivo',
                    'transfer' => 'Transferencia',
                    default => 'Todos',
                };
            @endphp
            {{ $paymentLabel }}
            •
            <strong>Total:</strong> {{ $ticketsCount }} boletos
        </div>
    </div>

    @if ($ticketsCount > 0)
        <table>
            <thead>
                <tr>
                    <th width="6%">Boleto</th>
                    <th width="12%">Venta</th>
                    <th width="12%">Salida</th>
                    <th width="18%">Ruta</th>
                    <th width="18%">Pasajero</th>
                    <th width="10%">DNI</th>
                    <th width="7%" style="text-align: center;">Asiento</th>
                    <th width="9%" style="text-align: center;">Pago</th>
                    <th width="8%" style="text-align: right;">Precio</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tickets as $ticket)
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
                        <td class="price" style="text-align: right;">${{ number_format((float) $ticket->price, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <div class="summary-box">
                <div class="summary-label">Boletos</div>
                <div class="summary-value">{{ $ticketsCount }}</div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Efectivo</div>
                <div class="summary-value" style="color: #166534;">${{ number_format((float) $cashTotal, 0, ',', '.') }}</div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Transferencia</div>
                <div class="summary-value" style="color: #075985;">${{ number_format((float) $transferTotal, 0, ',', '.') }}</div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Total</div>
                <div class="summary-value">${{ number_format((float) $total, 0, ',', '.') }}</div>
            </div>
        </div>
    @else
        <div class="empty">
            No hay boletos para el período y método de pago seleccionados.
        </div>
    @endif

</body>

</html>
