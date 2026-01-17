<!DOCTYPE html>
<html lang='es'>

<head>
    <meta charset='utf-8'>
    <title>Boletos</title>

    <style>
        @page {
            size: A4;
            margin: 3mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #111827;
            margin: 0;
        }

        .sheet {
            width: 100%;
        }

        .ticket {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 6mm;
            height: 130mm;
            /* media hoja A4 */
        }

        .ticket:first-child {
            margin-bottom: 5mm;
        }

        .ticket-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            /* 2/3 y 1/3 */
            gap: 4mm;
            flex: 1;
        }

        .header {
            text-align: center;
            margin-bottom: 4mm;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3mm;
        }

        .title {
            font-size: 14px;
            font-weight: bold;
        }

        .route-title {
            font-size: 12px;
            font-weight: bold;
            color: #d946ef;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            margin-top: 3mm;
            margin-bottom: 1mm;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1mm;
        }

        .box {
            background: #f9fafb;
            padding: 2mm;
            border-radius: 4px;
            font-size: 11px;
        }

        .child-box {
            background: #f3e8ff;
            border-left: 3px solid #d946ef;
            padding: 2mm;
            margin-top: 2mm;
            border-radius: 4px;
            font-size: 10px;
        }

        .seat {
            font-size: 18px;
            font-weight: bold;
            color: #d946ef;
            margin: 2mm;
            padding: 2mm;
            border-radius: 4px;
            border: 1px solid #f3e8ff;
            text-align: center;
            background-color: #f3f4f6;
        }

        .price {
            font-size: 14px;
            font-weight: bold;
            color: #059669;
            text-align: right;
            margin-top: 2mm;
        }

        .barcode {
            font-family: monospace;
            font-size: 11px;
            background: #f3f4f6;
            padding: 2mm;
            text-align: center;
            border-radius: 4px;
            margin-top: 2mm;
            width: 100%;
        }

        .conditions {
            font-size: 8.5px;
            text-align: justify;
            line-height: 1.3;
        }

        .conditions strong {
            font-size: 9px;
        }

        .child-warning {
            margin-top: 2mm;
            padding: 2mm;
            background: #f3e8ff;
            border-left: 3px solid #d946ef;
            font-size: 9px;
            font-weight: bold;
            color: #6b21a8;
        }

        .company-header {
            text-align: center;
            margin-bottom: 4mm;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .company-subtitle {
            font-size: 10px;
            color: #6b7280;
            margin-top: 1mm;
        }
    </style>
</head>

<body>

    @foreach ($tickets->chunk(2) as $pair)
        <div class='sheet'>

            @foreach ($pair as $index => $ticket)
                <div class='ticket'>

                    <table width='100%' cellspacing='0' cellpadding='0'>
                        <tr>
                            {{-- Columna izquierda 2/3 --}}
                            <td width='66%' valign='top' style='padding-right: 6mm;'>

                                {{-- HEADER --}}
                                <table width='100%' cellspacing='0' cellpadding='0'>
                                    <tr>
                                        <td valign='top'>
                                            <div class='company-name'>EXPRESSO SOFIA TURISMO</div>
                                            <div class='company-subtitle'>EMPRESA DE TRANSPORTE</div>
                                        </td>

                                        <td align='right' valign='top'>
                                            <div class='title'>BOLETO DE VIAJE</div>
                                            <div class='route-title'>
                                                {{ $ticket->origin->name }} > {{ $ticket->destination->name }}
                                            </div>
                                        </td>
                                    </tr>
                                </table>

                                <div class='section-title'>PASAJERO</div>
                                <div class='box'>
                                    <strong>Nombre:</strong> {{ $ticket->passenger->full_name }}<br>
                                    <strong>Documento:</strong> {{ $ticket->passenger->dni }}<br>
                                </div>

                                @php
                                    $hasChild =
                                        $ticket->travels_with_child && $ticket->passenger->children->isNotEmpty();
                                @endphp

                                @if ($hasChild)
                                    @foreach ($ticket->passenger->children as $child)
                                        <div class='child-box'>
                                            <strong class="color: #d946ef;">VIAJA CON NIÑO</strong><br>
                                            <strong>Nombre:</strong> {{ $child->full_name }}<br>
                                            <strong>Documento:</strong> {{ $child->dni }}
                                        </div>
                                    @endforeach
                                @endif

                                <div class='section-title'>VIAJE</div>
                                <div class='box'>
                                    <strong>Fecha:</strong>
                                    {{ $ticket->trip->schedule->departure_time->format('d/m/Y') }}<br>
                                    <strong>Hora de salida:</strong>
                                    {{ $ticket->trip->schedule->departure_time->format('H:i') }} hs<br>
                                    <strong>Hora de llegada:</strong>
                                    {{ $ticket->trip->schedule->arrival_time->format('H:i') }} hs<br>
                                    <strong>Servicio:</strong> {{ $ticket->trip->route->name ?? 'Regular' }}
                                </div>


                                <table width='100%' cellspacing='0' cellpadding='0'>
                                    <tr>
                                        <td width='50%' valign='top'>
                                            <div class='section-title'>ASIENTO</div>
                                            <div class='seat'>
                                                {{ $ticket->seat?->seat_number ?? 'SIN ASIENTO' }}
                                            </div>
                                        </td>
                                        <td width='50%' valign='top'>
                                            <div class='section-title'>PISO</div>
                                            <div class='seat'>
                                                {{ $ticket->seat?->floor ?? 'N/A' }}
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                                {{--                                 <div class='price'>
                                    $ {{ number_format($ticket->price, 2, ',', '.') }}
                                </div>
 --}}

                            </td>

                            {{-- Columna derecha 1/3 --}}
                            <td width='34%' valign='top'>

                                <div class='conditions'>
                                    <strong>CONDICIONES:</strong><br>
                                    Al adquirir el servicio verifique en el acto si la fecha, hora de viaje, precio
                                    abonado y destino están conforme a lo solicitado.<br><br>

                                    Cada pasajero tiene derecho a transportar hasta 1 bolso y/o valijas (hasta 15kg),
                                    cuyo tamaño y acondicionamiento no molesten al público ni al personal, y puedan
                                    llevarse en las bodegas destinadas a tal fin.<br><br>

                                    <strong>DEVOLUCIONES:</strong><br>
                                    • 30% de retención desde las 24hs anteriores y hasta la salida del servicio.<br>
                                    • 20% de retención desde las 48hs anteriores y hasta las 24hs a la salida del
                                    servicio.<br>
                                    • 10% de retención más de 48hs de la salida del servicio.<br>
                                    • Los servicios adquiridos con tarjeta de crédito no se tomarán en devolución bajo
                                    ningún concepto.<br>
                                    • Los boletos recibidos en carácter de donación y/o sin cargo son intransferibles,
                                    sin excepción.<br><br>

                                    <strong>CAMBIO DE FECHA U HORARIO:</strong><br>
                                    No se admitirán cambios; solo se aceptará devolución según condiciones anteriores.

                                    @if ($hasChild)
                                        <div class='child-warning'>
                                            El pasajero adulto es responsable del niño durante todo el viaje. El niño/a
                                            no ocupa asiento.
                                        </div>
                                    @endif

                                    <div class='section-title' style="font-size: 9px;">VENTA</div>
                                    <div class='box' style="font-size: 8px;">
                                        <strong>Fecha de emisión:</strong>
                                        {{ $ticket->sale->sale_date->format('d/m/Y H:i') }}<br>
                                        <strong>Vendedor:</strong> {{ $ticket->sale->user->name ?? 'N/A' }}
                                    </div>
                                </div>

                            </td>

                        </tr>
                    </table>
                    <div class='barcode'>
                        #{{ str_pad($ticket->id, 8, '0', STR_PAD_LEFT) }}
                    </div>

                    <p style="color: #6b7280; font-size: 10px; text-align: center;">
                        <strong>IMPORTANTE:</strong>
                        Presentar este boleto al momento del abordaje.
                        Conserve este documento durante todo el viaje.
                    </p>
                </div>
            @endforeach
        </div>
    @endforeach

</body>

</html>
