<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Planilla de Viaje</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #000;
            width: 100%;
            height: 100%;
        }

        .container {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 8px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        .header h1 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .header h2 {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .header .empresa {
            font-size: 9px;
            color: #666;
        }

        .info-viaje {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 6px;
            margin-bottom: 8px;
        }

        .info-viaje h3 {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 4px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 9px;
        }

        .info-label {
            font-weight: bold;
            width: 40%;
        }

        .info-value {
            width: 60%;
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8px;
        }

        th {
            background-color: #e0e0e0;
            border: 1px solid #000;
            padding: 4px 3px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
        }

        td {
            border: 1px solid #000;
            padding: 3px;
            font-size: 8px;
        }

        .pasajero-row td {
            padding: 2px 3px;
        }

        .no-pasajeros {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 10px;
        }

        .footer {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px solid #000;
            text-align: center;
            font-size: 7px;
            color: #666;
        }

        .total-pasajeros {
            margin-top: 5px;
            padding: 4px;
            background-color: #f9f9f9;
            border: 1px solid #000;
            text-align: right;
            font-weight: bold;
            font-size: 9px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>PLANILLA DE VIAJE</h1>
            <h2>SOFIA TURISMO</h2>
            <div class="empresa">EMPRESA DE TRANSPORTE</div>
        </div>

        <div class="info-viaje">
            <h3>INFORMACIÓN DEL VIAJE</h3>
            <div class="info-row">
                <span class="info-label">Fecha:</span>
                <span class="info-value">{{ $viaje['fecha_formateada'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Día:</span>
                <span class="info-value">{{ $viaje['dia_semana'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Horario:</span>
                <span class="info-value">{{ $viaje['horario'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Origen:</span>
                <span class="info-value">{{ $viaje['origen'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Destino:</span>
                <span class="info-value">{{ $viaje['destino'] }}</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 30%;">Nombre</th>
                    <th style="width: 15%;">DNI</th>
                    <th style="width: 15%;">Teléfono</th>
                    <th style="width: 25%;">Email</th>
                    <th style="width: 10%;">Asiento</th>
                </tr>
            </thead>
            <tbody>
                @if(count($viaje['pasajeros']) > 0)
                    @foreach($viaje['pasajeros'] as $index => $pasajero)
                        <tr class="pasajero-row">
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $pasajero['nombre'] }}</td>
                            <td>{{ $pasajero['dni'] }}</td>
                            <td>{{ $pasajero['telefono'] }}</td>
                            <td style="font-size: 7px;">{{ $pasajero['email'] }}</td>
                            <td>{{ $pasajero['asiento'] }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="6" class="no-pasajeros">Sin pasajeros registrados</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="total-pasajeros">
            Total de Pasajeros: {{ count($viaje['pasajeros']) }}
        </div>

        <div class="footer">
            <div>Documento generado el {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</div>
            <div>SOFIA TURISMO - Sistema de Gestión de Viajes</div>
        </div>
    </div>
</body>

</html>

