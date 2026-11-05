<?php

namespace App\Support;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\Trip;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Aviso de administración ante una persona que ya tiene boleto en el viaje.
 * Lo usan la venta (modal del paso "Pasajeros" y notificación al emitir),
 * la reprogramación y la edición de boletos. Cada conflicto se muestra como
 * "quién, qué boleto y qué viaje", con "Ver boleto" en otra pestaña y una
 * acción para volver que solo cierra el aviso (el formulario queda intacto).
 */
final class DuplicateTicketNotification
{
    public const TITLE = 'Este pasajero ya tiene boleto en este viaje';

    public const HINT = 'Una persona no puede tener dos boletos en el mismo viaje. Corregí el DNI o elegí otro viaje.';

    /**
     * @param  Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>  $conflicts
     */
    public static function send(Collection $conflicts, string $backLabel): void
    {
        self::make($conflicts, $backLabel)->send();
    }

    /**
     * @param  Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>  $conflicts
     */
    public static function make(Collection $conflicts, string $backLabel): Notification
    {
        return Notification::make()
            ->title(self::title($conflicts))
            ->body(self::body($conflicts))
            ->icon('heroicon-m-identification')
            ->danger()
            ->persistent()
            ->actions(self::actionsFor($conflicts, $backLabel));
    }

    /**
     * "Este pasajero ya tiene..." o "2 pasajeros ya tienen..." según cuántas
     * personas (DNI distintos) están en conflicto.
     *
     * @param  Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>  $conflicts
     */
    public static function title(Collection $conflicts): string
    {
        $people = $conflicts->pluck('dni')->unique()->count();

        return $people > 1
            ? "{$people} pasajeros ya tienen boleto en este viaje"
            : self::TITLE;
    }

    /**
     * Una línea por persona: "Ana Pérez (DNI 30123456) · boleto N°812 ·
     * Tartagal → Salta · 04/11/2026 08:00".
     *
     * @param  Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>  $conflicts
     */
    public static function body(Collection $conflicts): string
    {
        return collect(self::rows($conflicts))
            ->map(fn (array $row): string => e(implode(' · ', array_filter([
                trim("{$row['name']} (DNI {$row['dni']})"),
                "boleto N°{$row['ticket_id']}",
                $row['route'],
                trim("{$row['date']} {$row['time']}"),
            ]))))
            ->implode('<br>');
    }

    /**
     * Datos de cada conflicto listos para mostrar.
     *
     * @param  Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>  $conflicts
     * @return list<array{dni: string, name: string, is_child: bool, ticket_id: int, url: string, route: string, date: string, time: string, seat: ?string, bus: ?string}>
     */
    public static function rows(Collection $conflicts): array
    {
        return $conflicts
            ->map(function (array $conflict): array {
                $ticket = $conflict['ticket'];
                $trip = $conflict['trip'];
                $passenger = $ticket->passenger;
                $isChild = $passenger && trim((string) $passenger->dni) !== $conflict['dni'];
                $person = $isChild
                    ? $passenger->children->first(fn ($child): bool => trim((string) $child->dni) === $conflict['dni'])
                    : $passenger;

                return [
                    'dni' => $conflict['dni'],
                    'name' => trim(($person?->first_name ?? '').' '.($person?->last_name ?? '')),
                    'is_child' => $isChild,
                    'ticket_id' => (int) $ticket->id,
                    'url' => TicketResource::getUrl('view', ['record' => $ticket->id]),
                    'route' => trim(($ticket->origin?->name ?? '').' → '.($ticket->destination?->name ?? ''), ' →'),
                    'date' => $trip?->trip_date?->format('d/m/Y') ?? '',
                    'time' => $trip?->schedule?->departure_time?->format('H:i') ?? '',
                    'seat' => $ticket->seat?->seat_number !== null ? (string) $ticket->seat->seat_number : null,
                    'bus' => $trip?->bus?->name,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * "Ver boleto N°X" por boleto en conflicto: abre su vista en otra pestaña.
     *
     * @param  Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>  $conflicts
     * @return array<Action>
     */
    public static function viewTicketActions(Collection $conflicts): array
    {
        return $conflicts
            ->unique(fn (array $conflict): int => $conflict['ticket']->id)
            ->map(fn (array $conflict): Action => Action::make('ver_boleto_'.$conflict['ticket']->id)
                ->label("Ver boleto N°{$conflict['ticket']->id}")
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->button()
                ->url(TicketResource::getUrl('view', ['record' => $conflict['ticket']->id]), shouldOpenInNewTab: true))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>  $conflicts
     * @return array<Action>
     */
    public static function actionsFor(Collection $conflicts, string $backLabel): array
    {
        $actions = self::viewTicketActions($conflicts);

        $actions[] = Action::make('volver')
            ->label($backLabel)
            ->color('gray')
            ->button()
            ->close();

        return $actions;
    }
}
