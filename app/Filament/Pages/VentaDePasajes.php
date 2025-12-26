<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;

class VentaDePasajes extends Page
{
    protected static ?string $title = 'Venta de Pasajes';

    protected static ?string $navigationLabel = 'Venta de Pasajes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

/*     protected static string|UnitEnum|null $navigationGroup = 'Ventas'; */

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.venta-de-pasajes';


    public function generarPdf(Request $request)
    {
        $data = $request->validate([
            'origen' => 'required|string',
            'destino' => 'required|string',
            'parada' => 'nullable|string',
            'fecha' => 'required|date',
            'horario' => 'required|string',
            'diferido' => 'boolean',
            'fechaVuelta' => 'nullable|date',
            'horarioVuelta' => 'nullable|string',
            'asientos' => 'required|array',
            'asientosVuelta' => 'nullable|array',
            'cliente' => 'required|array',
            'cliente.nombre' => 'required|string',
            'cliente.dni' => 'required|string',
            'cliente.telefono' => 'required|string',
            'cliente.email' => 'required|email',
        ]);

        $pdf = Pdf::loadView('pdf.boleto', $data);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('enable-local-file-access', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('defaultFont', 'Arial');

        return $pdf->download('boleto-' . time() . '.pdf');
    }
}
