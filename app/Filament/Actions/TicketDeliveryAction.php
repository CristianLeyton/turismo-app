<?php

namespace App\Filament\Actions;

use App\Models\Ticket;
use App\Support\TicketDelivery;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Modal de entrega de boletos por link, al cerrar una venta (CreateTicket)
 * o una reprogramación (ViewTicket, tras RescheduleTicket). La página lo
 * abre como defaultAction con los argumentos que dejó TicketDelivery en la
 * sesión flash: {kind, sale_id, ticket_ids}.
 */
final class TicketDeliveryAction
{
    public const NAME = 'entregaBoletos';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->modalHeading(fn (array $arguments): string => self::heading($arguments))
            ->modalDescription(fn (array $arguments): ?string => self::description($arguments))
            ->modalIcon('heroicon-o-check-circle')
            ->modalIconColor('success')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalContent(fn (array $arguments) => view(
                'filament.tickets.delivery-modal',
                TicketDelivery::payload(self::tickets($arguments)),
            ))
            // Se cierra con la X o los botones del pie, nunca con un click
            // sin querer afuera ni con Escape: perdería los links de la venta.
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalSubmitAction(false)
            ->modalCancelAction(fn (Action $action, array $arguments): Action => self::isSale($arguments)
                ? $action->label('Nueva venta')->color('primary')
                : $action->label('Cerrar'))
            ->extraModalFooterActions(fn (array $arguments): array => self::isSale($arguments)
                ? [
                    Action::make('irAlInicio')
                        ->label('Ir al inicio')
                        ->color('gray')
                        ->url(url('/admin')),
                ]
                : []);
    }

    /**
     * Boletos del pedido que el usuario puede ver, o que vendió él mismo
     * (un rol puede vender sin ver boletos). Los argumentos viajan por el
     * navegador: un id ajeno o de otra venta se descarta.
     *
     * @param  array<string, mixed>  $arguments
     * @return Collection<int, Ticket>
     */
    public static function tickets(array $arguments): Collection
    {
        $ids = array_map('intval', (array) ($arguments['ticket_ids'] ?? []));

        return Ticket::query()
            ->whereIn('id', $ids)
            ->when(isset($arguments['sale_id']), fn ($query) => $query->where('sale_id', (int) $arguments['sale_id']))
            ->with('sale')
            ->orderBy('id')
            ->get()
            ->filter(fn (Ticket $ticket): bool => (bool) Auth::user()?->can('view', $ticket)
                || ($ticket->sale?->user_id !== null && (int) $ticket->sale->user_id === (int) Auth::id()))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function isSale(array $arguments): bool
    {
        return ($arguments['kind'] ?? null) !== TicketDelivery::KIND_RESCHEDULE;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function heading(array $arguments): string
    {
        if (! self::isSale($arguments)) {
            $ticketId = (int) (($arguments['ticket_ids'] ?? [])[0] ?? 0);

            return "Boleto N°{$ticketId} reprogramado correctamente";
        }

        return 'Venta N°'.(int) ($arguments['sale_id'] ?? 0).' realizada correctamente';
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function description(array $arguments): ?string
    {
        if (! self::isSale($arguments)) {
            return null;
        }

        $count = count((array) ($arguments['ticket_ids'] ?? []));

        return $count === 1 ? 'Se vendió 1 boleto.' : "Se vendieron {$count} boletos.";
    }
}
