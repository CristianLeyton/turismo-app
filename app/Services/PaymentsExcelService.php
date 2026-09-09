<?php

namespace App\Services;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentsExcelExport implements FromView, WithStyles, WithTitle
{
    protected $payments;
    protected $filters;
    protected $totals;

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  array{from: ?string, to: ?string}  $filters
     * @param  array{count: int, cash: float, transfer: float, total: float}  $totals
     */
    public function __construct(Collection $payments, array $filters, array $totals)
    {
        $this->payments = $payments;
        $this->filters = $filters;
        $this->totals = $totals;
    }

    public function view(): View
    {
        return view('excel.payments', [
            'payments' => $this->payments,
            'filters' => $this->filters,
            'totals' => $this->totals,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Encabezado de la tabla (fila 3)
            3 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F0F0F0'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Pagos';
    }
}

class PaymentsExcelService
{
    /**
     * Generar Excel de los pagos filtrados.
     *
     * @param  Collection<int, Payment>  $payments
     * @param  array{from: ?string, to: ?string}  $filters
     * @param  array{count: int, cash: float, transfer: float, total: float}  $totals
     */
    public function generate(Collection $payments, array $filters, array $totals)
    {
        return new PaymentsExcelExport($payments, $filters, $totals);
    }

    /**
     * Descargar Excel de los pagos filtrados.
     *
     * @param  Collection<int, Payment>  $payments
     * @param  array{from: ?string, to: ?string}  $filters
     * @param  array{count: int, cash: float, transfer: float, total: float}  $totals
     */
    public function download(Collection $payments, array $filters, array $totals, string $filename)
    {
        return Excel::download($this->generate($payments, $filters, $totals), $filename);
    }
}
