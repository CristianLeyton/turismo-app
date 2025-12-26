<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Planilla de Viajes</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        .header h1 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .header .filtros {
            font-size: 10px;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 4px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
        }

        td {
            border: 1px solid #000;
            padding: 3px;
            font-size: 8px;
        }

        .viaje-header {
            background-color: #e0e0e0;
            font-weight: bold;
        }

        .viaje-header td {
            padding: 5px;
            font-size: 9px;
        }

        .pasajero-row td {
            padding: 2px 4px;
        }

        .no-pasajeros {
            text-align: center;
            color: #999;
            font-style: italic;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>PLANILLA DE VIAJES - SOFIA TURISMO</h1>
        <div class="filtros">
            @if($fechaFiltro)
                Fecha: {{ \Carbon\Carbon::parse($fechaFiltro)->format('d/m/Y') }}
            @endif
            @if($horarioFiltro)
                | Horario: {{ $horarioFiltro }}
            @endif
            @if(!$fechaFiltro && !$horarioFiltro)
                Todos los viajes
            @endif
        </div>
    </div>

    @if(count($viajes) > 0)
        @foreach($viajes as $viaje)
            <table>
                <thead>
                    <tr class="viaje-header">
                        <th colspan="10">
                            Viaje: {{ $viaje['fecha_formateada'] }} - {{ $viaje['dia_semana'] }} | 
                            {{ $viaje['horario'] }} | 
                            {{ $viaje['origen'] }} → {{ $viaje['destino'] }} | 
                            Pasajeros: {{ count($viaje['pasajeros']) }}
                        </th>
                    </tr>
                    <tr>
                        <th>Nombre</th>
                        <th>DNI</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Asiento</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($viaje['pasajeros']) > 0)
                        @foreach($viaje['pasajeros'] as $pasajero)
                            <tr class="pasajero-row">
                                <td>{{ $pasajero['nombre'] }}</td>
                                <td>{{ $pasajero['dni'] }}</td>
                                <td>{{ $pasajero['telefono'] }}</td>
                                <td>{{ $pasajero['email'] }}</td>
                                <td>{{ $pasajero['asiento'] }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" class="no-pasajeros">Sin pasajeros registrados</td>
                        </tr>
                    @endif
                </tbody>
            </table>
            <div style="page-break-after: always;"></div>
        @endforeach
    @else
        <div style="text-align: center; padding: 20px;">
            <p>No hay viajes que coincidan con los filtros seleccionados.</p>
        </div>
    @endif
</body>

</html>

