<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\TrashedFilter;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static ?string $modelLabel = 'método de pago';
    protected static ?string $pluralModelLabel = 'Métodos de pago';
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 8;

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
            ]);
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
            ->filters([
                /* TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos') */
                    TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make()->button()->hiddenLabel()->extraAttributes(['title' => 'Editar']),
                DeleteAction::make()
                    ->modalHeading('Eliminar método de pago')
                    ->modalDescription('El método quedará oculto pero los boletos y pagos históricos seguirán mostrando su nombre. Podrás restaurarlo desde el filtro "eliminados".')
                    ->modalSubmitActionLabel('Eliminar')
                    ->button()->hiddenLabel()->extraAttributes(['title' => 'Eliminar']),
                RestoreAction::make()->button()->hiddenLabel()->extraAttributes(['title' => 'Restaurar']),
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
