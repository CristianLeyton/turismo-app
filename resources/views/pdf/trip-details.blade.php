<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Detalles del Viaje</title>

    <style>
        @page {
            margin: 3mm;
            size: A4 portrait;
        }

        body {
            font-family: "Arial", sans-serif;
            font-size: 12px;
            color: #1f2937;
        }

        /* ======= CONTENEDOR GENERAL ======= */
        .card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 18px;
        }

        /* ======= ENCABEZADO VIAJE ======= */
        .trip-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .trip-title {
            font-size: 20px;
            font-weight: bold;
        }

        .text-fuchsia {
            color: #c026d3;
        }

        .trip-sub {
            margin-top: 6px;
            font-size: 11px;
            color: #6b7280;
        }

        .trip-sub strong {
            color: #374151;
        }

        .stats {
            text-align: right;
        }

        .stats-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #9ca3af;
        }

        .stats-value {
            font-size: 26px;
            font-weight: bold;
            color: #c026d3;
            line-height: 1;
        }

        .stats-sub {
            font-size: 11px;
            color: #6b7280;
            font-weight: bold;
            margin-top: 4px;
        }

        /* ======= TABLA ======= */

        .table-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .table-header {
            padding: 10px 14px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
            font-weight: bold;
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
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            padding: 6px;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            font-size: 11px;
            padding: 6px;
            border-bottom: 1px solid #f3f4f6;
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* ======= BADGES ======= */

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-adult {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-child {
            background: #dcfce7;
            color: #166534;
        }

        .badge-seat {
            background: #f5d0fe;
            color: #86198f;
        }

        .badge-location {
            background: #ffedd5;
            color: #9a3412;
        }

        .child-info {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }

        .empty {
            padding: 20px;
            font-size: 11px;
            color: #6b7280;
        }

        /* ======= FOOTER ======= */
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

@php
    $stops = $trip->route->stops()->with('location')->get();
    $firstStop = $stops->first();
    $lastStop  = $stops->last();
@endphp

<!-- ===== ENCABEZADO DEL VIAJE ===== -->

<div style="margin-bottom: 3mm;">
    <h1 style="font-size: 12px; color: #2b2b2b; margin: 0; text-align: left;">
        Detalles del viaje - {{ $trip->bus->name}} - ({{ $trip->trip_date->format('d/m/Y') }}) - ({{ $trip->route->name ?? 'Ruta sin nombre' }})
    </h1>
</div>

<div class="card">
    <table width="100%" style="border-collapse: collapse;">
        <tr>
            <!-- Columna izquierda -->
            <td style="vertical-align: top; width: 75%;">

                <div class="trip-title">
                    <span class="text-fuchsia">
                        {{ $firstStop?->location->name ?? 'Origen' }}
                    </span>
                    >
                    <span class="text-fuchsia">
                        {{ $lastStop?->location->name ?? 'Destino' }}
                    </span>
                </div>

                <div class="trip-sub">
                    <strong>Fecha:</strong> {{ $trip->trip_date->format('d/m/Y') }}
                    • {{ $trip->departure_time?->format('H:i') ?? '--:--' }}
                    > {{ $trip->arrival_time?->format('H:i') ?? '--:--' }}
                </div>

                <div class="trip-sub">
                    <strong>Ruta:</strong>
                    <span class="text-fuchsia">
                        {{ $trip->route?->name ?? 'No especificada' }}
                    </span>
                </div>

                <div class="trip-sub">
                    <strong>Colectivo:</strong>
                    {{ $trip->bus?->name ?? 'No especificado' }}
                </div>

            </td>

            <!-- Columna derecha -->
            <td style="vertical-align: top; width: 25%; text-align: right;">

                <div class="stats-label">ASIENTOS VENDIDOS</div>

                <div class="stats-value">
                    {{ $trip->occupiedSeatsCount() }}
                </div>

                <div class="stats-sub">
                    {{ $trip->total_passengers }} PASAJEROS
                </div>

            </td>
        </tr>
    </table>
</div>

<!-- ===== TABLA PASAJEROS ===== -->
<div class="table-card">
    <div class="table-header">
        Pasajeros del Viaje ({{ $passengersCount ?? 0 }})
    </div>

    @if(($passengersCount ?? 0) > 0)
        <table>
            <thead>
                <tr>
                    <th width="8%">Tipo</th>
                    <th width="22%">Nombre</th>
                    <th width="12%">DNI</th>
                    <th width="12%">Teléfono</th>
                    <th width="10%">Asiento</th>
                    <th width="18%">Origen</th>
                    <th width="18%">Destino</th>
                </tr>
            </thead>
            <tbody>
                @foreach($passengers as $passenger)
                    <tr>
                        <td>
                            @if($passenger['type'] === 'adult')
                                <span class="badge badge-adult">Adulto</span>
                            @else
                                <span class="badge badge-child">Niño</span>
                            @endif
                        </td>

                        <td>
                            {{ $passenger['name'] }}
                            @if($passenger['type'] === 'child' && isset($passenger['parent_name']))
                                <div class="child-info">
                                    Viaja con: {{ $passenger['parent_name'] }}
                                </div>
                            @endif
                        </td>

                        <td>{{ $passenger['dni'] }}</td>

                        <td>{{ $passenger['phone'] == 'N/A' ? '-' : $passenger['phone'] }}</td>

                        <td>
                            <span class="badge badge-seat">
                                {{ is_numeric($passenger['seat_number']) ? $passenger['seat_number'] : 'No ocupa' }}
                            </span>
                        </td>

                        <td>
                            <span class="badge badge-location">{{ $passenger['origin'] }}</span>
                        </td>

                        <td>
                            <span class="badge badge-location">{{ $passenger['destination'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">
            No hay pasajeros registrados para este viaje.
        </div>
    @endif
</div>

<div class="footer">
    Generado el {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
