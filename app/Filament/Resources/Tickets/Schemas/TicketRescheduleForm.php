<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Models\Schedule;
use App\Models\Seat;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Component as LivewireComponent;

class TicketRescheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Boleto actual')
                    ->description(fn (LivewireComponent $livewire) => self::ticket($livewire)
                        ? "Boleto N°" . self::ticket($livewire)->id . " — Venta N°" . self::ticket($livewire)->sale_id
                        : '')
                    ->schema([
                        ViewField::make('ticket_summary')
                            ->label('')
                            ->view('tickets.reschedule-ticket-summary')
                            ->viewData(fn (LivewireComponent $livewire) => [
                                'ticket' => self::ticket($livewire),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Radio::make('scope')
                    ->label('¿Qué tramo querés reprogramar?')
                    ->options([
                        'outbound' => 'Solo este tramo',
                        'both' => 'Ida y vuelta',
                    ])
                    ->default('outbound')
                    ->live()
                    ->visible(fn (LivewireComponent $livewire) => self::isOutboundOfRoundTrip(self::ticket($livewire)))
                    ->dehydrated(),

                // ================= Ida =================
                Grid::make()
                    ->schema([
                        DatePicker::make('date')
                            ->label('Nueva fecha de ida')
                            ->required()
                            ->minDate(fn () => Carbon::today())
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => self::resetOutboundSearch($set))
                            ->validationMessages([
                                'required' => 'Seleccione una fecha de ida',
                            ]),

                        Select::make('schedule_id')
                            ->label('Horario de ida')
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get) => blank($get('date')))
                            ->placeholder(fn (Get $get) => blank($get('date'))
                                ? 'Seleccione una fecha primero'
                                : 'Seleccione un horario')
                            ->options(function (Get $get, LivewireComponent $livewire) {
                                $ticket = self::ticket($livewire);

                                if (blank($get('date')) || ! $ticket) {
                                    return [];
                                }

                                return self::scheduleOptions(
                                    (int) $ticket->origin_location_id,
                                    (int) $ticket->destination_location_id,
                                );
                            })
                            ->afterStateUpdated(function (Set $set, Get $get, LivewireComponent $livewire, $state) {
                                self::resetOutboundSearch($set);

                                $ticket = self::ticket($livewire);

                                if (blank($state) || blank($get('date')) || ! $ticket) {
                                    return;
                                }

                                $trip = self::resolveTrip($state, $get('date'), $ticket->origin_location_id, $ticket->destination_location_id);

                                if (! $trip) {
                                    return;
                                }

                                $set('trip_id', $trip->id);

                                // Autoseleccionar el asiento con mismo número si existe y está libre.
                                $sameNumberSeat = self::findSameNumberSeat($ticket, $trip);
                                if ($sameNumberSeat) {
                                    $set('seat_ids', [$sameNumberSeat->id]);
                                    $set('seat_autoselected', $sameNumberSeat->seat_number);
                                } else {
                                    $set('seat_autoselected', null);
                                }
                            })
                            ->helperText(function (Get $get) {
                                if (blank($get('trip_id'))) {
                                    return null;
                                }

                                $trip = Trip::find($get('trip_id'));
                                if (! $trip) {
                                    return null;
                                }

                                $available = $trip->remainingSeats();

                                if ($available < 1) {
                                    return 'Atención: el viaje no tiene asientos disponibles.';
                                }

                                return "Viaje disponible ({$trip->bus?->name}). Asientos disponibles: {$available}";
                            })
                            ->validationMessages([
                                'required' => 'Seleccione un horario',
                            ]),

                        Hidden::make('trip_id')
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('seat_ids', [])),

                        // Asiento elegido en el selector (entangle desde el ViewField).
                        // Declarado para que el estado llegue a getState() y se valide.
                        Hidden::make('seat_ids')
                            ->live()
                            ->dehydrated()
                            ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                if (filled($get('trip_id')) && (is_array($value) ? count($value) : 0) !== 1) {
                                    $fail('Seleccioná exactamente 1 asiento de ida antes de confirmar.');
                                }
                            }),

                        ViewField::make('seat_selector')
                            ->label('Nuevo asiento de ida')
                            ->columnSpanFull()
                            ->view('tickets.seat-selector')
                            ->visible(fn (Get $get) => filled($get('trip_id')) && Trip::find($get('trip_id')) !== null)
                            ->viewData(function (Get $get, LivewireComponent $livewire) {
                                $tripId = $get('trip_id');
                                $trip = $tripId ? Trip::find($tripId) : null;
                                $sessionId = session()->getId();

                                if ($trip && filled($get('seat_ids'))) {
                                    self::ensureReservationForSelectedSeats($trip, (array) $get('seat_ids'), $sessionId);
                                }

                                return [
                                    'trip_id' => $trip?->id,
                                    'trip' => $trip,
                                    'passengers_count' => 1,
                                    'fieldId' => 'seat_ids',
                                    'session_id' => $sessionId,
                                    'enable_reservation' => true,
                                    'reservation_timeout' => 5,
                                ];
                            }),
                    ])
                    ->columnSpanFull()
                    ->columns(2),

                // ================= Vuelta =================
                Grid::make()
                    ->schema([
                        DatePicker::make('return_date')
                            ->label('Nueva fecha de vuelta')
                            ->required()
                            ->minDate(fn () => Carbon::today())
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => self::resetReturnSearch($set))
                            ->validationMessages([
                                'required' => 'Seleccione una fecha de vuelta',
                            ]),

                        Select::make('return_schedule_id')
                            ->label('Horario de vuelta')
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get) => blank($get('return_date')))
                            ->placeholder(fn (Get $get) => blank($get('return_date'))
                                ? 'Seleccione una fecha primero'
                                : 'Seleccione un horario')
                            ->options(function (Get $get, LivewireComponent $livewire) {
                                $ticket = self::ticket($livewire);

                                if (blank($get('return_date')) || ! $ticket) {
                                    return [];
                                }

                                return self::scheduleOptions(
                                    (int) $ticket->destination_location_id,
                                    (int) $ticket->origin_location_id,
                                );
                            })
                            ->afterStateUpdated(function (Set $set, Get $get, LivewireComponent $livewire, $state) {
                                self::resetReturnSearch($set);

                                $ticket = self::ticket($livewire);

                                if (blank($state) || blank($get('return_date')) || ! $ticket) {
                                    return;
                                }

                                $trip = self::resolveTrip($state, $get('return_date'), $ticket->destination_location_id, $ticket->origin_location_id);

                                if (! $trip) {
                                    return;
                                }

                                $set('return_trip_id', $trip->id);

                                $sameNumberSeat = self::findSameNumberReturnSeat($ticket, $trip);
                                if ($sameNumberSeat) {
                                    $set('return_seat_ids', [$sameNumberSeat->id]);
                                    $set('return_seat_autoselected', $sameNumberSeat->seat_number);
                                } else {
                                    $set('return_seat_autoselected', null);
                                }
                            })
                            ->helperText(function (Get $get) {
                                if (blank($get('return_trip_id'))) {
                                    return null;
                                }

                                $trip = Trip::find($get('return_trip_id'));
                                if (! $trip) {
                                    return null;
                                }

                                $available = $trip->remainingSeats();

                                if ($available < 1) {
                                    return 'Atención: el viaje no tiene asientos disponibles.';
                                }

                                return "Viaje disponible ({$trip->bus?->name}). Asientos disponibles: {$available}";
                            })
                            ->validationMessages([
                                'required' => 'Seleccione un horario de vuelta',
                            ]),

                        Hidden::make('return_trip_id')
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('return_seat_ids', [])),

                        Hidden::make('return_seat_ids')
                            ->live()
                            ->dehydrated()
                            ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                if (filled($get('return_trip_id')) && (is_array($value) ? count($value) : 0) !== 1) {
                                    $fail('Seleccioná exactamente 1 asiento de vuelta antes de confirmar.');
                                }
                            }),

                        ViewField::make('return_seat_selector')
                            ->label('Nuevo asiento de vuelta')
                            ->columnSpanFull()
                            ->view('tickets.seat-selector')
                            ->visible(fn (Get $get) => filled($get('return_trip_id')) && Trip::find($get('return_trip_id')) !== null)
                            ->viewData(function (Get $get, LivewireComponent $livewire) {
                                $tripId = $get('return_trip_id');
                                $trip = $tripId ? Trip::find($tripId) : null;
                                $sessionId = session()->getId();

                                if ($trip && filled($get('return_seat_ids'))) {
                                    self::ensureReservationForSelectedSeats($trip, (array) $get('return_seat_ids'), $sessionId);
                                }

                                return [
                                    'trip_id' => $trip?->id,
                                    'trip' => $trip,
                                    'passengers_count' => 1,
                                    'fieldId' => 'return_seat_ids',
                                    'session_id' => $sessionId,
                                    'enable_reservation' => true,
                                    'reservation_timeout' => 5,
                                ];
                            }),
                    ])
                    ->columnSpanFull()
                    ->columns(2)
                    ->visible(fn (LivewireComponent $livewire, Get $get) => $get('scope') === 'both'
                        && self::isOutboundOfRoundTrip(self::ticket($livewire))),

                // Confirmación de contraseña (opt-in vía Configuración).
                // No es dato del boleto: dehydrated(false) y se lee directo
                // del estado en RescheduleTicket::confirmReschedule().
                TextInput::make('confirm_password')
                    ->label('Tu contraseña')
                    ->password()
                    ->revealable()
                    ->dehydrated(false)
                    ->required(fn (): bool => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE))
                    ->visible(fn (): bool => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE))
                    ->helperText('Confirmá tu clave de administrador para ejecutar la reprogramación.'),
            ]);
    }

    /**
     * Ticket a reprogramar, resuelto desde la página Livewire.
     */
    private static function ticket(LivewireComponent $livewire): ?Ticket
    {
        return method_exists($livewire, 'targetTicket') ? $livewire->targetTicket() : null;
    }

    private static function isOutboundOfRoundTrip(?Ticket $ticket): bool
    {
        return $ticket !== null
            && (bool) $ticket->is_round_trip
            && ! is_null($ticket->return_trip_id);
    }

    private static function resetOutboundSearch(Set $set): void
    {
        $set('trip_id', null);
        $set('seat_ids', []);
        $set('seat_autoselected', null);
    }

    private static function resetReturnSearch(Set $set): void
    {
        $set('return_trip_id', null);
        $set('return_seat_ids', []);
        $set('return_seat_autoselected', null);
    }

    /**
     * Resolver el viaje destino con findOrCreateForBooking (o null si no se puede).
     */
    private static function resolveTrip(mixed $scheduleId, mixed $date, int $originId, int $destinationId): ?Trip
    {
        $result = Trip::findOrCreateForBooking(
            (int) $scheduleId,
            self::formatDate($date),
            $originId,
            $destinationId,
        );

        return $result['trip'] ?? null;
    }

    /**
     * Horarios bookable() que cubren el segmento, sin forzar el bus original.
     */
    private static function scheduleOptions(int $originId, int $destinationId): array
    {
        return Schedule::query()
            ->bookable()
            ->whereHas('route.stops', fn ($q) => $q->where('location_id', $originId))
            ->whereHas('route.stops', fn ($q) => $q->where('location_id', $destinationId))
            ->with('route.stops')
            ->get()
            ->filter(fn (Schedule $schedule) => $schedule->route->isValidSegment($originId, $destinationId))
            ->sortBy(fn (Schedule $schedule) => $schedule->display_name, SORT_NATURAL | SORT_FLAG_CASE)
            ->mapWithKeys(fn (Schedule $schedule) => [$schedule->id => $schedule->display_name])
            ->toArray();
    }

    /**
     * Asiento con el MISMO número en el bus del viaje destino, si existe y está libre.
     */
    private static function findSameNumberSeat(Ticket $ticket, Trip $newTrip): ?Seat
    {
        $currentSeatNumber = $ticket->seat?->seat_number;

        if (blank($currentSeatNumber)) {
            return null;
        }

        $occupiedIds = $newTrip->activeSeatedTickets()->pluck('seat_id');

        return Seat::query()
            ->where('bus_id', $newTrip->bus_id)
            ->where('is_active', true)
            ->where('seat_number', $currentSeatNumber)
            ->whereNotIn('id', $occupiedIds)
            ->first();
    }

    /**
     * Igual que findSameNumberSeat, pero para el tramo de vuelta del pasajero.
     */
    private static function findSameNumberReturnSeat(Ticket $outboundTicket, Trip $newReturnTrip): ?Seat
    {
        $returnTicket = Ticket::query()
            ->where('sale_id', $outboundTicket->sale_id)
            ->where('passenger_id', $outboundTicket->passenger_id)
            ->where('is_round_trip', true)
            ->whereNull('return_trip_id')
            ->first();

        if (! $returnTicket) {
            return null;
        }

        $currentSeatNumber = $returnTicket->seat?->seat_number;

        if (blank($currentSeatNumber)) {
            return null;
        }

        $occupiedIds = $newReturnTrip->activeSeatedTickets()->pluck('seat_id');

        return Seat::query()
            ->where('bus_id', $newReturnTrip->bus_id)
            ->where('is_active', true)
            ->where('seat_number', $currentSeatNumber)
            ->whereNotIn('id', $occupiedIds)
            ->first();
    }

    /**
     * Crear/refrescar la reserva temporal de los asientos seleccionados para esta sesión.
     * (El selector JS también reserva; esto cubre la autoselección server-side.)
     */
    private static function ensureReservationForSelectedSeats(Trip $trip, array $seatIds, string $sessionId): void
    {
        $validIds = Seat::query()
            ->where('bus_id', $trip->bus_id)
            ->where('is_active', true)
            ->whereIn('id', $seatIds)
            ->pluck('id')
            ->all();

        if (empty($validIds)) {
            return;
        }

        $alreadyReserved = SeatReservation::query()
            ->where('trip_id', $trip->id)
            ->whereIn('seat_id', $validIds)
            ->where('user_session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->pluck('seat_id')
            ->all();

        $missing = array_diff($validIds, $alreadyReserved);

        if (empty($missing)) {
            SeatReservation::query()
                ->where('user_session_id', $sessionId)
                ->where('trip_id', $trip->id)
                ->whereIn('seat_id', $validIds)
                ->update(['expires_at' => now()->addMinutes(5)]);

            return;
        }

        SeatReservation::reserveSeats($trip->id, $missing, $sessionId, 5);
    }

    private static function formatDate(mixed $date): string
    {
        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }

        return Carbon::parse($date)->format('Y-m-d');
    }
}
