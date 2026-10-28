<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Clusters\Users\UsersCluster;
use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Support\Permissions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ActionGroup;
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
use Illuminate\Support\Facades\Auth;

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
        return (bool) Auth::user()?->can('roles.view_any');
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->can('roles.create');
    }

    public static function canEdit($record): bool
    {
        if (! (bool) Auth::user()?->can('roles.update')) {
            return false;
        }

        // Super Administrador no se edita: ya tiene bypass de todo.
        return $record === null || $record->name !== Permissions::ROLE_SUPER;
    }

    public static function canDelete($record): bool
    {
        if (! (bool) Auth::user()?->can('roles.delete')) {
            return false;
        }

        return ! in_array($record->name, Permissions::systemRoles(), true);
    }

    public static function form(Schema $schema): Schema
    {
        // Matriz de permisos: un bloque de checkboxes por módulo del sistema.
        // Cada bloque escribe en el estado `permissions.{modulo}`; al guardar
        // se aplanan todos los módulos en la lista de permisos del rol.
        //
        // Restricción: el editor sólo puede otorgar los permisos que él
        // mismo posee, así que cada bloque ofrece únicamente sus acciones
        // dentro de ese conjunto. Los módulos donde no tiene NINGÚN permiso
        // se omiten por completo. El SUPER ve todo el catálogo.
        $grantable = static::grantablePermissions();

        $permissionLists = [];

        foreach (Permissions::catalog() as $module => $config) {
            $options = collect($config['actions'])
                ->filter(fn (string $label, string $action): bool => in_array(
                    Permissions::permissionName($module, $action),
                    $grantable,
                    true,
                ))
                ->mapWithKeys(fn (string $label, string $action) => [
                    Permissions::permissionName($module, $action) => $label,
                ])
                ->all();

            if ($options === []) {
                continue;
            }

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
     * Además descarta todo permiso que el usuario actual no pueda otorgar
     * (sÃ³lo se sincroniza lo que el editor tiene en su propio alcance). Es
     * el choke point de guardado: protege tanto el alta como la edición
     * contra payloads manipulados desde el cliente.
     *
     * @param  array<string, list<string>>  $permissionsByModule
     * @return list<string>
     */
    public static function flattenPermissions(array $permissionsByModule): array
    {
        $grantable = static::grantablePermissions();

        return collect($permissionsByModule)
            ->flatten()
            ->filter()
            ->values()
            ->filter(fn (string $permission): bool => in_array($permission, $grantable, true))
            ->all();
    }

    /**
     * Lista de permisos del catálogo que el usuario actual puede otorgar:
     * únicamente los que él mismo posee. El Super Administrador (bypass
     * vía Gate::before) obtiene todo el catálogo.
     *
     * @return list<string>
     */
    public static function grantablePermissions(): array
    {
        $user = Auth::user();

        if ($user === null) {
            return [];
        }

        return collect(Permissions::all())
            ->filter(fn (string $permission): bool => (bool) $user->can($permission))
            ->values()
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(function ($query) {
            $query->where('guard_name', 'web')
                  ->withCount(['users', 'permissions']);

            // Si el usuario actual NO es super administrador, ocultamos ese rol de la tabla
            if (! Auth::user()?->hasRole(Permissions::ROLE_SUPER)) {
                $query->where('name', '!=', Permissions::ROLE_SUPER);
            }
        })->columns([
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
                ActionGroup::make([
                    static::editRoleAction()->button()->extraAttributes(['title' => 'Editar']),
                    static::deleteRoleAction()->button()->extraAttributes(['title' => 'Eliminar']),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                static::editRoleAction()->hiddenLabel()->extraAttributes(['title' => 'Editar', 'class' => 'hidden md:inline-flex']),
                static::deleteRoleAction()->hiddenLabel()->extraAttributes(['title' => 'Eliminar', 'class' => 'hidden md:inline-flex']),
            ]);
    }

    /**
     * Editar rol: se instancia dos veces (copia en el menú de tres puntitos
     * para mobile y botón directo para desktop), así que el cuerpo vive acá
     * y cada copia agrega sus atributos de visibilidad.
     */
    private static function editRoleAction(): EditAction
    {
        return EditAction::make()
            ->button()
            ->fillForm(fn (Role $record): array => [
                // fillForm REEMPLAZA el fill por defecto del EditAction
                // (que era attributesToArray), así que hay que incluir
                // también los atributos del rol, no sólo los permisos
                // agrupados por módulo.
                'name' => $record->name,
                'guard_name' => $record->guard_name,
                // Sólo se precargan los permisos dentro del alcance del
                // editor: los que no puede ver no se muestran (y rompería
                // la validación del checkbox list) pero se preservan igual
                // al guardar, tomados del registro y no del formulario.
                'permissions' => $record->permissions->pluck('name')
                    ->filter(fn (string $name): bool => in_array(
                        $name,
                        static::grantablePermissions(),
                        true,
                    ))
                    ->groupBy(fn (string $name) => str_contains($name, '.')
                        ? explode('.', $name)[0]
                        : 'otros')
                    ->all(),
            ])
            ->using(function (Role $record, array $data): void {
                $record->update(collect($data)->except('permissions')->all());

                // Sólo se sincroniza el alcance del editor: los permisos
                // del rol que el editor NO posee (por ejemplo, si edita
                // rol con más permisos que él) se preservan tal cual — el
                // no puede quitar lo que no puede otorgar.
                $grantable = static::grantablePermissions();
                $preserved = $record->permissions
                    ->pluck('name')
                    ->reject(fn (string $permission): bool => in_array($permission, $grantable, true))
                    ->all();

                $record->syncPermissions(array_values(array_unique([
                    ...$preserved,
                    ...static::flattenPermissions($data['permissions'] ?? []),
                ])));
            });
    }

    /** Eliminar rol (misma lógica de las dos copias, con política de borrado). */
    private static function deleteRoleAction(): DeleteAction
    {
        return DeleteAction::make()
            ->button()
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
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }
}
