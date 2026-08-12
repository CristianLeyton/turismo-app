<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesTicketsExcelExport implements FromView, WithStyles, WithTitle
{
    protected $records;
    protected $user;
    protected $filters;
    protected $totals;

    public function __construct(Collection $records, User $user, array $filters, array $totals)
    {
        $this->records = $records;
        $this->user = $user;
        $this->filters = $filters;
        $this->totals = $totals;
    }

    public function view(): View
    {
        return view('excel.sales-tickets', [
            'records' => $this->records,
            'user' => $this->user,
            'filters' => $this->filters,
            'totals' => $this->totals,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo simple para la fila de encabezado de la tabla (fila 3)
            3 => [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F0F0F0'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Detalle de ventas';
    }
}

class SalesTicketsExcelService
{
    /**
     * Generar Excel del detalle (boletos y pagos) de un usuario en un período.
     *
     * @param  Collection<int, array{type: string, id: int, date: mixed, sort: int, payment_method: ?string, amount: float, model: mixed}>  $records
     * @param  array{from: ?string, to: ?string, payment: string, type: string}  $filters
     * @param  array{count: int, tickets_count: int, payments_count: int, cash: float, transfer: float, ventas_total: float, payments_cash: float, payments_transfer: float, payments_total: float, saldo: float}  $totals
     */
    public function generateExcel(Collection $records, User $user, array $filters, array $totals)
    {
        return new SalesTicketsExcelExport($records, $user, $filters, $totals);
    }

    /**
     * Descargar Excel del detalle (boletos y pagos).
     *
     * @param  Collection<int, array{type: string, id: int, date: mixed, sort: int, payment_method: ?string, amount: float, model: mixed}>  $records
     * @param  array{from: ?string, to: ?string, payment: string, type: string}  $filters
     * @param  array{count: int, tickets_count: int, payments_count: int, cash: float, transfer: float, ventas_total: float, payments_cash: float, payments_transfer: float, payments_total: float, saldo: float}  $totals
     */
    public function downloadExcel(Collection $records, User $user, array $filters, array $totals, string $filename)
    {
        $export = $this->generateExcel($records, $user, $filters, $totals);

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }
}
