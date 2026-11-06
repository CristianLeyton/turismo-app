{{-- Colores de marca: paleta del color de la empresa en hex (dompdf no
     entiende var() ni oklch). Se calcula acá si quien renderiza no la pasa. --}}
@php
    $brand = $brand ?? \App\Support\BrandPalette::currentAsHex();
@endphp
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

        .text-brand {
            color: {{ $brand[600] }};
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
            color: {{ $brand[600] }};
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
            background: {{ $brand[200] }};
            color: {{ $brand[800] }};
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

        /* Observación interna: gris para que se distinga también impresa en blanco y negro. */
        .observation {
            background: #eeeeee;
            border-left: 3px solid #9ca3af;
            border-radius: 6px;
            padding: 4px 6px;
            word-wrap: break-word;
        }

        .observation-empty {
            color: #9ca3af;
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
        $lastStop = $stops->last();
    @endphp

    <!-- ===== ENCABEZADO DEL VIAJE ===== -->

    <div style="margin-bottom: 3mm;">
        <h1 style="font-size: 12px; color: #2b2b2b; margin: 0; text-align: left;">
            Detalles del viaje N° {{ $trip->id }} - {{ $trip->bus->name }} -
            ({{ $trip->trip_date->format('d/m/Y') }}) {{-- ({{ $trip->route->name ?? 'Ruta sin nombre' }}) --}}
        </h1>
    </div>

    <div class="card">
        <table width="100%" style="border-collapse: collapse;">
            <tr>
                <!-- Columna izquierda -->
                <td style="vertical-align: top; width: 75%;">

                    <div class="trip-title">
                        <span class="text-brand">
                            {{ $firstStop?->location->name ?? 'Origen' }}
                        </span>
                        >
                        <span class="text-brand">
                            {{ $lastStop?->location->name ?? 'Destino' }}
                        </span>
                    </div>

                    <div class="trip-sub">
                        <strong>Fecha:</strong> {{ $trip->trip_date->format('d/m/Y') }}
                        • {{ $trip->departure_time?->format('H:i') ?? '--:--' }}
                        > {{ $trip->arrival_time?->format('H:i') ?? '--:--' }}
                    </div>

                    {{--                 <div class="trip-sub">
                    <strong>Ruta:</strong>
                    <span class="text-brand">
                        {{ $trip->route?->name ?? 'No especificada' }}
                    </span>
                </div> --}}

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

                    {{--                 <div class="stats-sub">
                    {{ $trip->total_passengers }} PASAJEROS
                </div> --}}

                </td>
            </tr>
        </table>
    </div>

    <!-- ===== TABLA PASAJEROS ===== -->
    <div class="table-card">

        <table style="font-size: 16px; font-weight: bold; padding-left: 2mm; padding-right: 2mm;">
            <tr>
                <td>
                    <div style="text-align: left;">
                        Pasajeros
                    </div>
                </td>
                <td>
                    <div style="text-align: right;">
                        ({{ $passengersCount ?? 0 }})
                    </div>
                </td>
            </tr>
        </table>



        @php
            // Con observaciones activas, la columna Observación reemplaza a
            // Teléfono (que no se borra: queda listo para cuando la lista
            // permita elegir qué columnas mostrar). Apagadas, queda como antes.
            $showObservation = $showObservation ?? \App\Models\Setting::observacionesActivas();
            $showPhone = $showPhone ?? ! $showObservation;
            $widths = match (true) {
                $showObservation && $showPhone => ['ticket' => 7, 'name' => 18, 'dni' => 10, 'phone' => 10, 'seat' => 7, 'origin' => 12, 'destination' => 12, 'observation' => 24],
                $showObservation => ['ticket' => 7, 'name' => 20, 'dni' => 11, 'phone' => 0, 'seat' => 8, 'origin' => 13, 'destination' => 13, 'observation' => 28],
                $showPhone => ['ticket' => 8, 'name' => 22, 'dni' => 12, 'phone' => 12, 'seat' => 10, 'origin' => 18, 'destination' => 18, 'observation' => 0],
                default => ['ticket' => 8, 'name' => 28, 'dni' => 14, 'phone' => 0, 'seat' => 10, 'origin' => 20, 'destination' => 20, 'observation' => 0],
            };
        @endphp

        @if (($passengersCount ?? 0) > 0)
            <table>
                <thead>
                    <tr>
                        <th width="{{ $widths['ticket'] }}%">Boleto</th>
                        <th width="{{ $widths['name'] }}%">Nombre</th>
                        <th width="{{ $widths['dni'] }}%">DNI</th>
                        @if ($showPhone)
                            <th width="{{ $widths['phone'] }}%">Teléfono</th>
                        @endif
                        <th width="{{ $widths['seat'] }}%" style="text-align: center;">Asiento</th>
                        <th width="{{ $widths['origin'] }}%" style="text-align: center;">Origen</th>
                        <th width="{{ $widths['destination'] }}%" style="text-align: center;">Destino</th>
                        @if ($showObservation)
                            <th width="{{ $widths['observation'] }}%">Observación</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($passengers as $passenger)
                        <tr>
                            <td style="text-align: center;">
                                {{ $passenger['ticket_id'] }}
                            </td>
                            <td>
                                {{ $passenger['name'] }}
                                @if ($passenger['type'] === 'child' && isset($passenger['parent_name']))
                                    <div class="child-info">
                                        Viaja con: {{ $passenger['parent_name'] }}
                                    </div>
                                @endif
                                @if ($passenger['type'] === 'pet' && isset($passenger['parent_name']))
                                    <div class="child-info">
                                        Mascota de: {{ $passenger['parent_name'] }}
                                    </div>
                                    @if (isset($passenger['pet_count']) && $passenger['pet_count'] > 1)
                                        <div class="child-info">
                                            ({{ $passenger['pet_count'] }} mascotas)
                                        </div>
                                    @endif
                                @endif
                            </td>

                            <td>{{ $passenger['dni'] == 'N/A' ? '-' : $passenger['dni'] }}</td>

                            @if ($showPhone)
                                <td>{{ $passenger['phone'] == 'N/A' ? '-' : $passenger['phone'] }}</td>
                            @endif

                            <td style="text-align: center;">
                                @if ($passenger['type'] === 'pet')
                                    <span class="badge badge-seat">No ocupa</span>
                                @else
                                    <span class="badge badge-seat">
                                        {{ is_numeric($passenger['seat_number']) ? $passenger['seat_number'] : 'No ocupa' }}
                                    </span>
                                @endif
                            </td>

                            <td style="text-align: center;">
                                <span class="badge badge-location">{{ $passenger['origin'] }}</span>
                            </td>

                            <td style="text-align: center;">
                                <span class="badge badge-location">{{ $passenger['destination'] }}</span>
                            </td>

                            {{-- Solo en la fila del adulto; el menor y la mascota quedan vacíos.
                                 Recuadro redondeado como en el detalle del boleto. --}}
                            @if ($showObservation)
                                @if ($passenger['type'] === 'adult' && filled($passenger['observation'] ?? null))
                                    <td><div class="observation">{{ $passenger['observation'] }}</div></td>
                                @elseif ($passenger['type'] === 'adult')
                                    <td class="observation-empty">-</td>
                                @else
                                    <td></td>
                                @endif
                            @endif
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

    {{--     <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }}
    </div> --}}

</body>

</html>
