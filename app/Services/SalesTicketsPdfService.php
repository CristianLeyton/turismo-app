<?php

namespace App\Services;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;

class SalesTicketsPdfService
{
    /**
     * Generar PDF de los boletos vendidos por un usuario en un período.
     *
     * @param  Collection<int, \App\Models\Ticket>  $tickets
     * @param  array{from: ?string, to: ?string, payment: string}  $filters
     */
    public function generatePdf(Collection $tickets, User $user, array $filters)
    {
        $pdf = Pdf::loadView('pdf.sales-tickets', [
            'tickets' => $tickets,
            'user' => $user,
            'filters' => $filters,
            'ticketsCount' => $tickets->count(),
            'cashTotal' => $tickets->where('payment_method', 'cash')->sum('price'),
            'transferTotal' => $tickets->where('payment_method', 'transfer')->sum('price'),
            'total' => $tickets->sum('price'),
        ]);

        // Misma configuración que el PDF de viajes que ya funciona
        $pdf->setPaper('A4', 'landscape');
        $pdf->setOptions([
            'defaultFont' => 'Arial',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'isPhpEnabled' => true,
            'isJavascriptEnabled' => true,
            'chroot' => realpath(public_path()),
            'tempDir' => sys_get_temp_dir(),
            'fontDir' => public_path('fonts'),
            'unicode' => true,
            'encoding' => 'UTF-8',
        ]);

        return $pdf;
    }

    /**
     * Descargar PDF de los boletos vendidos.
     *
     * @param  Collection<int, \App\Models\Ticket>  $tickets
     * @param  array{from: ?string, to: ?string, payment: string}  $filters
     */
    public function downloadPdf(Collection $tickets, User $user, array $filters, string $filename)
    {
        $pdf = $this->generatePdf($tickets, $user, $filters);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
