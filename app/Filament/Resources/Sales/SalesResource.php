<?php

namespace App\Filament\Resources\Sales;

use App\Filament\Clusters\Sales\SalesCluster;
use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Models\PaymentMethod;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

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

    public static function canViewAny(): bool
    {
        // Este resource lista vendedores (model User): sin este override,
        // Filament resolvía UserPolicy y la página exigía users.view_any en
        // lugar de los permisos sales.* del catálogo (rol con "Ventas" no
        // entraba). La página se autoriza con el módulo sales.*.
        return (bool) auth()->user()?->can('sales.view_any');
    }

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
        return '$'.number_format($amount, 0, ',', '.');
    }

    /**
     * Filtros vigentes de la tabla (rango y métodos de pago), normalizados.
     * "Hasta" incluye el día completo: el DatePicker entrega solo la fecha y
     * '<= $to' excluía las ventas del último día.
     *
     * Se lee del estado del componente porque los totales se calculan en la
     * query base (modifyQueryUsing): Filament aplica los filtros dentro de un
     * where anidado, donde un select agregado se pierde.
     *
     * @return array{from: ?string, to: ?Carbon, methods: list<string>}
     */
    public static function salesFilterState(mixed $livewire): array
    {
        $filters = is_object($livewire) && property_exists($livewire, 'tableFilters')
            ? ($livewire->tableFilters ?? [])
            : [];

        $from = data_get($filters, 'date_range.from');
        $to = data_get($filters, 'date_range.to');

        return [
            'from' => filled($from) ? (string) $from : null,
            'to' => filled($to) ? Carbon::parse($to)->endOfDay() : null,
            'methods' => array_values(array_filter(
                (array) (data_get($filters, 'payment_method.methods') ?? []),
                fn (mixed $code): bool => is_string($code) && $code !== '',
            )),
        ];
    }

    /**
     * Boletos vigentes de ventas vigentes del vendedor (users.id de la query
     * externa) en el rango y los métodos indicados. Base de los conteos,
     * sumas y del criterio "aparece en el período".
     *
     * @param  list<string>  $methods
     */
    protected static function ticketsBaseSubquery(?string $from, ?Carbon $to, array $methods): QueryBuilder
    {
        return DB::table('tickets')
            ->join('sales', 'sales.id', '=', 'tickets.sale_id')
            ->whereColumn('sales.user_id', 'users.id')
            ->whereNull('sales.deleted_at')
            ->whereNull('tickets.deleted_at')
            ->when($from, fn ($q, $date) => $q->where('sales.sale_date', '>=', $date))
            ->when($to, fn ($q, $date) => $q->where('sales.sale_date', '<=', $date))
            ->when($methods !== [], fn ($q) => $q->whereIn('tickets.payment_method', $methods));
    }

    /**
     * Pagos vigentes recibidos del vendedor en el rango y los métodos indicados.
     *
     * @param  list<string>  $methods
     */
    protected static function paymentsBaseSubquery(?string $from, ?Carbon $to, array $methods): QueryBuilder
    {
        return DB::table('payments')
            ->whereColumn('payments.user_id', 'users.id')
            ->whereNull('payments.deleted_at')
            ->when($from, fn ($q, $date) => $q->where('payments.payment_date', '>=', $date))
            ->when($to, fn ($q, $date) => $q->where('payments.payment_date', '<=', $date))
            ->when($methods !== [], fn ($q) => $q->whereIn('payments.payment_method', $methods));
    }

    /**
     * Agrega a la query de vendedores los totales del período (boletos,
     * ventas, pagos y saldo) como columnas calculadas en la base, y deja solo
     * a quienes tienen al menos un boleto o un pago dentro del rango y los
     * métodos filtrados. Es la única regla de cálculo: la usan las filas, el
     * ordenamiento y la tarjeta de resumen.
     *
     * @param  array{from: ?string, to: ?Carbon, methods: list<string>}  $state
     */
    public static function applySalesTotals(Builder $query, array $state): Builder
    {
        ['from' => $from, 'to' => $to, 'methods' => $methods] = $state;

        $ventas = static::ticketsBaseSubquery($from, $to, $methods)->selectRaw('COALESCE(SUM(tickets.price), 0)');
        $pagos = static::paymentsBaseSubquery($from, $to, $methods)->selectRaw('COALESCE(SUM(payments.amount), 0)');

        return $query
            ->select('users.*')
            ->selectSub(static::ticketsBaseSubquery($from, $to, $methods)->selectRaw('COUNT(tickets.id)'), 'tickets_count')
            ->selectSub($ventas, 'total_amount')
            ->selectSub(static::paymentsBaseSubquery($from, $to, $methods)->selectRaw('COUNT(payments.id)'), 'payments_count')
            ->selectSub($pagos, 'payments_total')
            ->selectRaw(
                '(('.$ventas->toSql().') - ('.$pagos->toSql().')) as saldo',
                [...$ventas->getBindings(), ...$pagos->getBindings()],
            )
            ->where(fn (Builder $q) => $q
                ->whereExists(static::ticketsBaseSubquery($from, $to, $methods)->selectRaw('1'))
                ->orWhereExists(static::paymentsBaseSubquery($from, $to, $methods)->selectRaw('1')));
    }

    /**
     * Totales de la tarjeta de resumen: suma las mismas columnas calculadas
     * que muestran las filas sobre la query ya filtrada (fechas, métodos y
     * búsqueda), así el resumen nunca difiere de la tabla.
     *
     * @return array{boletos: int, ventas: float, pagos_count: int, pagos: float, saldo: float, from: ?string, to: ?string}
     */
    public static function mobileSummaryTotals(): array
    {
        $livewire = \Livewire\Livewire::current();

        if (! $livewire instanceof \App\Filament\Resources\Sales\Pages\ManageSales) {
            return ['boletos' => 0, 'ventas' => 0.0, 'pagos_count' => 0, 'pagos' => 0.0, 'saldo' => 0.0, 'from' => null, 'to' => null];
        }

        $rows = $livewire->getFilteredTableQuery()->toBase()->get();

        $ventas = (float) $rows->sum('total_amount');
        $pagos = (float) $rows->sum('payments_total');
        $filters = $livewire->tableFilters ?? [];

        return [
            'boletos' => (int) $rows->sum('tickets_count'),
            'ventas' => $ventas,
            'pagos_count' => (int) $rows->sum('payments_count'),
            'pagos' => $pagos,
            'saldo' => $ventas - $pagos,
            'from' => data_get($filters, 'date_range.from'),
            'to' => data_get($filters, 'date_range.to'),
        ];
    }

    /**
     * Formato de moneda público para la vista del resumen mobile.
     */
    public static function formatMoneyPublic(float|int $amount): string
    {
        return static::formatMoney((float) $amount);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('username')
            // Totales del período calculados en la base (una sola query, sin
            // cargar ventas ni boletos): ver applySalesTotals().
            ->modifyQueryUsing(fn (Builder $query, mixed $livewire): Builder => static::applySalesTotals($query, static::salesFilterState($livewire)))
            ->columns([
                TextColumn::make('username')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable()
                    ->hidden(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->getStateUsing(fn (Model $record): string => $record->name.' '.($record->surname ?? ''))
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
                    ->getStateUsing(fn (Model $record): int => (int) $record->getAttribute('tickets_count'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('tickets_count', $direction)),
                // Una sola columna de dinero: el desglose por método de pago
                // vive en el filtro del modal de detalle y en su desglose plegable.
                TextColumn::make('total_amount')
                    ->label('Total ventas')
                    ->color('primary')
                    ->visibleFrom('md')
                    ->getStateUsing(fn (Model $record): string => static::formatMoney((float) $record->getAttribute('total_amount')))
                    ->weight('bold')
                    ->toggleable()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('total_amount', $direction)),
                TextColumn::make('payments_count')
                    ->label('Pagos')
                    ->badge()
                    ->visibleFrom('md')
                    ->color('warning')
                    ->icon('heroicon-o-banknotes')
                    ->iconPosition('after')
                    ->alignCenter()
                    ->toggleable()
                    ->getStateUsing(fn (Model $record): int => (int) $record->getAttribute('payments_count'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('payments_count', $direction)),
                TextColumn::make('payments_total')
                    ->label('Total pagos')
                    ->badge()
                    ->visibleFrom('md')
                    ->color('warning')
                    ->toggleable()
                    ->getStateUsing(fn (Model $record): string => static::formatMoney((float) $record->getAttribute('payments_total')))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('payments_total', $direction)),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->toggleable()
                    ->getStateUsing(fn (Model $record): float => (float) $record->getAttribute('saldo'))
                    ->formatStateUsing(fn (float $state): string => static::formatMoney($state))
                    ->color(fn (float $state): string => $state > 0 ? 'warning' : ($state < 0 ? 'danger' : 'success'))
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('saldo', $direction)),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            // Los filtros no modifican la query por sí mismos: su estado lo
            // lee applySalesTotals(), que combina rango y métodos en una sola
            // condición por relación.
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
                        ]),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Desde: '.Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['to'] ?? null) {
                            $indicators[] = 'Hasta: '.Carbon::parse($data['to'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
                Filter::make('payment_method')
                    ->form([
                        \Filament\Forms\Components\Select::make('methods')
                            ->label('Método de pago')
                            ->options(fn () => PaymentMethod::options())
                            ->multiple()
                            ->placeholder('Todos los métodos'),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $methods = array_values($data['methods'] ?? []);
                        if ($methods === []) {
                            return [];
                        }

                        $labels = array_map(fn (string $code) => PaymentMethod::label($code), $methods);

                        return ['Método: '.implode(', ', $labels)];
                    }),
                /* TrashedFilter::make(), */
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->paginated(false) // Las filas son vendedores (acotado); el resumen siempre ve todo el período.
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
                    ->visible(fn (): bool => (bool) auth()->user()?->can('sales.view'))
                    ->extraAttributes([
                        'title' => 'Ver detalle',
                    ])
                    ->modalHeading(fn (Model $record) => 'Detalle de '.trim($record->name.' '.($record->surname ?? '')))
                    ->modalDescription('Boletos vendidos y pagos recibidos en el período seleccionado')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (Model $record, Action $action) => static::ticketsModalContent($record, $action))->extraAttributes(['class' => 'hidden md:inline-flex', 'title' => 'Ver detalles']),
                Action::make('register_payment')
                    ->label('Registrar pago')
                    ->button()
                    ->hiddenLabel()
                    ->color('success')
                    ->icon(Heroicon::Banknotes)
                    // Registra un Payment: misma autorización que el botón
                    // "Nuevo pago" de la página Pagos (PaymentPolicy::create).
                    ->visible(fn (): bool => (bool) auth()->user()?->can('payments.create'))
                    ->extraAttributes([
                        'title' => 'Registrar pago',
                    ])
                    ->modalHeading(fn (Model $record) => 'Registrar pago de '.trim($record->name.' '.($record->surname ?? '')))
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
                            ->options(fn () => PaymentMethod::options())
                            ->required()
                            ->default(fn () => array_key_first(PaymentMethod::options())),
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
                                'Se registró $'.number_format((float) $data['amount'], 0, ',', '.')
                                    .' ('.PaymentMethod::label($data['payment_method']).')'
                                    .' de '.trim($record->name.' '.($record->surname ?? ''))
                            )
                            ->success()
                            ->duration(4000)
                            ->send();
                    })->extraAttributes(['class' => 'hidden md:inline-flex', 'title' => 'Registrar pago']),
                ActionGroup::make([
                    Action::make('view_tickets')
                        ->label('Ver detalle')
                        ->button()
                        ->color('gray')
                        ->icon('heroicon-m-eye')
                        ->visible(fn (): bool => (bool) auth()->user()?->can('sales.view'))
                        ->extraAttributes([
                            'title' => 'Ver detalle',
                        ])
                        ->modalHeading(fn (Model $record) => 'Detalle de '.trim($record->name.' '.($record->surname ?? '')))
                        ->modalDescription('Boletos vendidos y pagos recibidos en el período seleccionado')
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->modalContent(fn (Model $record, Action $action) => static::ticketsModalContent($record, $action)),
                    Action::make('register_payment')
                        ->label('Registrar pago')
                        ->button()
                        ->color('success')
                        ->icon(Heroicon::Banknotes)
                        ->visible(fn (): bool => (bool) auth()->user()?->can('payments.create'))
                        ->extraAttributes([
                            'title' => 'Registrar pago',
                        ])
                        ->modalHeading(fn (Model $record) => 'Registrar pago de '.trim($record->name.' '.($record->surname ?? '')))
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
                                ->options(fn () => PaymentMethod::options())
                                ->required()
                                ->default(fn () => array_key_first(PaymentMethod::options())),
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
                                    'Se registró $'.number_format((float) $data['amount'], 0, ',', '.')
                                        .' ('.PaymentMethod::label($data['payment_method']).')'
                                        .' de '.trim($record->name.' '.($record->surname ?? ''))
                                )
                                ->success()
                                ->duration(4000)
                                ->send();
                        }),
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

    /**
     * Contenido del modal "Ver detalle": abre con el mismo rango de fechas y
     * los mismos métodos de pago que la tabla está mostrando, para que los
     * totales del detalle coincidan con los de la fila.
     */
    protected static function ticketsModalContent(Model $record, Action $action): \Illuminate\Contracts\View\View
    {
        $state = $action->getLivewire()?->getTableFiltersForm()?->getState() ?? [];

        return view('filament.resources.sales.tickets-modal', [
            'user' => $record,
            'from' => data_get($state, 'date_range.from'),
            'to' => data_get($state, 'date_range.to'),
            'payments' => array_values(data_get($state, 'payment_method.methods') ?? []),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSales::route('/'),
        ];
    }

    /**
     * Usuario reservado para pruebas (id=1): sus ventas solo se muestran
     * cuando él mismo está logueado.
     */
    protected const TEST_USER_ID = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(auth()->id() !== self::TEST_USER_ID, fn (Builder $query) => $query->where('users.id', '!=', self::TEST_USER_ID));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
