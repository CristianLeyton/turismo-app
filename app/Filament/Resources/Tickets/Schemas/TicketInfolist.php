<?php

namespace App\Filament\Resources\Tickets\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Schema;

class TicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // Resumen General con estilo similar al del formulario
                ViewEntry::make('summary_header')
                    ->label('')
                    ->view('tickets.infolist-summary-header')
                    ->columnSpanFull(),

                // Información del Viaje: tarjeta de estilo consistente con el
                // resumen general (ViewEntry blade, no badges sueltos).
                ViewEntry::make('sale_details')
                    ->label('')
                    ->view('tickets.infolist-sale-details')
                    ->columnSpanFull(),

                // Historial de ediciones (correcciones de datos, tickets.edit).
                // Una entrada por edición con autor, fecha, motivo y el detalle
                // Antes/Después de cada campo: el mismo render que el detalle
                // del Historial general (tickets/partials/edit-history).
                ComponentsSection::make('Historial de ediciones')
                    ->description(fn ($record): string => \App\Support\TicketEditHistory::summary($record?->edits))
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => $record?->edits()->exists())
                    ->schema([
                        ViewEntry::make('edit_history')
                            ->label('')
                            ->view('tickets.partials.edit-history')
                            ->columnSpanFull(),
                    ]),

            ]);
    }
}
