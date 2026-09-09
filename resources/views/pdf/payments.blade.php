<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Pagos</title>

    <style>
        @page {
            margin: 8mm;
            size: A4 portrait;
        }

        body {
            font-family: "Arial", sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        .card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
        }

        .title {
            font-size: 14px;
            font-weight: bold;
            color: #c026d3;
        }

        .subtitle {
            margin-top: 4px;
            font-size: 10px;
            color: #6b7280;
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

        .amount {
            font-weight: bold;
            text-align: right;
        }

        /* Resumen (mismo estilo que el detalle de ventas) */
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

        .rb-amber { background: #fffbeb; }
        .rb-amber .rlabel, .rb-amber .rvalue { color: #b45309; }

        .empty {
            padding: 20px;
            font-size: 11px;
            color: #6b7280;
        }
    </style>
</head>

<body>

    @php
        $fmt = fn ($value) => '$' . number_format((float) $value, 0, ',', '.');
    @endphp

    <div style="margin-bottom: 3mm;">
        <h1 style="font-size: 13px; color: #2b2b2b; margin: 0; text-align: left;">
            Pagos recibidos
        </h1>
    </div>

    {{-- Resumen primero, como en Ventas --}}
    <div class="card">
        <table class="rgrid">
            <tr>
                <td>
                    <div class="rbox rb-gray">
                        <p class="rlabel">Pagos recibidos</p>
                        <p class="rvalue">{{ $totals['count'] }}</p>
                    </div>
                </td>
                <td>
                    <div class="rbox rb-emerald">
                        <p class="rlabel">Efectivo</p>
                        <p class="rvalue">{{ $fmt($totals['cash']) }}</p>
                    </div>
                </td>
                <td>
                    <div class="rbox rb-sky">
                        <p class="rlabel">Transferencia</p>
                        <p class="rvalue">{{ $fmt($totals['transfer']) }}</p>
                    </div>
                </td>
                <td>
                    <div class="rbox rb-amber">
                        <p class="rlabel">Total cobrado</p>
                        <p class="rvalue">{{ $fmt($totals['total']) }}</p>
                    </div>
                </td>
            </tr>
        </table>
        <div class="subtitle" style="margin-top: 6px;">
            Período:
            {{ $filters['from'] ? \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') : 'inicio' }}
            al
            {{ $filters['to'] ? \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') : 'hoy' }}
        </div>
    </div>

    @if ($totals['count'] > 0)
        <table>
            <thead>
                <tr>
                    <th width="18%">Fecha de pago</th>
                    <th width="42%">Vendedor</th>
                    <th width="20%" style="text-align: center;">Método</th>
                    <th width="20%" style="text-align: right;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            {{ $payment->user ? trim($payment->user->name . ' ' . ($payment->user->surname ?? '')) : '—' }}
                        </td>
                        <td style="text-align: center;">
                            <span class="badge {{ $payment->payment_method === 'cash' ? 'badge-cash' : 'badge-transfer' }}">
                                {{ $payment->payment_method === 'cash' ? 'Efectivo' : 'Transferencia' }}
                            </span>
                        </td>
                        <td class="amount">{{ $fmt($payment->amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty">No hay pagos para el período seleccionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @else
        <div class="empty">
            No hay pagos para el período seleccionado.
        </div>
    @endif

</body>

</html>
