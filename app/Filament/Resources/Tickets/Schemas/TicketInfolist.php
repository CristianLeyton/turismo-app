<?php

namespace App\Filament\Resources\Tickets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\Section as Section;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists\Components\ViewEntry;

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

                // Información del Viaje
                ComponentsSection::make('Detalles de venta')
                    ->columnSpanFull()
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('sale.id')
                            ->label('N° Venta')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('sale.sale_date')
                            ->label('Fecha de venta')
                            ->date('d/m/Y H:i')
                            ->badge()
                            ->color('info'),
                        
                        TextEntry::make('sale.user.name')
                            ->label('Vendedor')
                            ->badge()
                            ->color('warning'),
                    ]),
                
            ]);
    }
}