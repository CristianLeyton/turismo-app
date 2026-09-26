<?php

namespace App\Filament\Resources\TicketAudit\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Detalle de auditoría del boleto, compartido por el modal "Ver" de la
 * tabla y la página de vista del recurso.
 *
 * El contenido vive en la vista Blade
 * filament/resources/ticket-audit/ticket-audit-detail, con el mismo estilo
 * visual que los demás modales de detalle del panel (trip-details,
 * infolist-summary-header). El record se resuelve dentro de la vista vía
 * $getRecord() (igual que tickets/infolist-summary-header.blade.php).
 */
class TicketAuditDetailSchema
{
    public static function make(): array
    {
        return [
            ViewEntry::make('audit_detail')
                ->label('')
                ->view('filament.resources.ticket-audit.ticket-audit-detail')
                ->columnSpanFull(),
        ];
    }

    /**
     * Estado del boleto para la tabla: Eliminado, Reprogramado o ambos.
     */
    public static function estado(Model $record): string
    {
        $deleted = $record->trashed();
        $rescheduled = $record->dateChanges->isNotEmpty();

        if ($deleted && $rescheduled) {
            return 'Eliminado + Reprogramado';
        }

        if ($deleted) {
            return 'Eliminado';
        }

        return 'Reprogramado';
    }
}
