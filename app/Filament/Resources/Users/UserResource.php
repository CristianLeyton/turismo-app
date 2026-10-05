<?php

namespace App\Filament\Resources\Users;

use App\Filament\Clusters\Users\UsersCluster;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
                    ->required()
                    ->label('Contraseña')
                    ->hiddenOn('edit')
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
                    ->relationship('roles', 'name', modifyQueryUsing: fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->orderBy('id'))
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->visible(fn (): bool => (bool) auth()->user()?->can('roles.assign'))
                    ->helperText('Todo usuario sin roles queda como Vendedor. El flag "es administrador" se sincroniza automáticamente según el rol asignado.'),
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
            ->filters([
                // TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->disabled(fn (User $record): bool => $record->id === 2)
                    ->button()->hiddenLabel()->extraAttributes([
                        'title' => 'Editar',
                    ])
                    ->after(fn (User $record) => $record->syncIsAdminFlag()),
                Action::make('resetPassword')
                    ->label('Restablecer contraseña')
                    ->icon(Heroicon::Key)
                    ->color('info')
                    ->action(function (User $record) {
                        $newPassword = $record->username;
                        $record->password = bcrypt($newPassword);
                        $record->save();

                        // Aquí puedes agregar lógica para notificar al usuario sobre su nueva contraseña
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
                    ->disabled(fn (User $record): bool => $record->id === 2)
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes([
                        'title' => 'Restablecer contraseña',
                    ]),

                DeleteAction::make()->disabled(fn (User $record): bool => $record->id === 2)->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar',
                ]),
                ForceDeleteAction::make()->disabled(fn (User $record): bool => $record->id === 2)->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente',
                ]),
                RestoreAction::make()->disabled(fn (User $record): bool => $record->id === 2)->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar',
                ]),
            ])
            ->toolbarActions([]);
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
