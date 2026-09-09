<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Clusters\Sales\SalesCluster;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentsExcelService;
use App\Services\PaymentsPdfService;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $cluster = SalesCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static ?string $modelLabel = 'pago';
    protected static ?string $pluralModelLabel = 'Pagos';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    protected static function formatMoney(float $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }

    /**
     * Subquery reutilizable: suma de montos de pagos por método (o varios),
     * en el rango de fechas vigente. Se usa para ordenar las columnas de
     * montos y para los totales del footer.
     */
    protected static function paymentsSumSubquery(?string $paymentMethod, array $paymentMethods, ?string $from, ?string $to): QueryBuilder
    {
        $query = DB::table('payments')
            ->selectRaw('COALESCE(SUM(payments.amount), 0)')
            ->whereColumn('payments.user_id', 'users.id')
            ->whereNull('payments.deleted_at')
            ->when($from, fn ($q, $date) => $q->where('payments.payment_date', '>=', $date))
            ->when($to, fn ($q, $date) => $q->where('payments.payment_date', '<=', $date));

        if ($paymentMethod !== null) {
            $query->where('payments.payment_method', $paymentMethod);
        } else {
            $query->whereIn('payments.payment_method', $paymentMethods);
        }

        return $query;
    }

    /**
     * Total para el footer: toma los IDs de pagos que la tabla ya está
     * mostrando (respeta todos los filtros) y suma/cuenta sobre ellos.
     *
     * @param  Builder|QueryBuilder  $query
     * @return array{count: int, cash: float, transfer: float, total: float}
     */
    protected static function footerTotals($query): array
    {
        $paymentIds = (clone $query)->pluck('payments.id');

        $base = DB::table('payments')
            ->whereNull('payments.deleted_at')
            ->whereIn('payments.id', $paymentIds);

        return [
            'count' => (clone $base)->count(),
            'cash' => (float) (clone $base)->where('payment_method', 'cash')->sum('amount'),
            'transfer' => (float) (clone $base)->where('payment_method', 'transfer')->sum('amount'),
            'total' => (float) (clone $base)->whereIn('payment_method', ['cash', 'transfer'])->sum('amount'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Vendedor')
                    ->relationship('user', 'name')
                    ->getOptionLabelFromRecordUsing(fn(User $record): string => trim($record->name . ' ' . ($record->surname ?? '')))
                    
                    ->preload()
                    ->required()
                    ->validationMessages([
                        'required' => 'El campo vendedor es obligatorio.',
                    ]),
                TextInput::make('amount')
                    ->label('Monto')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->prefix('$')
                    ->placeholder('0,00')
                    ->validationMessages([
                        'required' => 'El campo monto es obligatorio.',
                        'numeric' => 'El campo monto debe ser un número.',
                        'min' => 'El monto no puede ser negativo.',
                    ]),
                Select::make('payment_method')
                    ->label('Método de pago')
                    ->options([
                        'cash' => 'Efectivo',
                        'transfer' => 'Transferencia',
                    ])
                    ->required()
                    ->default('cash')
                    ->validationMessages([
                        'required' => 'El campo método de pago es obligatorio.',
                    ]),
                DatePicker::make('payment_date')
                    ->label('Fecha de recepción')
                    ->required()
                    ->default(now())
                    ->validationMessages([
                        'required' => 'El campo fecha de recepción es obligatorio.',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                /*  TextColumn::make('id')
                    ->label('N°')
                    s
                    ->alignCenter() */
                TextColumn::make('payment_date')
                    ->label('Fecha de pago')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                TextColumn::make('user.name')
                    ->label('Vendedor')
                    ->getStateUsing(fn(Model $record): string => $record->user
                        ? trim($record->user->name . ' ' . ($record->user->surname ?? ''))
                        : '—')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->formatStateUsing(fn(Model $record): string => static::formatMoney((float) $record->amount))
                    ->weight('bold')
                    ->sortable()
                    ->alignEnd()
                    ->summarize([
                        /*Summarizer::make()
                            ->label('Cantidad')
                             ->using(function (QueryBuilder $query): int {
                                return static::footerTotals($query)['count'];
                            }),
                        Summarizer::make()
                            ->label('Efectivo')
                            ->using(function (QueryBuilder $query): string {
                                return static::formatMoney(static::footerTotals($query)['cash']);
                            }),
                        Summarizer::make()
                            ->label('Transferencia')
                            ->using(function (QueryBuilder $query): string {
                                return static::formatMoney(static::footerTotals($query)['transfer']);
                            }), */
                        Summarizer::make()
                            ->label('Total cobrado')
                            ->using(function (QueryBuilder $query): string {
                                return static::formatMoney(static::footerTotals($query)['total']);
                            }),
                    ]),
                TextColumn::make('payment_method')
                    ->label('Método')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'cash' ? 'success' : 'info')
                    ->formatStateUsing(fn(string $state): string => $state === 'cash' ? 'Efectivo' : 'Transferencia')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Cargado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->hidden(),
            ])
            ->defaultSort('payment_date', 'desc')
            ->recordAction(false)
            ->recordUrl(null)
            ->filters([
                Filter::make('date_range')
                    ->form([
                        Grid::make(2)->schema([
                            DatePicker::make('from')
                                ->label('Desde')
                                ->default(Carbon::now()->startOfMonth()),
                            DatePicker::make('to')
                                ->label('Hasta')
                                ->default(Carbon::now()->endOfMonth()),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $to = $data['to'] ?? null;

                        // Mismo criterio que Ventas: el DatePicker entrega solo la
                        // fecha, así que llevamos 'to' al final del día para no
                        // excluir pagos cargados durante esa jornada.
                        if ($to) {
                            $to = Carbon::parse($to)->endOfDay();
                        }

                        return $query
                            ->when($from, fn (Builder $q) => $q->where('payment_date', '>=', $from))
                            ->when($to, fn (Builder $q) => $q->where('payment_date', '<=', $to));
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
                Filter::make('seller')
                    ->form([
                        Select::make('user_id')
                            ->label('Vendedor')
                            ->options(fn () => User::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (User $u) => [
                                    $u->id => trim($u->name . ' ' . ($u->surname ?? '')),
                                ]))
                            ->placeholder('Todos los vendedores'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['user_id'] ?? null, fn (Builder $q, $userId) => $q->where('user_id', $userId));
                    }),
                TrashedFilter::make(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->hiddenFilterIndicators()
            ->headerActions([
                Action::make('export_pdf')
                    ->label('PDF')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (ManagePayments $livewire) {
                        $filters = data_get($livewire->getTableFiltersForm()?->getState(), 'date_range', []);
                        $query = $livewire->getFilteredTableQuery()
                            ?? static::getEloquentQuery();

                        return app(PaymentsPdfService::class)->download(
                            (clone $query)->orderBy('payment_date')->get(),
                            $filters,
                            static::footerTotals($query),
                            static::getExportFilename('pdf', $filters)
                        );
                    }),
                Action::make('export_excel')
                    ->label('Excel')
                    ->color('success')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (ManagePayments $livewire) {
                        $filters = data_get($livewire->getTableFiltersForm()?->getState(), 'date_range', []);
                        $query = $livewire->getFilteredTableQuery()
                            ?? static::getEloquentQuery();

                        return app(PaymentsExcelService::class)->download(
                            (clone $query)->orderBy('payment_date')->get(),
                            $filters,
                            static::footerTotals($query),
                            static::getExportFilename('xlsx', $filters)
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Editar',
                ]),
                DeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar',
                ]),
                ForceDeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente',
                ]),
                RestoreAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar',
                ]),
            ])
            ->toolbarActions([
                /*  BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]), */])
            ->emptyStateHeading('No hay pagos')
            ->emptyStateDescription('Registrá un pago desde la vista de Ventas o con el botón "Nuevo pago".');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }

    /**
     * Usuario reservado para pruebas (id=1): sus pagos solo se muestran
     * cuando él mismo está logueado.
     */
    protected const TEST_USER_ID = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(auth()->id() !== self::TEST_USER_ID, fn (Builder $query) => $query->where('payments.user_id', '!=', self::TEST_USER_ID));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    protected static function getExportFilename(string $extension, array $dateRange): string
    {
        $from = filled($dateRange['from'] ?? null)
            ? Carbon::parse($dateRange['from'])->format('d-m-Y')
            : 'inicio';
        $to = filled($dateRange['to'] ?? null)
            ? Carbon::parse($dateRange['to'])->format('d-m-Y')
            : 'hoy';

        return "Pagos_{$from}_{$to}.{$extension}";
    }
}
