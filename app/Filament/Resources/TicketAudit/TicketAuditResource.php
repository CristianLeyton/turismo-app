<?php

namespace App\Filament\Resources\TicketAudit;

use App\Filament\Clusters\Tickets\TicketsCluster;
use App\Filament\Resources\TicketAudit\Classes\TicketAuditInfolist;
use App\Filament\Resources\TicketAudit\Classes\TicketAuditTable;
use App\Filament\Resources\TicketAudit\Pages\ListTicketAudit;
use App\Filament\Resources\TicketAudit\Pages\ViewTicketAudit;
use App\Models\Ticket;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

/**
 * Recurso de auditoría de boletos: muestra los boletos ELIMINADOS y los
 * EDITADOS/REPROGRAMADOS (con su historial de cambios de fecha).
 *
 * Es 100% de solo lectura: no permite crear, editar, eliminar ni restaurar.
 * Solo visible para administradores (is_admin).
 */
class TicketAuditResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $cluster = TicketsCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::ListBullet;

    protected static ?string $modelLabel = 'boleto';
    protected static ?string $pluralModelLabel = 'Boletos';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    /**
     * Slug propio: sin esto colisionaría con el recurso "Boletos"
     * (ambos usan el modelo Ticket y derivarían el slug 'tickets').
     */
    protected static ?string $slug = 'ticket-history';

    public static function getNavigationLabel(): string
    {
        return 'Historial';
    }

    public static function getRecordTitle($record): string
    {
        return "Boleto N°{$record->id}";
    }

    public static function canAccess(): bool
    {
        return (bool) (Auth::user()?->is_admin ?? false);
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    /**
     * El ítem de navegación solo aparece para administradores.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /**
     * Recurso de solo lectura: ninguna operación de escritura está permitida.
     * Los boletos eliminados NO pueden restaurarse ni editarse desde aquí.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete($record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore($record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return TicketAuditInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketAuditTable::configure($table);
    }

    /**
     * Solo boletos eliminados o reprogramados alguna vez.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->where(function (Builder $query) {
                $query
                    ->whereNotNull('tickets.deleted_at')
                    ->orWhereHas('dateChanges');
            });
    }

    /**
     * Permite bindear (y ver) boletos eliminados por la ruta /{record}.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketAudit::route('/'),
            'view' => ViewTicketAudit::route('/{record}'),
        ];
    }
}
