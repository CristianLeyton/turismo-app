<?php

namespace App\Livewire;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SalesTicketsExcelService;
use App\Services\SalesTicketsPdfService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SalesUserTickets extends Component
{
    /**
     * Vendedor consultado. Bloqueado: el navegador no puede cambiarlo (en
     * "Mis ventas" permitiría ver los datos de otro vendedor).
     */
    #[Locked]
    public int $userId;

    public ?string $from = null;

    public ?string $to = null;

    /**
     * Métodos de pago elegidos (códigos). Vacío = todos.
     *
     * @var list<string>
     */
    public array $payments = [];

    /** Tipo de registro: all (boletos + pagos) | tickets | payments */
    public string $type = 'all';

    /** Orden por fecha: asc (más antiguo primero) | desc (más reciente primero) */
    public string $sort = 'asc';

    /** Página actual de la tabla. */
    public int $page = 1;

    /** Registros por página (máximo 50). */
    public int $perPage = 50;

    /** IDs de ventas con el detalle de boletos expandido. */
    public array $expandedSales = [];

    /**
     * Expande o colapsa el detalle de boletos de una venta.
     */
    public function toggleSale(int $saleId): void
    {
        if (in_array($saleId, $this->expandedSales, true)) {
            $this->expandedSales = array_values(array_diff($this->expandedSales, [$saleId]));
        } else {
            $this->expandedSales[] = $saleId;
        }
    }

    /**
     * Verifica si una venta tiene el detalle expandido.
     */
    public function isSaleExpanded(int $saleId): bool
    {
        return in_array($saleId, $this->expandedSales, true);
    }

    /**
     * El modal de Ventas pasa el rango y los métodos que tiene filtrados la
     * tabla; "Mis ventas" pasa el mes actual.
     *
     * @param  array<int, string>  $payments
     */
    public function mount(int $userId, ?string $from = null, ?string $to = null, array $payments = []): void
    {
        $this->userId = $userId;

        $this->authorizeViewer();

        $this->payments = array_values(array_filter($payments, fn (mixed $code): bool => is_string($code) && $code !== ''));

        $this->from = filled($from) ? $from : null;
        $this->to = filled($to) ? $to : null;
    }

    public function updated($property): void
    {
        // Cualquier cambio de filtro o de cantidad por página vuelve a la página 1.
        if (in_array($property, ['from', 'to', 'type', 'perPage'], true) || str_starts_with((string) $property, 'payments')) {
            $this->page = 1;
        }
    }

    /**
     * Con `sales.view` se consulta a cualquier vendedor; con `sales.view_own`,
     * solo a uno mismo. Se llama al montar y en cada exportación, porque son
     * peticiones independientes y los permisos pueden cambiar con el detalle
     * abierto.
     */
    protected function authorizeViewer(): void
    {
        $viewer = auth()->user();

        $canView = $viewer !== null && (
            $viewer->can('sales.view')
            || ($viewer->can('sales.view_own') && $this->userId === $viewer->getKey())
        );

        abort_unless($canView, 403);
    }

    public function getUser(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    public function resetFilters(): void
    {
        $this->from = Carbon::now()->startOfMonth()->toDateString();
        $this->to = Carbon::now()->endOfMonth()->toDateString();
        $this->payments = [];
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
            ->when($this->payments !== [], fn ($q) => $q->whereIn('payment_method', $this->payments))
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
            ->when($this->payments !== [], fn ($q) => $q->whereIn('payment_method', $this->payments));
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
     * Registros agrupados para la tabla: una fila por venta (con el total
     * de dinero de la venta y sus boletos para el detalle expandible) y una
     * fila por cada pago recibido. Mantiene el mismo orden que allRecords.
     *
     * @return Collection<int, array{type: string, id: int, date: mixed, sort: int, payment_methods: array, amount: float, tickets: Collection, tickets_count: int, model: mixed}>
     */
    public function getGroupedRecordsProperty(): Collection
    {
        $grouped = collect();

        // Pagos: una fila por pago, igual que siempre.
        $this->allRecords
            ->where('type', 'payment')
            ->each(function (array $record) use ($grouped) {
                $grouped->push([
                    'type' => 'payment',
                    'id' => $record['id'],
                    'date' => $record['date'],
                    'sort' => $record['sort'],
                    'payment_methods' => array_values(array_unique([$record['payment_method']])),
                    'amount' => $record['amount'],
                    'tickets' => collect(),
                    'tickets_count' => 0,
                    'model' => $record['model'],
                ]);
            });

        // Boletos: agrupados por venta. El monto de la fila es la suma de los
        // boletos FILTRADOS de esa venta (el movimiento de dinero real).
        $this->allRecords
            ->where('type', 'ticket')
            ->groupBy(fn (array $record) => $record['model']->sale_id ?? ('sale-'.$record['model']->id))
            ->each(function (Collection $tickets) use ($grouped) {
                $first = $tickets->first()['model'];
                $sale = $first->sale;

                $grouped->push([
                    'type' => 'sale',
                    'id' => $sale?->id ?? $first->id,
                    'date' => $sale?->sale_date ?? $tickets->first()['date'],
                    'sort' => $sale?->sale_date?->getTimestamp() ?? $tickets->first()['sort'],
                    'payment_methods' => $tickets->map(fn (array $r) => $r['payment_method'])->filter()->unique()->values()->all(),
                    'amount' => (float) $tickets->sum('amount'),
                    'tickets' => $tickets->pluck('model')->values(),
                    'tickets_count' => $tickets->count(),
                    'model' => $sale,
                ]);
            });

        $direction = $this->sort === 'desc' ? 'desc' : 'asc';

        return $grouped
            ->sortBy([
                ['sort', $direction],
                ['type', $direction],
                ['id', $direction],
            ])
            ->values();
    }

    /**
     * Registros agrupados de la página actual, con paginación estilo Filament.
     */
    public function getRecordsProperty(): LengthAwarePaginator
    {
        $all = $this->groupedRecords;

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
     *     ventas_total: float,
     *     payments_total: float,
     *     saldo: float,
     *     method_breakdown: array,
     * }
     */
    public function getTotalsProperty(): array
    {
        $records = $this->allRecords;

        // Independiente del método: suma sobre todos los códigos existentes.
        $ventas = $records->where('type', 'ticket')->sum('amount');
        $pagos = $records->where('type', 'payment')->sum('amount');

        return [
            'count' => $records->count(),
            'tickets_count' => $records->where('type', 'ticket')->count(),
            'payments_count' => $records->where('type', 'payment')->count(),
            'ventas_total' => $ventas,
            'payments_total' => $pagos,
            'saldo' => $ventas - $pagos,
            // Desglose por método: lo consumen el modal y los exports.
            'method_breakdown' => $this->methodBreakdown,
        ];
    }

    public function paymentLabel(?string $method): string
    {
        return PaymentMethod::label($method);
    }

    public function paymentBadgeClasses(?string $method): string
    {
        return PaymentMethod::badgeClasses($method);
    }

    /**
     * Desglose de ventas y pagos por método de pago, en el orden definido
     * en la tabla payment_methods. Solo incluye métodos con monto > 0.
     *
     * @return array<int, array{code: string, label: string, color: string, ventas: float, pagos: float}>
     */
    public function getMethodBreakdownProperty(): array
    {
        $breakdown = [];

        foreach (PaymentMethod::allWithState() as $code => $meta) {
            $ventas = $this->allRecords
                ->where('type', 'ticket')
                ->where('payment_method', $code)
                ->sum('amount');
            $pagos = $this->allRecords
                ->where('type', 'payment')
                ->where('payment_method', $code)
                ->sum('amount');

            if ($ventas == 0.0 && $pagos == 0.0) {
                continue;
            }

            $breakdown[] = [
                'code' => $code,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'ventas' => (float) $ventas,
                'pagos' => (float) $pagos,
            ];
        }

        return $breakdown;
    }

    public function money(float|int $amount): string
    {
        return '$'.number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Exportar en PDF todo lo filtrado (sin paginar), agrupado por venta
     * con el detalle de boletos de cada una.
     */
    public function exportPdf()
    {
        $this->authorizeViewer();

        $service = app(SalesTicketsPdfService::class);

        return $service->downloadPdf(
            $this->groupedRecords,
            $this->getUser(),
            $this->getExportContext(),
            $this->totals,
            $this->getExportFilename('pdf')
        );
    }

    /**
     * Exportar en Excel todo lo filtrado (sin paginar), agrupado por venta
     * (sin el detalle de boletos: para eso está la app).
     */
    public function exportExcel()
    {
        $this->authorizeViewer();

        $service = app(SalesTicketsExcelService::class);

        return $service->downloadExcel(
            $this->groupedRecords,
            $this->getUser(),
            $this->getExportContext(),
            $this->totals,
            $this->getExportFilename('xlsx')
        );
    }

    /**
     * Contexto de filtros usado por las exportaciones.
     *
     * @return array{from: ?string, to: ?string, payments: list<string>, payment_label: ?string, type: string}
     */
    protected function getExportContext(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'payments' => $this->payments,
            'payment_label' => $this->selectedPaymentsLabel(),
            'type' => $this->type,
        ];
    }

    /**
     * Etiquetas de los métodos elegidos unidas por coma, o null si son todos.
     */
    public function selectedPaymentsLabel(): ?string
    {
        if ($this->payments === []) {
            return null;
        }

        return implode(', ', array_map(fn (string $code): string => PaymentMethod::label($code), $this->payments));
    }

    /**
     * Texto del botón del desplegable de métodos: hasta dos se nombran, con
     * más se cuentan para que el botón no crezca.
     */
    public function paymentsButtonLabel(): string
    {
        return match (true) {
            $this->payments === [] => 'Todos los métodos',
            count($this->payments) <= 2 => (string) $this->selectedPaymentsLabel(),
            default => count($this->payments).' métodos',
        };
    }

    /**
     * Las casillas del desplegable escriben el array desde el navegador:
     * se normaliza a una lista de códigos.
     */
    public function updatedPayments(): void
    {
        $this->payments = array_values(array_unique(array_filter(
            $this->payments,
            fn (mixed $code): bool => is_string($code) && $code !== '',
        )));
    }

    protected function getExportFilename(string $extension): string
    {
        $from = $this->from ? Carbon::parse($this->from)->format('d-m-Y') : 'inicio';
        $to = $this->to ? Carbon::parse($this->to)->format('d-m-Y') : 'hoy';
        $payment = $this->payments !== [] ? '_'.implode('-', $this->payments) : '';
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
