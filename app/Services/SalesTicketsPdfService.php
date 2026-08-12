<?php

namespace App\Services;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class SalesTicketsPdfService
{
    /**
     * Generar PDF del detalle (boletos y pagos) de un usuario en un período.
     *
     * @param  Collection<int, array{type: string, id: int, date: mixed, sort: int, payment_method: ?string, amount: float, model: mixed}>  $records
     * @param  array{from: ?string, to: ?string, payment: string, type: string}  $filters
     * @param  array{count: int, tickets_count: int, payments_count: int, cash: float, transfer: float, ventas_total: float, payments_cash: float, payments_transfer: float, payments_total: float, saldo: float}  $totals
     */
    public function generatePdf(Collection $records, User $user, array $filters, array $totals)
    {
        $pdf = Pdf::loadView('pdf.sales-tickets', [
            'records' => $records,
            'user' => $user,
            'filters' => $filters,
            'totals' => $totals,
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
     * Descargar PDF del detalle (boletos y pagos).
     *
     * @param  Collection<int, array{type: string, id: int, date: mixed, sort: int, payment_method: ?string, amount: float, model: mixed}>  $records
     * @param  array{from: ?string, to: ?string, payment: string, type: string}  $filters
     * @param  array{count: int, tickets_count: int, payments_count: int, cash: float, transfer: float, ventas_total: float, payments_cash: float, payments_transfer: float, payments_total: float, saldo: float}  $totals
     */
    public function downloadPdf(Collection $records, User $user, array $filters, array $totals, string $filename)
    {
        $pdf = $this->generatePdf($records, $user, $filters, $totals);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
