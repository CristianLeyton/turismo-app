<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Models\Clients;
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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Enums\FiltersLayout;

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
                    ->icon(fn(Clients $record): Heroicon => $record->can_buy ? Heroicon::CheckCircle : Heroicon::NoSymbol)
                    ->color(fn(Clients $record): string => $record->can_buy ? 'success' : 'danger'),
                TextEntry::make('comments')
                    ->label('Motivo / Comentarios')
                    ->placeholder('—'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('dni')
            ->recordClasses(fn(Clients $record): array => $record->can_buy ? [] : [
                '!bg-red-100',
                'hover:!bg-red-200',
            ])
            ->columns([
                TextColumn::make('dni')
                    ->label('DNI')
                    ->sortable()
                    ->tooltip(fn($state, $record) => $record->apellido . " " . $record->nombre)
                    ->searchable(),
                TextColumn::make('apellido')
                    ->label('Apellido')
                    ->limit(22)
                    ->sortable()
                    ->visibleFrom('md')
                    ->tooltip(fn($state, $record) => $record->apellido)
                    ->searchable(),
                TextColumn::make('nombre')
                    ->limit(22)
                    ->tooltip(fn($state, $record) => $record->nombre)
                    ->label('Nombre')
                    ->visibleFrom('md')
                    ->searchable(),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->visibleFrom('md')
                    ->searchable(),
                TextColumn::make('can_buy')
                    ->label('Estado')
                    ->badge()
                    ->state(fn(Clients $record): string => $record->can_buy ? 'Habilitado' : 'Baneado')
                    ->color(fn(Clients $record): string => $record->can_buy ? 'success' : 'danger'),
                TextColumn::make('boletos_historicos')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->label('Boletos')
                    ->tooltip('Total de boletos vendidos')
                    ->state(fn(Clients $record): int => $record->tickets()->count())
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withCount('tickets')
                            ->orderBy('tickets_count', $direction);
                    })
                    ->numeric(),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->filters([
                // Filtro de estado (baneado / habilitado).
                TernaryFilter::make('can_buy')
                    ->label('Estado')
                    ->attribute('can_buy')
                    ->placeholder('Todos')
                    ->trueLabel('Baneados')
                    ->falseLabel('Habilitados')
                    ->queries(
                        true: fn(Builder $query): Builder => $query->where('can_buy', false),
                        false: fn(Builder $query): Builder => $query->where('can_buy', true),
                        blank: fn(Builder $query): Builder => $query,
                    ),
            ])->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordActions([
                // Menú de tres puntitos (construido desde los builders,
                // con visibilidad duplicada para las acciones): sólo celular.
                ActionGroup::make([
                    static::toggleBanAction()->button(),
                    static::resetPasswordAction()->button(),
                    EditAction::make()->button(),
                    DeleteAction::make()->button()->visible(fn() => (bool) Auth::user()?->can('clients.delete')),
                    ForceDeleteAction::make()->button(),
                    RestoreAction::make()->button(),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                Action::make('toggleBan')
                    ->visible(fn() => (bool) Auth::user()?->can('clients.ban'))
                    ->label(fn(Clients $record): string => $record->can_buy ? 'Banear' : 'Habilitar')
                    ->icon(fn(Clients $record): Heroicon => $record->can_buy ? Heroicon::NoSymbol : Heroicon::CheckCircle)
                    ->color(fn(Clients $record): string => $record->can_buy ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn(Clients $record): string => $record->can_buy ? 'Banear cliente' : 'Habilitar cliente')
                    ->modalDescription(fn(Clients $record): string => $record->can_buy
                        ? "¿Confirmás banear a {$record->nombre} {$record->apellido}? No se le podrán vender más boletos."
                        : "¿Confirmás habilitar a {$record->nombre} {$record->apellido}? Volverá a poder comprar boletos.")
                    ->modalSubmitActionLabel(fn(Clients $record): string => $record->can_buy ? 'Banear' : 'Habilitar')
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
                    ->extraAttributes(['title' => 'Banear / Habilitar', 'class' => 'hidden md:inline-flex']),
                // Contraseña inicial del cliente = su DNI. Si ya se la cambió
                // y la olvidó, se resetea: vuelve a ser el DNI (sin hash) y al
                // próximo ingreso se le obliga a elegir otra.
                Action::make('resetPassword')
                    ->visible(fn() => (bool) Auth::user()?->can('clients.reset_password'))
                    ->label('Resetear contraseña')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Resetear contraseña')
                    ->modalDescription(fn(Clients $record): string => $record->hasPersonalPassword()
                        ? "\u00bfResetear la contrase\u00f1a de {$record->nombre} {$record->apellido}? Entrar\u00e1 con su DNI (como contrase\u00f1a inicial) y tendr\u00e1 que elegir una nueva al pr\u00f3ximo ingreso."
                        : "{$record->nombre} {$record->apellido} todav\u00eda no eligi\u00f3 su propia contrase\u00f1a: entra con su DNI.")
                    ->modalSubmitActionLabel('Resetear')
                    ->action(function (Clients $record): void {
                        // Vuelve al estado inicial: contraseña = su DNI (el
                        // cast 'hashed' la guarda bcrypt) y bandera de primer
                        // ingreso en true, para que el panel lo obligue a
                        // elegir una nueva.
                        $record->update([
                            'password' => $record->dni,
                            'must_change_password' => true,
                        ]);

                        Notification::make()
                            ->title('Contraseña reseteada')
                            ->body($record->nombre . ' ' . $record->apellido . ' entra con su DNI y elige una contrase\u00f1a nueva al pr\u00f3ximo ingreso.')
                            ->success()
                            ->send();
                    })
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes(['title' => 'Resetear contraseña', 'class' => 'hidden md:inline-flex']),
                EditAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Editar',
                    'class' => 'hidden md:inline-flex',
                ]),
                DeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar',
                    'class' => 'hidden md:inline-flex',
                ])->visible(fn() => (bool) Auth::user()?->can('clients.delete')),
                ForceDeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente',
                    'class' => 'hidden md:inline-flex',
                ]),
                RestoreAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar',
                    'class' => 'hidden md:inline-flex',
                ]),
            ])
            ->toolbarActions([
                /* BulkActionGroup::make([
                    DeleteBulkAction::make(),
                                         ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]), */]);
    }

    /**
     * Banear / habilitar cliente: se instancia dos veces (copia en el menú de
     * tres puntitos para mobile y botón directo para desktop), así que el
     * cuerpo vive acá y cada copia agrega sus atributos de visibilidad.
     */
    private static function toggleBanAction(): Action
    {
        return Action::make('toggleBan')
            ->visible(fn() => (bool) Auth::user()?->can('clients.ban'))
            ->label(fn(Clients $record): string => $record->can_buy ? 'Banear' : 'Habilitar')
            ->icon(fn(Clients $record): Heroicon => $record->can_buy ? Heroicon::NoSymbol : Heroicon::CheckCircle)
            ->color(fn(Clients $record): string => $record->can_buy ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalHeading(fn(Clients $record): string => $record->can_buy ? 'Banear cliente' : 'Habilitar cliente')
            ->modalDescription(fn(Clients $record): string => $record->can_buy
                ? "¿Confirmás banear a {$record->nombre} {$record->apellido}? No se le podrán vender más boletos."
                : "¿Confirmás habilitar a {$record->nombre} {$record->apellido}? Volverá a poder comprar boletos.")
            ->modalSubmitActionLabel(fn(Clients $record): string => $record->can_buy ? 'Banear' : 'Habilitar')
            ->action(function (Clients $record): void {
                $record->update(['can_buy' => ! $record->can_buy]);

                Notification::make()
                    ->title($record->can_buy ? 'Cliente habilitado' : 'Cliente baneado')
                    ->body("{$record->nombre} {$record->apellido} " . ($record->can_buy ? 'puede volver a comprar boletos.' : 'no podrá comprar boletos.'))
                    ->success()
                    ->send();
            });
    }

    /** Resetear contraseña a su DNI (misma lógica en las dos copias). */
    private static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->visible(fn() => (bool) Auth::user()?->can('clients.reset_password'))
            ->label('Resetear contraseña')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Resetear contraseña')
            ->modalDescription(fn(Clients $record): string => $record->hasPersonalPassword()
                ? "\u00bfResetear la contrase\u00f1a de {$record->nombre} {$record->apellido}? Entrar\u00e1 con su DNI (como contrase\u00f1a inicial) y tendr\u00e1 que elegir una nueva al pr\u00f3ximo ingreso."
                : "{$record->nombre} {$record->apellido} todav\u00eda no eligió su propia contrase\u00f1a: entra con su DNI.")
            ->modalSubmitActionLabel('Resetear')
            ->action(function (Clients $record): void {
                // Vuelve al estado inicial: contraseña = su DNI (el
                // cast 'hashed' la guarda bcrypt) y bandera de primer
                // ingreso en true, para que el panel lo obligue a
                // elegir una nueva.
                $record->update([
                    'password' => $record->dni,
                    'must_change_password' => true,
                ]);

                Notification::make()
                    ->title('Contraseña reseteada')
                    ->body($record->nombre . ' ' . $record->apellido . ' entra con su DNI y elige una contrase\u00f1a nueva al pr\u00f3ximo ingreso.')
                    ->success()
                    ->send();
            });
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
