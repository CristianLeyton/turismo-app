<?php

namespace App\Livewire;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SalesTicketsExcelService;
use App\Services\SalesTicketsPdfService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Livewire\Component;

class SalesUserTickets extends Component
{
    public int $userId;

    public ?string $from = null;

    public ?string $to = null;

    /** Método de pago: all | cash | transfer */
    public string $payment = 'all';

    /** Tipo de registro: all (boletos + pagos) | tickets | payments */
    public string $type = 'all';

    /** Orden por fecha: asc (más antiguo primero) | desc (más reciente primero) */
    public string $sort = 'asc';

    /** Página actual de la tabla. */
    public int $page = 1;

    /** Registros por página (máximo 50). */
    public int $perPage = 50;

    public function mount(int $userId, ?string $from = null, ?string $to = null): void
    {
        $this->userId = $userId;
        $this->from = filled($from) ? $from : null;
        $this->to = filled($to) ? $to : null;
    }

    public function updated($property): void
    {
        // Cualquier cambio de filtro o de cantidad por página vuelve a la página 1.
        if (in_array($property, ['from', 'to', 'payment', 'type', 'perPage'], true)) {
            $this->page = 1;
        }
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
        $this->type = 'all';
        $this->sort = 'asc';
        $this->page = 1;
    }

    /**
     * Query base de boletos del vendedor en el rango y método elegidos.
     */
    protected function ticketQuery(): Builder
    {
        return Ticket::query()
            ->whereHas('sale', function ($query) {
                $query->where('user_id', $this->userId)
                    ->when(filled($this->from), fn ($q) => $q->where('sale_date', '>=', $this->from))
                    ->when(filled($this->to), fn ($q) => $q->where('sale_date', '<=', Carbon::parse($this->to)->endOfDay()));
            })
            ->when($this->payment !== 'all', fn ($q) => $q->where('payment_method', $this->payment))
            ->with(['origin', 'destination', 'trip.schedule', 'trip.bus', 'passenger', 'seat', 'sale']);
    }

    /**
     * Query base de pagos recibidos del vendedor en el rango y método elegidos.
     */
    protected function paymentQuery(): Builder
    {
        return Payment::query()
            ->where('user_id', $this->userId)
            ->when(filled($this->from), fn ($q) => $q->where('payment_date', '>=', $this->from))
            ->when(filled($this->to), fn ($q) => $q->where('payment_date', '<=', Carbon::parse($this->to)->endOfDay()))
            ->when($this->payment !== 'all', fn ($q) => $q->where('payment_method', $this->payment));
    }

    /**
     * Todos los registros filtrados (boletos + pagos), ordenados por fecha
     * según la dirección elegida (asc = la más antigua primero, desc = la
     * más reciente primero). Se usa para el resumen, la paginación y las
     * exportaciones (que salen completas, sin paginar).
     */
    public function getAllRecordsProperty(): Collection
    {
        $tickets = $this->type === 'payments' ? collect() : $this->ticketQuery()->get();
        $payments = $this->type === 'tickets' ? collect() : $this->paymentQuery()->get();

        $records = collect();

        foreach ($tickets as $ticket) {
            $date = $ticket->sale?->sale_date;

            $records->push([
                'type' => 'ticket',
                'id' => $ticket->id,
                'date' => $date,
                'sort' => $date?->getTimestamp() ?? 0,
                'payment_method' => $ticket->payment_method,
                'amount' => (float) $ticket->price,
                'model' => $ticket,
            ]);
        }

        foreach ($payments as $payment) {
            $date = $payment->payment_date;

            $records->push([
                'type' => 'payment',
                'id' => $payment->id,
                'date' => $date,
                'sort' => $date?->getTimestamp() ?? 0,
                'payment_method' => $payment->payment_method,
                'amount' => (float) $payment->amount,
                'model' => $payment,
            ]);
        }

        $direction = $this->sort === 'desc' ? 'desc' : 'asc';

        return $records
            ->sortBy([
                ['sort', $direction],
                ['type', $direction],
                ['id', $direction],
            ])
            ->values();
    }

    /**
     * Registros de la página actual, con paginación estilo Filament.
     */
    public function getRecordsProperty(): LengthAwarePaginator
    {
        $all = $this->allRecords;

        $perPage = max((int) $this->perPage, 1);
        $totalPages = max((int) ceil($all->count() / $perPage), 1);
        $page = min(max((int) $this->page, 1), $totalPages);

        return new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    /**
     * Totales del resumen, calculados sobre todos los registros filtrados
     * (no sobre la página actual).
     *
     * @return array{
     *     count: int,
     *     tickets_count: int,
     *     payments_count: int,
     *     cash: float,
     *     transfer: float,
     *     ventas_total: float,
     *     payments_cash: float,
     *     payments_transfer: float,
     *     payments_total: float,
     *     saldo: float,
     * }
     */
    public function getTotalsProperty(): array
    {
        $records = $this->allRecords;

        $cash = $records->where('type', 'ticket')->where('payment_method', 'cash')->sum('amount');
        $transfer = $records->where('type', 'ticket')->where('payment_method', 'transfer')->sum('amount');
        $paymentsCash = $records->where('type', 'payment')->where('payment_method', 'cash')->sum('amount');
        $paymentsTransfer = $records->where('type', 'payment')->where('payment_method', 'transfer')->sum('amount');

        return [
            'count' => $records->count(),
            'tickets_count' => $records->where('type', 'ticket')->count(),
            'payments_count' => $records->where('type', 'payment')->count(),
            'cash' => $cash,
            'transfer' => $transfer,
            'ventas_total' => $cash + $transfer,
            'payments_cash' => $paymentsCash,
            'payments_transfer' => $paymentsTransfer,
            'payments_total' => $paymentsCash + $paymentsTransfer,
            'saldo' => ($cash + $transfer) - ($paymentsCash + $paymentsTransfer),
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
     * Exportar en PDF todo lo filtrado (sin paginar).
     */
    public function exportPdf()
    {
        $service = app(SalesTicketsPdfService::class);

        return $service->downloadPdf(
            $this->allRecords,
            $this->getUser(),
            $this->getExportContext(),
            $this->totals,
            $this->getExportFilename('pdf')
        );
    }

    /**
     * Exportar en Excel todo lo filtrado (sin paginar).
     */
    public function exportExcel()
    {
        $service = app(SalesTicketsExcelService::class);

        return $service->downloadExcel(
            $this->allRecords,
            $this->getUser(),
            $this->getExportContext(),
            $this->totals,
            $this->getExportFilename('xlsx')
        );
    }

    /**
     * Contexto de filtros usado por las exportaciones.
     *
     * @return array{from: ?string, to: ?string, payment: string, type: string}
     */
    protected function getExportContext(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'payment' => $this->payment,
            'type' => $this->type,
        ];
    }

    protected function getExportFilename(string $extension): string
    {
        $from = $this->from ? Carbon::parse($this->from)->format('d-m-Y') : 'inicio';
        $to = $this->to ? Carbon::parse($this->to)->format('d-m-Y') : 'hoy';
        $payment = $this->payment !== 'all' ? '_' . $this->payment : '';
        $type = match ($this->type) {
            'tickets' => '_solo_boletos',
            'payments' => '_solo_pagos',
            default => '',
        };

        return "Detalle_{$this->getUser()->username}_{$from}_{$to}{$payment}{$type}.{$extension}";
    }

    public function render(): View
    {
        return view('livewire.sales-user-tickets');
    }
}
