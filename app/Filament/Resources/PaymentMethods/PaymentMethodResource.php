<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static ?string $modelLabel = 'método de pago';

    protected static ?string $pluralModelLabel = 'Métodos de pago';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(100)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'El nombre del método es obligatorio.',
                        'max' => 'El nombre no debe exceder :max caracteres.',
                    ])
                    ->helperText('El código interno y el orden se generan automáticamente a partir del nombre.'),
                Select::make('color')
                    ->label('Color')
                    ->options(fn (): array => static::colorOptions())
                    ->allowHtml()
                    ->native(false)
                    ->required()
                    ->in(array_keys(PaymentMethod::PALETTE))
                    ->default(fn (): string => PaymentMethod::suggestedColor())
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Elegí un color para el método.',
                        'in' => 'Elegí un color de la lista.',
                    ])
                    ->helperText('Se usa en los badges de ventas, pagos y boletos. Al crear se sugiere uno que no use otro método.'),
            ]);
    }

    /**
     * Opciones del campo Color: cada una se muestra como el badge que va a
     * tener el método. El HTML es fijo (labels y clases de la paleta).
     *
     * @return array<string, string>
     */
    public static function colorOptions(): array
    {
        $options = [];

        foreach (PaymentMethod::PALETTE as $key => $entry) {
            $options[$key] = '<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold '.$entry['classes'].'">'.e($entry['label']).'</span>';
        }

        return $options;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                /* TextColumn::make('sort_order')
                    ->label('Orden')
                    ->alignCenter()
                    ->sortable(), */
                TextColumn::make('label')
                    ->label('Nombre')
                    ->badge()
                    // Desde el color guardado del registro (no desde el cache
                    // por código): refleja el cambio apenas se edita.
                    ->color(fn (PaymentMethod $record): string|array => PaymentMethod::paletteEntry($record->getAttributes()['color'] ?? null)['filament'] ?? 'gray')
                    ->sortable(),
                /*                 TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(), */
                /*                 IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-pause-circle')
                    ->trueColor('success')
                    ->falseColor('gray'), */
                /*                 TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->since()
                    ->placeholder('—')
                    ->alignCenter()
                    ->toggleable(), */
            ])
            ->recordAction(null)
            ->recordUrl(null)
            ->filters([
                /* TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos') */
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->button()->extraAttributes(['title' => 'Editar']),
                    DeleteAction::make()
                        ->modalHeading('Eliminar método de pago')
                        ->modalDescription('El método quedará oculto pero los boletos y pagos históricos seguirán mostrando su nombre. Podrás restaurarlo desde el filtro "eliminados".')
                        ->modalSubmitActionLabel('Eliminar')
                        ->button()->extraAttributes(['title' => 'Eliminar']),
                    RestoreAction::make()->button()->extraAttributes(['title' => 'Restaurar']),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                EditAction::make()->button()->hiddenLabel()->extraAttributes(['title' => 'Editar', 'class' => 'hidden md:inline-flex']),
                DeleteAction::make()
                    ->modalHeading('Eliminar método de pago')
                    ->modalDescription('El método quedará oculto pero los boletos y pagos históricos seguirán mostrando su nombre. Podrás restaurarlo desde el filtro "eliminados".')
                    ->modalSubmitActionLabel('Eliminar')
                    ->button()->hiddenLabel()->extraAttributes(['title' => 'Eliminar', 'class' => 'hidden md:inline-flex']),
                RestoreAction::make()->button()->hiddenLabel()->extraAttributes(['title' => 'Restaurar', 'class' => 'hidden md:inline-flex']),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentMethods::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Incluir soft-deleted en el listado para poder restaurarlos.
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
