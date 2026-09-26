<?php

namespace App\Filament\Resources\TicketAudit\Pages;

use App\Filament\Resources\TicketAudit\TicketAuditResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListTicketAudit extends ListRecords
{
    protected static string $resource = TicketAuditResource::class;

    protected static ?string $title = 'Historial';
    protected ?string $heading = 'Historial de boletos';

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) (Auth::user()?->is_admin ?? false);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Recurso de solo lectura: sin acciones.
        ];
    }
}
