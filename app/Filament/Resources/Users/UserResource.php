<?php

namespace App\Filament\Resources\Users;

use App\Filament\Clusters\Users\UsersCluster;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $cluster = UsersCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static bool $hasTitleCaseModelLabel = false;

    /*     protected static string | UnitEnum | null $navigationGroup = 'Sistema'; */
    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->label('Usuario')
                    ->minLength(3)
                    ->maxLength(255)
                    ->required()
                    ->unique()
                    ->validationMessages([
                        'min' => 'El nombre de usuario debe tener al menos :min caracteres.',
                        'required' => 'El nombre de usuario es obligatorio.',
                        'max' => 'El nombre de usuario no debe exceder los :max caracteres.',
                        'unique' => 'El nombre de usuario ya está en uso.',
                    ]),
                TextInput::make('password')
                    /* ->password() */
                    ->label('Contraseña')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                    ->visible(fn (): bool => (bool) Auth::user()?->is_admin)
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Dejá vacío para mantener la contraseña actual.'
                        : null)
                    ->validationMessages(['required' => 'El campo contraseña es obligatorio.']),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->validationMessages(['required' => 'El campo nombre es obligatorio.']),
                TextInput::make('surname')
                    ->label('Apellido')
                    ->maxLength(255)
                    ->nullable()
                    ->validationMessages([
                        'max' => 'El apellido no debe exceder los :max caracteres.',
                    ]),
                Select::make('roles')
                    ->label('Roles')
                    ->relationship('roles', 'name', modifyQueryUsing: function ($query) {
                        $query->where('guard_name', 'web')
                            ->orderBy('id');

                        // Si el usuario actual NO es super administrador, ocultamos la opción
                        if (! Auth::user()?->hasRole(Permissions::ROLE_SUPER)) {
                            $query->where('name', '!=', Permissions::ROLE_SUPER);
                        }
                    })
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->visible(fn (): bool => (bool) Auth::user()?->can('roles.assign'))
                    // Obligatorio: no se permiten usuarios sin rol. Se valida
                    // sólo cuando el campo es visible (quien no tiene
                    // roles.assign no puede asignar roles de todos modos).
                    // Red de seguridad extra: User::syncIsAdminFlag() asigna
                    // Vendedor a todo usuario que quede sin roles.
                    ->required(fn (): bool => (bool) Auth::user()?->can('roles.assign'))
                    ->validationMessages([
                        'required' => 'El usuario debe tener al menos un rol.',
                    ])
                    ->helperText('Obligatorio: el rol define qué acciones puede realizar el usuario en el sistema.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('User')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('id', '!=', 1)) // Excluir el usuario con ID 1
            ->columns([
                TextColumn::make('username')
                    ->label('Usuario')
                    ->visibleFrom('md')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->getStateUsing(fn (Model $record): string => $record->name.' '.($record->surname ?? ''))
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Permissions::ROLE_SUPER => 'danger',
                        Permissions::ROLE_ADMIN => 'success',
                        default => 'info',
                    }),
            ])
            ->recordAction(null)
            ->recordUrl(null)
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('roles')
                    ->label('Roles')
                    ->relationship('roles', 'name', modifyQueryUsing: function ($query) {
                        $query->where('guard_name', 'web')
                            ->orderBy('id');

                        // El rol Super Administrador sólo se muestra (y se
                        // puede filtrar) si el usuario logueado ES super
                        // administrador, igual que en el formulario.
                        if (! Auth::user()?->hasRole(Permissions::ROLE_SUPER)) {
                            $query->where('name', '!=', Permissions::ROLE_SUPER);
                        }
                    })
                    ->multiple()
                    ->preload(),
            ])
            // Filtros arriba de la tabla y aplicados al instante: sin botón
            // "Aplicar" (deferFilters(false)).
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordActions([
                // Menú de tres puntitos (sólo celular): los ítems conservan
                // su label para que el menú abierto muestre el texto.
                ActionGroup::make([
                    EditAction::make()
                        ->disabled(fn (User $record): bool => $record->id === 2)->button()
                        ->after(fn (User $record) => $record->syncIsAdminFlag()),
                    static::resetPasswordAction()->button(),
                    DeleteAction::make()->button()->disabled(fn (User $record): bool => $record->id === 2),
                    ForceDeleteAction::make()->button()->disabled(fn (User $record): bool => $record->id === 2),
                    RestoreAction::make()->button()->disabled(fn (User $record): bool => $record->id === 2),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                EditAction::make()
                    ->disabled(fn (User $record): bool => $record->id === 2)
                    ->button()->hiddenLabel()->extraAttributes([
                        'title' => 'Editar', 'class' => 'hidden md:inline-flex',
                    ])
                    ->after(fn (User $record) => $record->syncIsAdminFlag()),
                static::resetPasswordAction()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restablecer contraseña', 'class' => 'hidden md:inline-flex',
                ]),
                DeleteAction::make()->disabled(fn (User $record): bool => $record->id === 2)->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar', 'class' => 'hidden md:inline-flex',
                ]),
                ForceDeleteAction::make()->disabled(fn (User $record): bool => $record->id === 2)->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente', 'class' => 'hidden md:inline-flex',
                ]),
                RestoreAction::make()->disabled(fn (User $record): bool => $record->id === 2)->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar', 'class' => 'hidden md:inline-flex',
                ]),
            ])
            ->toolbarActions([]);
    }

    /**
     * Restablecer contraseña: se instancia dos veces (copia en el menú de
     * tres puntitos para mobile y botón directo para desktop), así que el
     * cuerpo vive acá y cada copia agrega sus atributos de visibilidad.
     */
    private static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Restablecer contraseña')
            ->icon(Heroicon::Key)
            ->color('info')
            ->action(function (User $record) {
                $newPassword = $record->username;
                $record->password = bcrypt($newPassword);
                $record->save();

                Notification::make()
                    ->title('Contraseña restablecida')
                    ->body('El nombre de usuario y la nueva contraseña es: '.$newPassword)
                    ->success()
                    ->icon('heroicon-o-key')
                    ->iconColor('info')
                    ->duration(3000)
                    ->send();
            })
            ->requiresConfirmation()
            ->disabled(fn (User $record): bool => $record->id === 2);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    // oculto el recurso para usuarios que no son administradores
    /*     public static function canViewAny(): bool
    {
        return auth()->user()?->is_admin == true;
    } */
}
