<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;
use Filament\Panel;
use App\Exports\PlanillaViajesExport;

class PlanillasViajes extends Page
{
    protected static ?string $title = 'Planillas de Viajes';

    protected static ?string $navigationLabel = 'Planillas de Viajes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

/*     protected static string|UnitEnum|null $navigationGroup = 'Ventas'; */

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.planillas-viajes';

    public $viajes = [];

    public function mount(): void
    {
        // Datos hardcodeados de viajes
        $this->viajes = $this->generarViajesHardcodeados();
    }

    protected function generarViajesHardcodeados(): array
    {
        $viajes = [];
        $fechaInicio = Carbon::now()->next(Carbon::MONDAY); // Próximo lunes
        $viajeId = 1;

        // Generar viajes para las próximas 4 semanas (solo lunes a viernes)
        for ($semana = 0; $semana < 4; $semana++) {
            for ($dia = 0; $dia < 5; $dia++) {
                $fecha = $fechaInicio->copy()->addWeeks($semana)->addDays($dia);

                // Dos horarios por día
                foreach (['02:00-06:00', '19:00-23:00'] as $horario) {
                    // Usar el ID del viaje como semilla para datos determinísticos
                    mt_srand($viajeId * 1000);

                    $lugares = ['Salta Capital', 'Oran'];
                    $origenIndex = mt_rand(0, 1);
                    $origen = $lugares[$origenIndex];
                    $destino = $lugares[1 - $origenIndex]; // Asegurar que sean diferentes

                    // Cantidad de pasajeros determinística basada en el ID
                    $cantidadPasajeros = 5 + (($viajeId * 7) % 13); // Entre 5 y 17

                    $viajes[] = [
                        'id' => $viajeId,
                        'fecha' => $fecha->format('Y-m-d'),
                        'fecha_formateada' => $fecha->format('d/m/Y'),
                        'dia_semana' => $fecha->locale('es')->dayName,
                        'horario' => $horario,
                        'origen' => $origen,
                        'destino' => $destino,
                        'pasajeros' => $this->generarPasajerosAleatorios($cantidadPasajeros, $viajeId),
                    ];

                    $viajeId++;
                }
            }
        }

        return $viajes;
    }

    protected function generarViajePorId(int $viajeId): ?array
    {
        // Calcular la fecha y horario basado en el ID
        $fechaInicio = Carbon::now()->next(Carbon::MONDAY);

        // Cada viaje tiene un ID único: 1-40 (4 semanas * 5 días * 2 horarios)
        // Estructura: semana (0-3), día (0-4), horario (0-1)
        $viajeIndex = $viajeId - 1;

        if ($viajeIndex < 0 || $viajeIndex >= 40) {
            return null;
        }

        $semana = intval($viajeIndex / 10);
        $diaEnSemana = intval(($viajeIndex % 10) / 2);
        $horarioIndex = ($viajeIndex % 10) % 2;

        $horarios = ['02:00-06:00', '19:00-23:00'];
        $fecha = $fechaInicio->copy()->addWeeks($semana)->addDays($diaEnSemana);

        // Usar el ID del viaje como semilla para datos determinísticos
        mt_srand($viajeId * 1000);

        $lugares = ['Salta Capital', 'Oran'];
        $origenIndex = mt_rand(0, 1);
        $origen = $lugares[$origenIndex];
        $destino = $lugares[1 - $origenIndex];

        // Cantidad de pasajeros determinística
        $cantidadPasajeros = 5 + (($viajeId * 7) % 13);

        return [
            'id' => $viajeId,
            'fecha' => $fecha->format('Y-m-d'),
            'fecha_formateada' => $fecha->format('d/m/Y'),
            'dia_semana' => $fecha->locale('es')->dayName,
            'horario' => $horarios[$horarioIndex],
            'origen' => $origen,
            'destino' => $destino,
            'pasajeros' => $this->generarPasajerosAleatorios($cantidadPasajeros, $viajeId),
        ];
    }

    protected function generarPasajerosAleatorios(int $cantidad, int $viajeId): array
    {
        $nombres = [
            'Juan Pérez',
            'María González',
            'Carlos Rodríguez',
            'Ana Martínez',
            'Luis Fernández',
            'Laura Sánchez',
            'Pedro López',
            'Carmen Torres',
            'Roberto Díaz',
            'Sofía Ramírez',
            'Diego Morales',
            'Elena Castro',
            'Fernando Ruiz',
            'Patricia Vega',
            'Miguel Herrera'
        ];

        $pasajeros = [];
        for ($i = 0; $i < $cantidad; $i++) {
            // Usar semilla determinística basada en viajeId e índice
            $semilla = ($viajeId * 100) + $i;
            mt_srand($semilla);

            $nombreIndex = mt_rand(0, count($nombres) - 1);
            $nombre = $nombres[$nombreIndex];

            // DNI determinístico
            $dni = 20000000 + (($semilla * 13) % 30000000);

            // Teléfono determinístico
            $telefono = '0387' . str_pad((($semilla * 7) % 10000000), 7, '0', STR_PAD_LEFT);

            // Email basado en nombre
            $email = strtolower(str_replace([' ', 'á', 'é', 'í', 'ó', 'ú'], ['', 'a', 'e', 'i', 'o', 'u'], $nombre)) . $i . '@email.com';

            // Asiento determinístico (distribuido entre 1-60)
            $asiento = 1 + (($semilla * 11) % 60);

            $pasajeros[] = [
                'nombre' => $nombre,
                'dni' => $dni,
                'telefono' => $telefono,
                'email' => $email,
                'asiento' => $asiento,
            ];
        }

        return $pasajeros;
    }

    public function exportarPdfViaje(Request $request)
    {
        $viajeId = (int) $request->get('viaje_id');

        if (!$viajeId) {
            abort(404, 'Viaje no encontrado');
        }

        // Generar el viaje específico con datos determinísticos
        $viaje = $this->generarViajePorId($viajeId);

        if (!$viaje) {
            abort(404, 'Viaje no encontrado');
        }

        $pdf = Pdf::loadView('pdf.planilla-viaje-individual', [
            'viaje' => $viaje,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('enable-local-file-access', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('defaultFont', 'Arial');

        $nombreArchivo = 'planilla-' . str_replace('/', '-', $viaje['fecha_formateada']) . '-' . str_replace(':', '-', $viaje['horario']) . '.pdf';

        return $pdf->download($nombreArchivo);
    }

    public function exportarExcelViaje(Request $request)
    {
        $viajeId = (int) $request->get('viaje_id');

        if (!$viajeId) {
            abort(404, 'Viaje no encontrado');
        }

        // Generar el viaje específico con datos determinísticos
        $viaje = $this->generarViajePorId($viajeId);

        if (!$viaje) {
            abort(404, 'Viaje no encontrado');
        }

        $data = [];

        if (empty($viaje['pasajeros'])) {
            $data[] = [
                $viaje['fecha_formateada'],
                $viaje['dia_semana'],
                $viaje['horario'],
                $viaje['origen'],
                $viaje['destino'],
                'Sin pasajeros',
                '',
                '',
                '',
                '',
            ];
        } else {
            foreach ($viaje['pasajeros'] as $pasajero) {
                $data[] = [
                    $viaje['fecha_formateada'],
                    $viaje['dia_semana'],
                    $viaje['horario'],
                    $viaje['origen'],
                    $viaje['destino'],
                    $pasajero['nombre'],
                    $pasajero['dni'],
                    $pasajero['telefono'],
                    $pasajero['email'],
                    $pasajero['asiento'],
                ];
            }
        }

        $nombreArchivo = 'planilla-' . str_replace('/', '-', $viaje['fecha_formateada']) . '-' . str_replace(':', '-', $viaje['horario']) . '.xlsx';

        return Excel::download(new PlanillaViajesExport($data), $nombreArchivo);
    }
}
