<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesTicketsExcelExport implements FromView, WithStyles, WithTitle
{
    protected $tickets;
    protected $user;
    protected $filters;

    public function __construct(Collection $tickets, User $user, array $filters)
    {
        $this->tickets = $tickets;
        $this->user = $user;
        $this->filters = $filters;
    }

    public function view(): View
    {
        return view('excel.sales-tickets', [
            'tickets' => $this->tickets,
            'user' => $this->user,
            'filters' => $this->filters,
            'ticketsCount' => $this->tickets->count(),
            'cashTotal' => $this->tickets->where('payment_method', 'cash')->sum('price'),
            'transferTotal' => $this->tickets->where('payment_method', 'transfer')->sum('price'),
            'total' => $this->tickets->sum('price'),
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
        return 'Boletos Vendidos';
    }
}

class SalesTicketsExcelService
{
    /**
     * Generar Excel de los boletos vendidos por un usuario en un período.
     *
     * @param  Collection<int, \App\Models\Ticket>  $tickets
     * @param  array{from: ?string, to: ?string, payment: string}  $filters
     */
    public function generateExcel(Collection $tickets, User $user, array $filters)
    {
        return new SalesTicketsExcelExport($tickets, $user, $filters);
    }

    /**
     * Descargar Excel de los boletos vendidos.
     *
     * @param  Collection<int, \App\Models\Ticket>  $tickets
     * @param  array{from: ?string, to: ?string, payment: string}  $filters
     */
    public function downloadExcel(Collection $tickets, User $user, array $filters, string $filename)
    {
        $export = $this->generateExcel($tickets, $user, $filters);

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }
}
