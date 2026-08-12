<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Clusters\Sales\SalesCluster;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use App\Models\User;
use BackedEnum;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Vendedor')
                    ->relationship('user', 'name')
                    ->getOptionLabelFromRecordUsing(fn(User $record): string => trim($record->name . ' ' . ($record->surname ?? '')))
                    ->searchable()
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
                    ->sortable()
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->formatStateUsing(fn(Model $record): string => static::formatMoney((float) $record->amount))
                    ->weight('bold')
                    ->sortable()
                    ->alignEnd(),
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
                TrashedFilter::make(),
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

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
