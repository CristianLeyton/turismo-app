<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = '';

    protected ?string $heading = 'Usuarios';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->after(fn (User $record) => $record->syncIsAdminFlag())
                ->modalCancelAction(fn ($action) => $action->label('Cerrar')),
        ];
    }
}
