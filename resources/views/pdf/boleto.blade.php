<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Boleto de Viaje</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        body {
            margin: 0;
            padding: 0;
        }

        .page {
            width: 210mm;
        }

        .boleto {
            position: relative;
            height: 130mm;
            padding: 4mm;
            border-left: 2px solid #000;
            border-right: 2px solid #000;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        .boleto:first-child {
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
            margin-bottom: 2mm;
        }

        .boleto:last-child {
            border-top: 1px solid #000;
            border-bottom: 2px solid #000;
        }


        /* HEADER */
        .boleto-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .boleto-header h1 {
            font-size: 18px;
            font-weight: bold;
        }

        .boleto-header h2 {
            font-size: 12px;
            font-weight: bold;
        }

        .empresa {
            font-size: 11px;
            color: #666;
        }

        /* CUERPO (TABLA – compatible DomPDF) */
        table.boleto-body {
            width: 100%;
            border-spacing: 6px;
            margin-bottom: 6px;
        }

        .boleto-col {
            width: 50%;
            vertical-align: top;
        }

        .boleto-section {
            border: 1px solid #ccc;
            padding: 5px;
        }

        .boleto-section h3 {
            font-size: 11px;
            font-weight: bold;
            border-bottom: 1px solid #000;
            margin-bottom: 4px;
            padding-bottom: 2px;
        }

        .boleto-row {
            font-size: 9px;
            margin-bottom: 2px;
        }

        .boleto-label {
            font-weight: bold;
        }

        /* ASIENTOS */
        .asientos {
            margin-top: 2px;
        }

        .asiento-badge {
            display: inline-block;
            border: 1px solid #000;
            padding: 1px 5px;
            font-size: 8px;
            margin: 1px;
            font-weight: bold;
            background: #f0f0f0;
        }

        /* FOOTER */
        .boleto-footer {
            border-top: 2px solid #000;
            margin-top: 6px;
            padding-top: 4px;
            text-align: center;
            font-size: 8px;
        }

        /* POSICIONADOS */
        .tipo-viaje {
            position: absolute;
            top: 6mm;
            right: 6mm;
            background: #000;
            color: #fff;
            font-size: 11px;
            font-weight: bold;
            padding: 2px 8px;
        }

        .codigo-boleto {
            position: absolute;
            bottom: 6mm;
            right: 6mm;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="page">

        <!-- BOLETO IDA -->
        <div class="boleto">
            <div class="tipo-viaje">IDA</div>

            <div class="boleto-header">
                <h1>BOLETO DE VIAJE</h1>
                <h2>SOFIA TURISMO</h2>
                <div class="empresa">EMPRESA DE TRANSPORTE</div>
            </div>

            <table class="boleto-body">
                <tr>
                    <td class="boleto-col">
                        <div class="boleto-section">
                            <h3>DATOS DEL VIAJE</h3>
                            <div class="boleto-row"><span class="boleto-label">Origen:</span> {{ $origen }}</div>
                            <div class="boleto-row"><span class="boleto-label">Destino:</span> {{ $destino }}</div>
                            <div class="boleto-row"><span class="boleto-label">Fecha:</span>
                                {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</div>
                            <div class="boleto-row"><span class="boleto-label">Horario:</span> {{ $horario }}</div>
                            <div class="boleto-row"><span class="boleto-label">Asientos:</span> {{ count($asientos) }}
                            </div>
                        </div>
                    </td>

                    <td class="boleto-col">
                        <div class="boleto-section">
                            <h3>DATOS DEL PASAJERO</h3>
                            <div class="boleto-row"><span class="boleto-label">Nombre:</span> {{ $cliente['nombre'] }}
                            </div>
                            <div class="boleto-row"><span class="boleto-label">DNI:</span> {{ $cliente['dni'] }}</div>
                            <div class="boleto-row"><span class="boleto-label">Teléfono:</span>
                                {{ $cliente['telefono'] }}</div>
                            <div class="boleto-row"><span class="boleto-label">Email:</span> {{ $cliente['email'] }}
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="boleto-section">
                <h3>ASIENTOS SELECCIONADOS</h3>
                <div class="asientos">
                    @foreach ($asientos as $asiento)
                        <span class="asiento-badge">{{ $asiento }}</span>
                    @endforeach
                </div>
            </div>

            <div class="boleto-footer">
                <div>IMPORTANTE: Presentar este boleto al momento del abordaje</div>
                <div>Conserve este documento durante todo el viaje</div>
            </div>

            <div class="codigo-boleto">
                Código: {{ strtoupper(substr(md5($cliente['dni'] . $fecha . $origen), 0, 8)) }}
            </div>
        </div>

        @if ($diferido)
            <!-- BOLETO VUELTA -->
            <div class="boleto">
                <div class="tipo-viaje">VUELTA</div>

                <div class="boleto-header">
                    <h1>BOLETO DE VIAJE</h1>
                    <h2>SOFIA TURISMO</h2>
                    <div class="empresa">EMPRESA DE TRANSPORTE</div>
                </div>

                <table class="boleto-body">
                    <tr>
                        <td class="boleto-col">
                            <div class="boleto-section">
                                <h3>DATOS DEL VIAJE</h3>
                                <div class="boleto-row"><span class="boleto-label">Origen:</span> {{ $destino }}
                                </div>
                                <div class="boleto-row"><span class="boleto-label">Destino:</span> {{ $origen }}
                                </div>
                                <div class="boleto-row"><span class="boleto-label">Fecha:</span>
                                    {{ \Carbon\Carbon::parse($fechaVuelta)->format('d/m/Y') }}</div>
                                <div class="boleto-row"><span class="boleto-label">Horario:</span> {{ $horarioVuelta }}
                                </div>
                                <div class="boleto-row"><span class="boleto-label">Asientos:</span>
                                    {{ count($asientosVuelta) }}</div>
                            </div>
                        </td>

                        <td class="boleto-col">
                            <div class="boleto-section">
                                <h3>DATOS DEL PASAJERO</h3>
                                <div class="boleto-row"><span class="boleto-label">Nombre:</span>
                                    {{ $cliente['nombre'] }}</div>
                                <div class="boleto-row"><span class="boleto-label">DNI:</span> {{ $cliente['dni'] }}
                                </div>
                                <div class="boleto-row"><span class="boleto-label">Teléfono:</span>
                                    {{ $cliente['telefono'] }}</div>
                                <div class="boleto-row"><span class="boleto-label">Email:</span>
                                    {{ $cliente['email'] }}</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="boleto-section">
                    <h3>ASIENTOS SELECCIONADOS</h3>
                    <div class="asientos">
                        @foreach ($asientosVuelta as $asiento)
                            <span class="asiento-badge">{{ $asiento }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="boleto-footer">
                    <div>IMPORTANTE: Presentar este boleto al momento del abordaje</div>
                    <div>Conserve este documento durante todo el viaje</div>
                </div>

                <div class="codigo-boleto">
                    Código: {{ strtoupper(substr(md5($cliente['dni'] . $fechaVuelta . $destino), 0, 8)) }}
                </div>
            </div>
        @endif

    </div>
</body>

</html>
