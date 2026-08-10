<?php

namespace App\Livewire;

use App\Models\Sale;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SalesTicketsExcelService;
use App\Services\SalesTicketsPdfService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class SalesUserTickets extends Component
{
    public int $userId;

    public ?string $from = null;

    public ?string $to = null;

    public string $payment = 'all';

    public function mount(int $userId, ?string $from = null, ?string $to = null): void
    {
        $this->userId = $userId;
        $this->from = filled($from) ? $from : null;
        $this->to = filled($to) ? $to : null;
    }

    public function getUser(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    public function resetFilters(): void
    {
        $this->from = Carbon::now()->startOfMonth()->toDateString();
        $this->to = Carbon::now()->endOfMonth()->toDateString();
        $this->payment = 'all';
    }

    /**
     * Boletos del vendedor dentro del rango de fechas y método de pago elegidos.
     */
    public function getTicketsProperty(): Collection
    {
        return Ticket::query()
            ->whereHas('sale', function ($query) {
                $query->where('user_id', $this->userId)
                    ->when(filled($this->from), fn ($q) => $q->where('sale_date', '>=', $this->from))
                    ->when(filled($this->to), fn ($q) => $q->where('sale_date', '<=', Carbon::parse($this->to)->endOfDay()));
            })
            ->when($this->payment !== 'all', fn ($q) => $q->where('payment_method', $this->payment))
            ->with(['origin', 'destination', 'trip.schedule', 'trip.bus', 'passenger', 'seat', 'sale'])
            // Más antiguos primero; ante empate (misma venta), el N° de boleto
            // más bajo primero para que el orden sea siempre predecible.
            ->orderBy(
                Sale::select('sale_date')->whereColumn('sales.id', 'tickets.sale_id')
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * Totales del resumen inferior, calculados sobre los boletos ya filtrados.
     *
     * @return array{count: int, cash: float, transfer: float, total: float}
     */
    public function getTotalsProperty(): array
    {
        $tickets = $this->tickets;

        return [
            'count' => $tickets->count(),
            'cash' => $tickets->where('payment_method', 'cash')->sum('price'),
            'transfer' => $tickets->where('payment_method', 'transfer')->sum('price'),
            'total' => $tickets->sum('price'),
        ];
    }

    public function paymentLabel(?string $method): string
    {
        return match ($method) {
            'cash' => 'Efectivo',
            'transfer' => 'Transferencia',
            default => '—',
        };
    }

    public function paymentBadgeClasses(?string $method): string
    {
        return match ($method) {
            'cash' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
            'transfer' => 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
            default => 'bg-gray-500/10 text-gray-600 dark:text-gray-300',
        };
    }

    public function money(float|int $amount): string
    {
        return '$' . number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Exportar en PDF los boletos ya filtrados por el modal.
     */
    public function exportPdf()
    {
        $service = app(SalesTicketsPdfService::class);

        return $service->downloadPdf(
            $this->tickets,
            $this->getUser(),
            $this->getExportContext(),
            $this->getExportFilename('pdf')
        );
    }

    /**
     * Exportar en Excel los boletos ya filtrados por el modal.
     */
    public function exportExcel()
    {
        $service = app(SalesTicketsExcelService::class);

        return $service->downloadExcel(
            $this->tickets,
            $this->getUser(),
            $this->getExportContext(),
            $this->getExportFilename('xlsx')
        );
    }

    /**
     * Contexto de filtros usado por las exportaciones.
     *
     * @return array{from: ?string, to: ?string, payment: string}
     */
    protected function getExportContext(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'payment' => $this->payment,
        ];
    }

    protected function getExportFilename(string $extension): string
    {
        $from = $this->from ? Carbon::parse($this->from)->format('d-m-Y') : 'inicio';
        $to = $this->to ? Carbon::parse($this->to)->format('d-m-Y') : 'hoy';
        $payment = $this->payment !== 'all' ? '_' . $this->payment : '';

        return "Boletos_{$this->getUser()->username}_{$from}_{$to}{$payment}.{$extension}";
    }

    public function render(): View
    {
        return view('livewire.sales-user-tickets');
    }
}
