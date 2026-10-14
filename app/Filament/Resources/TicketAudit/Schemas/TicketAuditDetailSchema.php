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
     * Estado del boleto para la tabla: combinación de Eliminado /
     * Reprogramado / Editado (por ejemplo "Reprogramado + Editado").
     */
    public static function estado(Model $record): string
    {
        $partes = [];

        if ($record->trashed()) {
            $partes[] = 'Eliminado';
        }

        if ($record->dateChanges->isNotEmpty()) {
            $partes[] = 'Reprogramado';
        }

        if ($record->edits->isNotEmpty()) {
            $partes[] = 'Editado';
        }

        return $partes === [] ? '—' : implode(' + ', $partes);
    }
}
