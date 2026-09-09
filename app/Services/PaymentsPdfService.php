<?php

namespace App\Services;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class PaymentsPdfService
{
    /**
     * Generar PDF de los pagos filtrados con resumen de totales.
     *
     * @param  Collection<int, Payment>  $payments
     * @param  array{from: ?string, to: ?string}  $filters
     * @param  array{count: int, cash: float, transfer: float, total: float}  $totals
     */
    public function generate(Collection $payments, array $filters, array $totals)
    {
        $pdf = Pdf::loadView('pdf.payments', [
            'payments' => $payments,
            'filters' => $filters,
            'totals' => $totals,
        ]);

        $pdf->setPaper('A4', 'portrait');
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
     * Descargar PDF de los pagos filtrados.
     *
     * @param  Collection<int, Payment>  $payments
     * @param  array{from: ?string, to: ?string}  $filters
     * @param  array{count: int, cash: float, transfer: float, total: float}  $totals
     */
    public function download(Collection $payments, array $filters, array $totals, string $filename)
    {
        $pdf = $this->generate($payments, $filters, $totals);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
