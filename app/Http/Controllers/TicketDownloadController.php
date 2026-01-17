<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class TicketDownloadController extends Controller
{
    /**
     * Descargar un solo ticket PDF
     */
    public function download(Request $request)
    {
        $pdfContent = session('ticket_pdf_content');
        $filename = session('ticket_pdf_filename');

        if (!$pdfContent || !$filename) {
            abort(404, 'Datos de ticket no encontrados');
        }

        // Limpiar sesión
        session()->forget('ticket_pdf_content');
        session()->forget('ticket_pdf_filename');

        return view('tickets.download', [
            'pdfContent' => $pdfContent,
            'filename' => $filename
        ]);
    }

    /**
     * Descargar múltiples tickets PDF
     */
    public function downloadMultiple(Request $request)
    {
        $downloads = session('ticket_pdfs_data');

        if (!$downloads) {
            abort(404, 'Datos de tickets no encontrados');
        }

        // Limpiar sesión
        session()->forget('ticket_pdfs_data');

        return view('tickets.download-multiple', [
            'downloads' => $downloads
        ]);
    }
}
