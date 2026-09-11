<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Models\Clients;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ClientsResource extends Resource
{
    protected static ?string $model = Clients::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserCircle;

    protected static ?string $modelLabel = 'cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static bool $hasTitleCaseModelLabel = false;
    /*     protected static string | UnitEnum | null $navigationGroup = 'Sistema'; */
    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'dni';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('apellido')
                    ->label('Apellido')
                    ->minLength(2)
                    ->maxLength(255)
                    ->required()
                    ->regex('/^[\pL\s\'\-\x{2019}]+$/u') // Letras, acentos, espacios, apóstrofo (') y guión
                    ->validationMessages([
                        'min' => 'El apellido debe tener al menos :min caracteres.',
                        'required' => 'El apellido es obligatorio.',
                        'max' => 'El apellido no debe exceder los :max caracteres.',
                        'regex' => 'El apellido solo puede contener letras y espacios.',
                    ]),
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->minLength(2)
                    ->maxLength(255)
                    ->required()
                    ->regex('/^[\pL\s\'\-\x{2019}]+$/u') // Letras, acentos, espacios, apóstrofo (') y guión
                    ->validationMessages([
                        'min' => 'El nombre debe tener al menos :min caracteres.',
                        'required' => 'El nombre es obligatorio.',
                        'max' => 'El nombre no debe exceder los :max caracteres.',
                        'regex' => 'El nombre solo puede contener letras y espacios.',
                    ]),
                TextInput::make('dni')
                    ->required()
                    ->label('DNI')
                    ->unique()
                    ->numeric()
                    ->minLength(6)
                    ->maxLength(9)
                    ->rules([
                        'digits_between:7,8',
                    ])
                    ->validationMessages([
                        'min' => 'El DNI debe tener al menos :min caracteres.',
                        'required' => 'El DNI es obligatorio.',
                        'max' => 'El DNI no debe exceder los :max caracteres.',
                        'unique' => 'El DNI ya está en uso.',
                        'numeric' => 'El DNI debe ser un número.',
                        'digits_between' => 'El DNI debe tener entre 7 y 8 dígitos.',
                    ]),
                TextInput::make('telefono')
                    ->label('Teléfono')
                    ->numeric()
                    ->minLength(6)
                    ->maxLength(20)
                    ->rules([
                        'digits_between:7,12',
                    ])
                    ->validationMessages([
                        'min' => 'El teléfono debe tener al menos :min caracteres.',
                        'max' => 'El teléfono no debe exceder los :max caracteres.',
                        'numeric' => 'El teléfono debe ser un número.',
                        'digits_between' => 'El teléfono debe tener entre 7 y 12 dígitos.',
                    ]),
                Toggle::make('can_buy')
                    ->label('¿Puede comprar boletos?')
                    ->helperText('Si se deshabilita, el cliente queda baneado y no se le podrán vender boletos.')
                    ->default(true)
                    ->live()
                    ->columnSpanFull(),
                Textarea::make('comments')
                    ->label('Motivo / Comentarios')
                    ->helperText('Se muestra al vendedor cuando intenta venderle a un cliente baneado.')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('nombre'),
                TextEntry::make('apellido'),
                TextEntry::make('dni'),
                TextEntry::make('telefono'),
                TextEntry::make('can_buy')
                    ->label('¿Puede comprar?')
                    ->icon(fn (Clients $record): Heroicon => $record->can_buy ? Heroicon::CheckCircle : Heroicon::NoSymbol)
                    ->color(fn (Clients $record): string => $record->can_buy ? 'success' : 'danger'),
                TextEntry::make('comments')
                    ->label('Motivo / Comentarios')
                    ->placeholder('—'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('dni')
            ->recordClasses(fn (Clients $record): array => $record->can_buy ? [] : [
                '!bg-red-100',
                'hover:!bg-red-200',
            ])
            ->columns([
                TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable(),
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('apellido')
                    ->label('Apellido')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('can_buy')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Clients $record): string => $record->can_buy ? 'Habilitado' : 'Baneado')
                    ->color(fn (Clients $record): string => $record->can_buy ? 'success' : 'danger'),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->filters([/* 
                TrashedFilter::make(), */])
            ->recordActions([
                Action::make('toggleBan')
                    ->visible(fn() => Auth::user()?->is_admin)
                    ->label(fn (Clients $record): string => $record->can_buy ? 'Banear' : 'Habilitar')
                    ->icon(fn (Clients $record): Heroicon => $record->can_buy ? Heroicon::NoSymbol : Heroicon::CheckCircle)
                    ->color(fn (Clients $record): string => $record->can_buy ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Clients $record): string => $record->can_buy ? 'Banear cliente' : 'Habilitar cliente')
                    ->modalDescription(fn (Clients $record): string => $record->can_buy
                        ? "¿Confirmás banear a {$record->nombre} {$record->apellido}? No se le podrán vender más boletos."
                        : "¿Confirmás habilitar a {$record->nombre} {$record->apellido}? Volverá a poder comprar boletos.")
                    ->modalSubmitActionLabel(fn (Clients $record): string => $record->can_buy ? 'Banear' : 'Habilitar')
                    ->action(function (Clients $record): void {
                        $record->update(['can_buy' => ! $record->can_buy]);

                        Notification::make()
                            ->title($record->can_buy ? 'Cliente habilitado' : 'Cliente baneado')
                            ->body("{$record->nombre} {$record->apellido} " . ($record->can_buy ? 'puede volver a comprar boletos.' : 'no podrá comprar boletos.'))
                            ->success()
                            ->send();
                    })
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes(['title' => 'Banear / Habilitar']),
                EditAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Editar',
                ]),
                DeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar',
                ])->visible(fn() => Auth::user()?->is_admin),
                ForceDeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente',
                ]),
                RestoreAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar',
                ]),
            ])
            ->toolbarActions([
                /* BulkActionGroup::make([
                    DeleteBulkAction::make(),
                                         ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(), 
                ]), */
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClients::route('/'),
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
