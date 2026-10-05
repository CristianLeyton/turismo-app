<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Clusters\Users\UsersCluster;
use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * Autogestión de roles y permisos.
 *
 * La matriz de permisos se genera a partir del catálogo
 * (App\Support\Permissions): cada módulo del sistema agrupa sus acciones
 * como checkboxes. Los roles del sistema no se pueden borrar; "Super
 * Administrador" tampoco se puede editar (ya tiene bypass total).
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $cluster = UsersCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;

    protected static ?string $modelLabel = 'rol';

    protected static ?string $pluralModelLabel = 'Roles y permisos';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('roles.view_any');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('roles.create');
    }

    public static function canEdit($record): bool
    {
        if (! (bool) auth()->user()?->can('roles.update')) {
            return false;
        }

        // Super Administrador no se edita: ya tiene bypass de todo.
        return $record === null || $record->name !== Permissions::ROLE_SUPER;
    }

    public static function canDelete($record): bool
    {
        if (! (bool) auth()->user()?->can('roles.delete')) {
            return false;
        }

        return ! in_array($record->name, Permissions::systemRoles(), true);
    }

    public static function form(Schema $schema): Schema
    {
        // Matriz de permisos: un bloque de checkboxes por módulo del sistema.
        // Cada bloque escribe en el estado `permissions.{modulo}`; al guardar
        // se aplanan todos los módulos en la lista de permisos del rol.
        $permissionLists = [];

        foreach (Permissions::catalog() as $module => $config) {
            $options = collect($config['actions'])
                ->mapWithKeys(fn (string $label, string $action) => [
                    Permissions::permissionName($module, $action) => $label,
                ])
                ->all();

            $permissionLists[] = CheckboxList::make("permissions.{$module}")
                ->label($config['label'])
                ->options($options)
                ->bulkToggleable()
                ->columns(1);
        }

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre del rol')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Role $record): bool => $record !== null
                        && in_array($record->name, Permissions::systemRoles(), true))
                    ->dehydrated(fn (?Role $record): bool => $record === null
                        || ! in_array($record->name, Permissions::systemRoles(), true))
                    ->validationMessages([
                        'required' => 'El nombre del rol es obligatorio.',
                        'unique' => 'Ya existe un rol con ese nombre.',
                    ]),
                Grid::make(3)->components($permissionLists)->columnSpanFull(),
            ]);
    }

    /**
     * Aplana el estado `permissions.{modulo}` a la lista de nombres.
     *
     * @param  array<string, list<string>>  $permissionsByModule
     * @return list<string>
     */
    public static function flattenPermissions(array $permissionsByModule): array
    {
        return collect($permissionsByModule)
            ->flatten()
            ->filter()
            ->values()
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->where('guard_name', 'web')->withCount(['users', 'permissions']))
            ->columns([
                TextColumn::make('name')
                    ->label('Rol')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Permissions::ROLE_SUPER => 'danger',
                        Permissions::ROLE_ADMIN => 'success',
                        Permissions::ROLE_SELLER => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->alignCenter(),
                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make()
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes(['title' => 'Editar'])
                    ->fillForm(fn (Role $record): array => [
                        // fillForm REEMPLAZA el fill por defecto del EditAction
                        // (que era attributesToArray), así que hay que incluir
                        // también los atributos del rol, no sólo los permisos
                        // agrupados por módulo.
                        'name' => $record->name,
                        'guard_name' => $record->guard_name,
                        'permissions' => $record->permissions->pluck('name')
                            ->groupBy(fn (string $name) => str_contains($name, '.')
                                ? explode('.', $name)[0]
                                : 'otros')
                            ->all(),
                    ])
                    ->using(function (Role $record, array $data): void {
                        $record->update(collect($data)->except('permissions')->all());
                        $record->syncPermissions(static::flattenPermissions($data['permissions'] ?? []));
                    }),
                DeleteAction::make()
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes(['title' => 'Eliminar'])
                    ->modalDescription(function (Role $record): string {
                        $usersCount = $record->users()->count();

                        return $usersCount > 0
                            ? "⚠ Este rol tiene {$usersCount} usuario(s) asignado(s) y no se puede eliminar. Reasignalos a otro rol primero."
                            : 'Esta acción no se puede deshacer.';
                    })
                    ->before(function (DeleteAction $action, Role $record): void {
                        // Política de borrado: un rol con usuarios asignados no
                        // se elimina (quedarían sin acceso al sistema). Roles
                        // sin usuarios sí, para poder limpiar errores.
                        $usersCount = $record->users()->count();

                        if ($usersCount === 0) {
                            return;
                        }

                        Notification::make()
                            ->title('No se puede eliminar el rol')
                            ->body("\"{$record->name}\" tiene {$usersCount} usuario(s) asignado(s). Reasignalos a otro rol antes de eliminarlo.")
                            ->danger()
                            ->send();

                        $action->cancel();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }
}
