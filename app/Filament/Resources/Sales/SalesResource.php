<?php

namespace App\Filament\Resources\Sales;

use App\Filament\Clusters\Sales\SalesCluster;
use App\Filament\Resources\Sales\Pages\ManageSales;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;

class SalesResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $cluster = SalesCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::PresentationChartBar;

    protected static ?string $modelLabel = 'venta';
    protected static ?string $pluralModelLabel = 'Ventas';
    protected static bool $hasTitleCaseModelLabel = false;
    /*     protected static string | UnitEnum | null $navigationGroup = 'Sistema'; */
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'username';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    protected static function formatMoney(float $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }

    /**
     * Subquery reutilizable: cantidad de tickets vendidos por un usuario en el
     * rango de fechas vigente. Se usa para ordenar la columna de tickets sin
     * necesidad de agregarla al SELECT principal.
     */
    protected static function ticketsCountSubquery(?string $from, ?string $to): QueryBuilder
    {
        return DB::table('tickets')
            ->join('sales', 'sales.id', '=', 'tickets.sale_id')
            ->whereColumn('sales.user_id', 'users.id')
            ->whereNull('sales.deleted_at')
            ->whereNull('tickets.deleted_at')
            ->when($from, fn($q, $date) => $q->where('sales.sale_date', '>=', $date))
            ->when($to, fn($q, $date) => $q->where('sales.sale_date', '<=', $date))
            ->selectRaw('COUNT(tickets.id)');
    }

    /**
     * Subquery reutilizable: suma de tickets de un usuario por método de pago
     * (o varios), en el rango de fechas vigente. Se usa tanto para ordenar
     * las columnas de montos como para el total del footer.
     */
    protected static function ticketsSumSubquery(?string $paymentMethod, array $paymentMethods, ?string $from, ?string $to): QueryBuilder
    {
        $query = DB::table('tickets')
            ->selectRaw('COALESCE(SUM(tickets.price), 0)')
            ->join('sales', 'sales.id', '=', 'tickets.sale_id')
            ->whereColumn('sales.user_id', 'users.id')
            ->whereNull('sales.deleted_at')
            ->whereNull('tickets.deleted_at')
            ->when($from, fn($q, $date) => $q->where('sales.sale_date', '>=', $date))
            ->when($to, fn($q, $date) => $q->where('sales.sale_date', '<=', $date));

        if ($paymentMethod !== null) {
            $query->where('tickets.payment_method', $paymentMethod);
        } else {
            $query->whereIn('tickets.payment_method', $paymentMethods);
        }

        return $query;
    }

    /**
     * Total para el footer: toma los IDs de usuarios que la tabla ya está
     * mostrando (respeta búsqueda + filtro de fecha) y suma sus tickets
     * acotado al mismo rango de fechas ($from/$to).
     */
    protected static function sumTicketsForFooter(QueryBuilder $query, ?string $paymentMethod, array $paymentMethods, ?string $from, ?string $to): float
    {
        $userIds = (clone $query)->pluck('users.id');

        $sumQuery = DB::table('tickets')
            ->join('sales', 'sales.id', '=', 'tickets.sale_id')
            ->whereNull('sales.deleted_at')
            ->whereNull('tickets.deleted_at')
            ->whereIn('sales.user_id', $userIds)
            ->when($from, fn($q, $date) => $q->where('sales.sale_date', '>=', $date))
            ->when($to, fn($q, $date) => $q->where('sales.sale_date', '<=', $date));

        if ($paymentMethod !== null) {
            $sumQuery->where('tickets.payment_method', $paymentMethod);
        } else {
            $sumQuery->whereIn('tickets.payment_method', $paymentMethods);
        }

        return (float) $sumQuery->sum('tickets.price');
    }

    /**
     * Subquery reutilizable: cantidad de pagos recibidos de un usuario en el
     * rango de fechas vigente.
     */
    protected static function paymentsCountSubquery(?string $from, ?string $to): QueryBuilder
    {
        return DB::table('payments')
            ->whereColumn('payments.user_id', 'users.id')
            ->whereNull('payments.deleted_at')
            ->when($from, fn($q, $date) => $q->where('payments.payment_date', '>=', $date))
            ->when($to, fn($q, $date) => $q->where('payments.payment_date', '<=', $date))
            ->selectRaw('COUNT(payments.id)');
    }

    /**
     * Subquery reutilizable: suma de montos de los pagos recibidos de un
     * usuario en el rango de fechas vigente.
     */
    protected static function paymentsSumSubquery(?string $from, ?string $to): QueryBuilder
    {
        return DB::table('payments')
            ->whereColumn('payments.user_id', 'users.id')
            ->whereNull('payments.deleted_at')
            ->when($from, fn($q, $date) => $q->where('payments.payment_date', '>=', $date))
            ->when($to, fn($q, $date) => $q->where('payments.payment_date', '<=', $date))
            ->selectRaw('COALESCE(SUM(payments.amount), 0)');
    }

    /**
     * Totales para el footer: toma los IDs de usuarios que la tabla ya está
     * mostrando (respeta búsqueda + filtro de fecha) y cuenta/suma sus pagos
     * acotado al mismo rango de fechas ($from/$to).
     *
     * @return array{count: int, sum: float}
     */
    protected static function paymentsForFooter(QueryBuilder $query, ?string $from, ?string $to): array
    {
        $userIds = (clone $query)->pluck('users.id');

        $paymentQuery = DB::table('payments')
            ->whereNull('payments.deleted_at')
            ->whereIn('payments.user_id', $userIds)
            ->when($from, fn($q, $date) => $q->where('payments.payment_date', '>=', $date))
            ->when($to, fn($q, $date) => $q->where('payments.payment_date', '<=', $date));

        return [
            'count' => (clone $paymentQuery)->count(),
            'sum' => (float) (clone $paymentQuery)->sum('payments.amount'),
        ];
    }

    public static function table(Table $table): Table
    {
        // Rango de fechas actualmente seleccionado. Se completa dentro del
        // query() del filtro 'date_range' (siempre corre porque tiene
        // valores por defecto y deferFilters(false)).
        //
        // OJO: se lee más abajo desde closures NORMALES con "use (&$dateFilter)"
        // (nunca desde arrow functions "fn() => ..."), porque las arrow
        // functions capturan las variables externas POR VALOR, no por
        // referencia, y quedarían con el snapshot ['from' => null, 'to' => null]
        // de este momento en vez del valor real que fija el filtro.
        $dateFilter = ['from' => null, 'to' => null];

        return $table
            ->recordTitleAttribute('username')
            ->columns([
                TextColumn::make('username')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable()
                    ->hidden(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->getStateUsing(fn(Model $record): string => $record->name . ' ' . ($record->surname ?? ''))
                    ->searchable()
                    ->sortable(['name', 'surname'])
                    ->limit(22),
                TextColumn::make('tickets_count')
                    ->label('Boletos vendidos')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-ticket')
                    ->iconPosition('after')
                    ->alignCenter()
                    ->toggleable()
                    ->visibleFrom('md')
                    ->getStateUsing(function (Model $record): int {
                        $ticketCount = 0;

                        foreach ($record->sales as $sale) {
                            $ticketCount += $sale->tickets->count();
                        }

                        return $ticketCount;
                    })
                    ->sortable(query: function (Builder $query, string $direction) use (&$dateFilter): Builder {
                        return $query->orderBy(
                            static::ticketsCountSubquery($dateFilter['from'], $dateFilter['to']),
                            $direction
                        );
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->extraAttributes(['class' => 'hidden md:flex md:flex-col'])
                            ->using(function (QueryBuilder $query) use (&$dateFilter): int {
                                $userIds = (clone $query)->pluck('users.id');

                                return DB::table('tickets')
                                    ->join('sales', 'sales.id', '=', 'tickets.sale_id')
                                    ->whereIn('sales.user_id', $userIds)
                                    ->whereNull('sales.deleted_at')
                                    ->whereNull('tickets.deleted_at')
                                    ->when($dateFilter['from'], fn($q, $date) => $q->where('sales.sale_date', '>=', $date))
                                    ->when($dateFilter['to'], fn($q, $date) => $q->where('sales.sale_date', '<=', $date))
                                    ->count();
                            })
                    ),
                TextColumn::make('cash_amount')
                    ->label('Efectivo')
                    ->visibleFrom('md')
                    ->getStateUsing(function (Model $record) {
                        $total = 0;
                        foreach ($record->sales as $sale) {
                            foreach ($sale->tickets as $ticket) {
                                if ($ticket->payment_method === 'cash') {
                                    $total += $ticket->price;
                                }
                            }
                        }
                        return static::formatMoney($total);
                    })
                    ->sortable(query: function (Builder $query, string $direction) use (&$dateFilter): Builder {
                        return $query->orderBy(
                            static::ticketsSumSubquery('cash', [], $dateFilter['from'], $dateFilter['to']),
                            $direction
                        );
                    })
                    ->badge()
                    ->color('success')
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->extraAttributes(['class' => 'hidden md:flex md:flex-col'])
                            ->hiddenOn('sm')
                            ->using(function (QueryBuilder $query) use (&$dateFilter): string {
                                return static::formatMoney(
                                    static::sumTicketsForFooter($query, 'cash', [], $dateFilter['from'], $dateFilter['to'])
                                );
                            })
                    ),
                TextColumn::make('transfer_amount')
                    ->label('Transferencia')
                    ->visibleFrom('md')
                    ->getStateUsing(function (Model $record) {
                        $total = 0;
                        foreach ($record->sales as $sale) {
                            foreach ($sale->tickets as $ticket) {
                                if ($ticket->payment_method === 'transfer') {
                                    $total += $ticket->price;
                                }
                            }
                        }
                        return static::formatMoney($total);
                    })
                    ->sortable(query: function (Builder $query, string $direction) use (&$dateFilter): Builder {
                        return $query->orderBy(
                            static::ticketsSumSubquery('transfer', [], $dateFilter['from'], $dateFilter['to']),
                            $direction
                        );
                    })
                    ->badge()
                    ->color('info')
                    ->toggleable()
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->extraAttributes(['class' => 'hidden md:flex md:flex-col'])
                            ->using(function (QueryBuilder $query) use (&$dateFilter): string {
                                return static::formatMoney(
                                    static::sumTicketsForFooter($query, 'transfer', [], $dateFilter['from'], $dateFilter['to'])
                                );
                            })
                    ),
                TextColumn::make('total_amount')
                    ->label('Total ventas')
                    ->color('primary')
                    ->visibleFrom('md')
                    ->getStateUsing(function (Model $record) {
                        $total = 0;
                        foreach ($record->sales as $sale) {
                            foreach ($sale->tickets as $ticket) {
                                if (in_array($ticket->payment_method, ['cash', 'transfer'])) {
                                    $total += $ticket->price;
                                }
                            }
                        }
                        return static::formatMoney($total);
                    })
                    ->weight('bold')
                    ->toggleable()
                    ->sortable(query: function (Builder $query, string $direction) use (&$dateFilter): Builder {
                        return $query->orderBy(
                            static::ticketsSumSubquery(null, ['cash', 'transfer'], $dateFilter['from'], $dateFilter['to']),
                            $direction
                        );
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->extraAttributes(['class' => 'hidden md:flex md:flex-col'])
                            ->using(function (QueryBuilder $query) use (&$dateFilter): string {
                                return static::formatMoney(
                                    static::sumTicketsForFooter($query, null, ['cash', 'transfer'], $dateFilter['from'], $dateFilter['to'])
                                );
                            })
                    ),
                TextColumn::make('payments_count')
                    ->label('Pagos')
                    ->badge()
                    ->visibleFrom('md')
                    ->color('warning')
                    ->icon('heroicon-o-banknotes')
                    ->iconPosition('after')
                    ->alignCenter()
                    ->toggleable()
                    ->getStateUsing(function (Model $record): int {
                        return $record->payments->count();
                    })
                    ->sortable(query: function (Builder $query, string $direction) use (&$dateFilter): Builder {
                        return $query->orderBy(
                            static::paymentsCountSubquery($dateFilter['from'], $dateFilter['to']),
                            $direction
                        );
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->extraAttributes(['class' => 'hidden md:flex md:flex-col'])
                            ->using(function (QueryBuilder $query) use (&$dateFilter): int {
                                return static::paymentsForFooter($query, $dateFilter['from'], $dateFilter['to'])['count'];
                            })
                    ),
                TextColumn::make('payments_total')
                    ->label('Total pagos')
                    ->badge()
                    ->visibleFrom('md')
                    ->color('warning')
                    ->toggleable()
                    ->getStateUsing(function (Model $record): string {
                        $total = 0;

                        foreach ($record->payments as $payment) {
                            $total += (float) $payment->amount;
                        }

                        return static::formatMoney($total);
                    })
                    ->sortable(query: function (Builder $query, string $direction) use (&$dateFilter): Builder {
                        return $query->orderBy(
                            static::paymentsSumSubquery($dateFilter['from'], $dateFilter['to']),
                            $direction
                        );
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->extraAttributes(['class' => 'hidden md:flex md:flex-col'])
                            ->using(function (QueryBuilder $query) use (&$dateFilter): string {
                                return static::formatMoney(
                                    static::paymentsForFooter($query, $dateFilter['from'], $dateFilter['to'])['sum']
                                );
                            })
                    ),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->toggleable()
                    ->getStateUsing(function (Model $record): float {
                        $ventas = 0;

                        foreach ($record->sales as $sale) {
                            foreach ($sale->tickets as $ticket) {
                                if (in_array($ticket->payment_method, ['cash', 'transfer'])) {
                                    $ventas += (float) $ticket->price;
                                }
                            }
                        }

                        $pagos = 0;

                        foreach ($record->payments as $payment) {
                            $pagos += (float) $payment->amount;
                        }

                        return $ventas - $pagos;
                    })
                    ->formatStateUsing(fn(float $state): string => static::formatMoney($state))
                    ->color(fn(float $state): string => $state > 0 ? 'warning' : ($state < 0 ? 'danger' : 'success'))
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(query: function (Builder $query, string $direction) use (&$dateFilter): Builder {
                        $ventas = static::ticketsSumSubquery(null, ['cash', 'transfer'], $dateFilter['from'], $dateFilter['to']);
                        $pagos = static::paymentsSumSubquery($dateFilter['from'], $dateFilter['to']);

                        return $query->orderByRaw(
                            '(' . $ventas->toSql() . ') - (' . $pagos->toSql() . ') ' . ($direction === 'asc' ? 'asc' : 'desc'),
                            [...$ventas->getBindings(), ...$pagos->getBindings()]
                        );
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->extraAttributes(['class' => 'hidden md:flex md:flex-col'])
                            ->using(function (QueryBuilder $query) use (&$dateFilter): string {
                                $ventas = static::sumTicketsForFooter(
                                    $query,
                                    null,
                                    ['cash', 'transfer'],
                                    $dateFilter['from'],
                                    $dateFilter['to']
                                );
                                $pagos = static::paymentsForFooter($query, $dateFilter['from'], $dateFilter['to'])['sum'];

                                return static::formatMoney($ventas - $pagos);
                            })
                    ),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->filters([
                Filter::make('date_range')
                    ->form([
                        Grid::make(2)->schema([
                            \Filament\Forms\Components\DatePicker::make('from')
                                ->label('Desde')
                                ->default(Carbon::now()->startOfMonth()),
                            \Filament\Forms\Components\DatePicker::make('to')
                                ->label('Hasta')
                                ->default(Carbon::now()->endOfMonth()),
                        ])
                    ])
                    ->query(function (Builder $query, array $data) use (&$dateFilter): Builder {
                        $from = $data['from'] ?? null;
                        $to = $data['to'] ?? null;

                        // El DatePicker entrega solo la fecha (sin hora), por lo que
                        // '<= $to' se interpretaba como '$to 00:00:00' y excluía las
                        // ventas cargadas durante el día de hoy. Llevamos $to al final
                        // del día para incluir todas las ventas de esa fecha.
                        if ($to) {
                            $to = Carbon::parse($to)->endOfDay();
                        }

                        // Guardamos el rango vigente: lo leen los sortable(query:)
                        // y los Summarizer::using() de las columnas de arriba.
                        $dateFilter['from'] = $from;
                        $dateFilter['to'] = $to;

                        return $query
                            // Carga 'sales.tickets' acotado al mismo rango de fechas
                            // que filtra qué usuarios aparecen (whereHas abajo), para
                            // que los valores mostrados en cada fila coincidan.
                            ->with(['sales' => function ($query) use ($from, $to) {
                                $query->with('tickets');
                                if ($from) {
                                    $query->where('sale_date', '>=', $from);
                                }
                                if ($to) {
                                    $query->where('sale_date', '<=', $to);
                                }
                            }, 'payments' => function ($query) use ($from, $to) {
                                if ($from) {
                                    $query->where('payment_date', '>=', $from);
                                }
                                if ($to) {
                                    $query->where('payment_date', '<=', $to);
                                }
                            }])
                            ->when(
                                $from,
                                fn(Builder $query, $date): Builder => $query->whereHas('sales', function (Builder $query) use ($date) {
                                    $query->where('sale_date', '>=', $date);
                                })
                            )
                            ->when(
                                $to,
                                fn(Builder $query, $date): Builder => $query->whereHas('sales', function (Builder $query) use ($date) {
                                    $query->where('sale_date', '<=', $date);
                                })
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Desde: ' . Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['to'] ?? null) {
                            $indicators[] = 'Hasta: ' . Carbon::parse($data['to'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
                /* TrashedFilter::make(), */
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(1)
            ->persistFiltersInSession()
            ->hiddenFilterIndicators()
            ->recordActions([
                Action::make('view_tickets')
                    ->label('Ver detalle')
                    ->button()
                    ->color('gray')
                    ->hiddenLabel()
                    ->icon('heroicon-m-eye')
                    ->extraAttributes([
                        'title' => 'Ver detalle',
                    ])
                    ->modalHeading(fn(Model $record) => 'Detalle de ' . trim($record->name . ' ' . ($record->surname ?? '')))
                    ->modalDescription('Boletos vendidos y pagos recibidos en el período seleccionado')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(function (Model $record, Action $action) {
                        // El modal abre con el mismo rango de fechas que la tabla
                        // está mostrando en ese momento.
                        $livewire = $action->getLivewire();
                        $range = $livewire
                            ? data_get($livewire->getTableFiltersForm()?->getState(), 'date_range', [])
                            : [];

                        return view('filament.resources.sales.tickets-modal', [
                            'user' => $record,
                            'from' => $range['from'] ?? null,
                            'to' => $range['to'] ?? null,
                        ]);
                    })->extraAttributes(['class' => 'hidden md:inline-flex']),
                Action::make('register_payment')
                    ->label('Registrar pago')
                    ->button()
                    ->hiddenLabel()
                    ->color('success')
                    ->icon(Heroicon::Banknotes)
                    ->extraAttributes([
                        'title' => 'Registrar pago',
                    ])
                    ->modalHeading(fn(Model $record) => 'Registrar pago de ' . trim($record->name . ' ' . ($record->surname ?? '')))
                    ->modalDescription('Cargá un pago recibido de este vendedor')
                    ->modalSubmitActionLabel('Guardar pago')
                    ->form([
                        TextInput::make('amount')
                            ->label('Monto')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('$')
                            ->placeholder('0,00'),
                        Select::make('payment_method')
                            ->label('Método de pago')
                            ->options([
                                'cash' => 'Efectivo',
                                'transfer' => 'Transferencia',
                            ])
                            ->required()
                            ->default('cash'),
                        DatePicker::make('payment_date')
                            ->label('Fecha de recepción')
                            ->required()
                            ->default(now()),
                    ])
                    ->action(function (Model $record, array $data): void {
                        $record->payments()->create([
                            'amount' => $data['amount'],
                            'payment_method' => $data['payment_method'],
                            'payment_date' => $data['payment_date'],
                        ]);

                        Notification::make()
                            ->title('Pago registrado')
                            ->body(
                                'Se registró $' . number_format((float) $data['amount'], 0, ',', '.')
                                    . ' (' . ($data['payment_method'] === 'cash' ? 'efectivo' : 'transferencia') . ')'
                                    . ' de ' . trim($record->name . ' ' . ($record->surname ?? ''))
                            )
                            ->success()
                            ->duration(4000)
                            ->send();
                    })->extraAttributes(['class' => 'hidden md:inline-flex']),
                ActionGroup::make([
                    Action::make('view_tickets')
                        ->label('Ver detalle')
                        ->button()
                        ->color('gray')
                        ->hiddenLabel()
                        ->icon('heroicon-m-eye')
                        ->extraAttributes([
                            'title' => 'Ver detalle',
                        ])
                        ->modalHeading(fn(Model $record) => 'Detalle de ' . trim($record->name . ' ' . ($record->surname ?? '')))
                        ->modalDescription('Boletos vendidos y pagos recibidos en el período seleccionado')
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->modalContent(function (Model $record, Action $action) {
                            // El modal abre con el mismo rango de fechas que la tabla
                            // está mostrando en ese momento.
                            $livewire = $action->getLivewire();
                            $range = $livewire
                                ? data_get($livewire->getTableFiltersForm()?->getState(), 'date_range', [])
                                : [];

                            return view('filament.resources.sales.tickets-modal', [
                                'user' => $record,
                                'from' => $range['from'] ?? null,
                                'to' => $range['to'] ?? null,
                            ]);
                        }),
                    Action::make('register_payment')
                        ->label('Registrar pago')
                        ->button()
                        ->hiddenLabel()
                        ->color('success')
                        ->icon(Heroicon::Banknotes)
                        ->extraAttributes([
                            'title' => 'Registrar pago',
                        ])
                        ->modalHeading(fn(Model $record) => 'Registrar pago de ' . trim($record->name . ' ' . ($record->surname ?? '')))
                        ->modalDescription('Cargá un pago recibido de este vendedor')
                        ->modalSubmitActionLabel('Guardar pago')
                        ->form([
                            TextInput::make('amount')
                                ->label('Monto')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->prefix('$')
                                ->placeholder('0,00'),
                            Select::make('payment_method')
                                ->label('Método de pago')
                                ->options([
                                    'cash' => 'Efectivo',
                                    'transfer' => 'Transferencia',
                                ])
                                ->required()
                                ->default('cash'),
                            DatePicker::make('payment_date')
                                ->label('Fecha de recepción')
                                ->required()
                                ->default(now()),
                        ])
                        ->action(function (Model $record, array $data): void {
                            $record->payments()->create([
                                'amount' => $data['amount'],
                                'payment_method' => $data['payment_method'],
                                'payment_date' => $data['payment_date'],
                            ]);

                            Notification::make()
                                ->title('Pago registrado')
                                ->body(
                                    'Se registró $' . number_format((float) $data['amount'], 0, ',', '.')
                                        . ' (' . ($data['payment_method'] === 'cash' ? 'efectivo' : 'transferencia') . ')'
                                        . ' de ' . trim($record->name . ' ' . ($record->surname ?? ''))
                                )
                                ->success()
                                ->duration(4000)
                                ->send();
                        })
                ])->extraAttributes(['class' => 'md:hidden']),
                /*                 EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(), */
            ])
            ->toolbarActions([
                /*                 BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]), */])
            ->emptyStateHeading('No hay ventas')
            ->emptyStateDescription('No se encontraron ventas para el período seleccionado.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSales::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
