<?php

namespace App\Filament\Resources\TicketAudit\Classes;

use App\Filament\Resources\TicketAudit\Schemas\TicketAuditDetailSchema;
use Filament\Schemas\Schema;

/**
 * Infolist de la página de vista: reutiliza el mismo detalle completo que
 * el modal "Ver" de la tabla (TicketAuditDetailSchema).
 */
class TicketAuditInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(TicketAuditDetailSchema::make());
    }
}
