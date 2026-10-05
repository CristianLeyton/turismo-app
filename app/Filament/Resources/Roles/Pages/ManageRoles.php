<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Spatie\Permission\Models\Role;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected static ?string $title = '';

    protected ?string $heading = 'Roles y permisos';

    public static function canAccess(array $parameters = []): bool
    {
        return RoleResource::canViewAny();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->using(function (array $data): Role {
                    $role = Role::create([
                        'name' => $data['name'],
                        'guard_name' => 'web',
                    ]);

                    $role->syncPermissions(RoleResource::flattenPermissions($data['permissions'] ?? []));

                    return $role;
                }),
        ];
    }
}
