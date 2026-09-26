<?php

namespace App\Filament\Resources\TicketAudit\Pages;

use App\Filament\Resources\TicketAudit\TicketAuditResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewTicketAudit extends ViewRecord
{
    protected static string $resource = TicketAuditResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) (Auth::user()?->is_admin ?? false);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Recurso de solo lectura: sin EditAction, DeleteAction ni RestoreAction.
        ];
    }
}
