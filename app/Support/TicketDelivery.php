<?php

namespace App\Support;

use App\Models\Sale;
use App\Models\Setting;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Entrega de boletos por link (parámetro DESCARGA_AUTOMATICA_PDF desactivado):
 * qué boletos se entregan al cerrar una venta o una reprogramación y los
 * datos del modal de entrega (TicketDeliveryAction).
 *
 * La venta y la reprogramación terminan con una redirección (Filament
 * redirige siempre después de crear), así que el pedido de abrir el modal
 * viaja en la sesión flash: la página de destino lo lee al montar y abre el
 * modal una sola vez; al recargar ya no está.
 */
final class TicketDelivery
{
    public const SESSION_KEY = 'ticket_delivery';

    public const KIND_SALE = 'sale';

    public const KIND_RESCHEDULE = 'reschedule';

    /**
     * Boletos que se entregan al cerrar una venta: uno por pasajero, el de
     * ida (su PDF incluye la vuelta si es diferido).
     *
     * @return Collection<int, Ticket>
     */
    public static function ticketsForSale(Sale $sale): Collection
    {
        return $sale->tickets()
            ->orderBy('id')
            ->get()
            ->groupBy('passenger_id')
            ->map(fn (Collection $tickets): Ticket => $tickets->first(fn (Ticket $ticket): bool => ! $ticket->isReturnLeg()) ?? $tickets->first())
            ->sortBy('id')
            ->values();
    }

    /**
     * Pide abrir el modal de entrega de una venta en la próxima página.
     * Genera los tokens acá para que el modal no escriba nada al abrirse.
     */
    public static function flashForSale(Sale $sale): void
    {
        $tickets = self::ticketsForSale($sale);

        $tickets->each(fn (Ticket $ticket): string => $ticket->shareToken());

        session()->flash(self::SESSION_KEY, [
            'kind' => self::KIND_SALE,
            'sale_id' => $sale->id,
            'ticket_ids' => $tickets->modelKeys(),
        ]);
    }

    /**
     * Pide abrir el modal de entrega de un boleto reprogramado.
     */
    public static function flashForReschedule(Ticket $ticket): void
    {
        $ticket->shareToken();

        session()->flash(self::SESSION_KEY, [
            'kind' => self::KIND_RESCHEDULE,
            'sale_id' => $ticket->sale_id,
            'ticket_ids' => [$ticket->id],
        ]);
    }

    /**
     * Pedido pendiente de la sesión, o null. Lo consume quien monta la página.
     *
     * @return array{kind: string, sale_id: int|null, ticket_ids: list<int>}|null
     */
    public static function pending(): ?array
    {
        $pending = session(self::SESSION_KEY);

        if (! is_array($pending) || empty($pending['ticket_ids'])) {
            return null;
        }

        return [
            'kind' => $pending['kind'] === self::KIND_RESCHEDULE ? self::KIND_RESCHEDULE : self::KIND_SALE,
            'sale_id' => isset($pending['sale_id']) ? (int) $pending['sale_id'] : null,
            'ticket_ids' => array_values(array_map('intval', (array) $pending['ticket_ids'])),
        ];
    }

    /**
     * Datos del modal para los boletos dados (todos de la misma venta).
     *
     * @param  Collection<int, Ticket>  $tickets
     * @return array{
     *     rows: list<array{id: int, name: string, title: string, route: string|null, link: string, download: string, defaultPhone: string|null}>,
     *     phones: list<array{phone: string, label: string}>,
     *     template: string,
     * }
     */
    public static function payload(Collection $tickets): array
    {
        $tickets = (new EloquentCollection($tickets->all()))->loadMissing(['passenger', 'origin', 'destination', 'sale']);

        $sale = $tickets->first()?->sale;
        $candidates = $sale ? WhatsAppShare::candidatesForSale($sale) : [];
        $candidatesByPassenger = $sale ? WhatsAppShare::candidatesForSale($sale, unique: false) : [];

        $rows = $tickets->map(function (Ticket $ticket) use ($candidates, $candidatesByPassenger): array {
            $name = $ticket->passenger?->full_name ?? '';

            return [
                'id' => $ticket->id,
                'name' => $name,
                'title' => "Boleto N°{$ticket->id}".($name !== '' ? " — {$name}" : ''),
                'route' => $ticket->origin && $ticket->destination
                    ? "{$ticket->origin->name} → {$ticket->destination->name}"
                    : null,
                'link' => $ticket->shareUrl(),
                'download' => $ticket->sharePdfUrl('attachment'),
                'defaultPhone' => self::defaultPhoneFor($ticket, $candidatesByPassenger, $candidates),
            ];
        })->values()->all();

        $phones = array_map(fn (array $candidate): array => [
            'phone' => $candidate['phone'],
            'label' => "{$candidate['name']} — {$candidate['phone']}",
        ], $candidates);

        return [
            'rows' => $rows,
            'phones' => $phones,
            'template' => Setting::whatsappMensaje(),
        ];
    }

    /**
     * Teléfono propio de un boleto, normalizado: el de su pasajero y, si no
     * tiene, el del padrón por su DNI. Null si no tiene ninguno. Lo usa el
     * "Enviar por WhatsApp" del detalle del boleto.
     */
    public static function phoneForTicket(Ticket $ticket): ?string
    {
        if (! $ticket->sale) {
            return null;
        }

        return self::ownPhone($ticket, WhatsAppShare::candidatesForSale($ticket->sale, unique: false));
    }

    /**
     * Teléfono preseleccionado de un boleto en el modal: el propio y, si no
     * tiene, el primero de la venta.
     *
     * @param  list<array{passenger_id: int, name: string, phone: string, source: string}>  $candidatesByPassenger  Con repeticiones.
     * @param  list<array{passenger_id: int, name: string, phone: string, source: string}>  $candidates  Sin repeticiones.
     */
    private static function defaultPhoneFor(Ticket $ticket, array $candidatesByPassenger, array $candidates): ?string
    {
        return self::ownPhone($ticket, $candidatesByPassenger) ?? $candidates[0]['phone'] ?? null;
    }

    /**
     * @param  list<array{passenger_id: int, name: string, phone: string, source: string}>  $candidatesByPassenger
     */
    private static function ownPhone(Ticket $ticket, array $candidatesByPassenger): ?string
    {
        $own = array_values(array_filter(
            $candidatesByPassenger,
            fn (array $candidate): bool => (int) $candidate['passenger_id'] === (int) $ticket->passenger_id,
        ));

        usort($own, fn (array $a, array $b): int => ($a['source'] === 'passenger' ? 0 : 1) <=> ($b['source'] === 'passenger' ? 0 : 1));

        return $own[0]['phone'] ?? null;
    }
}
