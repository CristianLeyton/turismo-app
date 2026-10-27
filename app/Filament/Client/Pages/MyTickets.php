<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Schemas\ClientTicketAssignForm;
use App\Models\Ticket;
use App\Models\Trip;
use App\Services\ClientTicketAssignmentService;
use App\Support\ClientSession;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Dashboard del panel de clientes ("/" del panel).
 *
 * Sólo se llega acá identificado (middleware EnsureClientIdentified), y en
 * cada query/action se re-verifica el DNI de la sesión: el panel nunca confía
 * en IDs que vengan por la URL o el form.
 */
class MyTickets extends BaseDashboard implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.client.my-tickets';

    /**
     * Por defecto sólo se ven los boletos SIN fecha (lo único que puede
     * operar el cliente). El switch del panel permite mostrar además los
     * que ya tienen fecha confirmada.
     */
    public bool $mostrarFechados = false;

    public function getTitle(): string
    {
        return 'Mis boletos';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('salir')
                ->label('Salir')
                ->icon('heroicon-m-arrow-left-on-rectangle')
                ->color('gray')
                ->action(function () {
                    // Borra sólo la identidad del cliente; la sesión del panel
                    // de admin es otra cosa y no se toca.
                    ClientSession::logout();

                    $this->redirect(ClientAccess::getUrl(panel: 'clientes'));
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            // Más nuevo primero: el cliente ve arriba su boleto más reciente.
            ->defaultSort('id', 'desc')
            ->query(fn () => ClientTicketAssignmentService::ticketsForCurrentClient()
                ->when(! $this->mostrarFechados, fn (Builder $q) => $q->whereNull('trip_id'))
                ->with(['passenger', 'origin', 'destination', 'trip.schedule', 'trip.bus', 'seat']))
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('passenger.full_name')
                    ->label('Pasajero')
                    // Mobile: sólo N°, tramo y fecha caben en la pantalla.
                    ->visibleFrom('sm')
                    ->sortable(false),

                TextColumn::make('tramo')
                    ->label('Tramo')
                    ->state(fn (Ticket $record) => trim(
                        ($record->origin?->name ?? '?') . ' → ' . ($record->destination?->name ?? '?')
                    )),

                // Identidad clara del tramo: "Ida" para la ida del diferido y
                // "Vuelta de N°X" para la vuelta, con el N° del boleto de ida
                // que lleva el precio. Un boleto sólo de ida (sin diferido)
                // muestra la etiqueta neutra "Solo ida".
                BadgeColumn::make('tipo')
                    ->state(fn (Ticket $record) => match (true) {
                        $record->isReturnLeg() => ($outbound = $record->roundTripOutboundTicket())
                            ? 'Vuelta de N°' . $outbound->id
                            : 'Vuelta',
                        $record->isPartOfRoundTrip() || (bool) $record->is_round_trip => 'Ida',
                        default => 'Solo ida',
                    })
                    ->color(fn (string $state): string => $state === 'Solo ida' ? 'gray' : 'primary')
                    // La identidad del tramo no entra en mobile.
                    ->visibleFrom('sm'),

                BadgeColumn::make('estado')
                    ->state(fn (Ticket $record) => $record->isPendingDate() ? 'Pendiente' : 'Confirmado')
                    ->color(fn (string $state) => $state === 'Pendiente' ? 'warning' : 'success')
                    // Mobile: la fecha "Sin fecha" ya lo dice.
                    ->visibleFrom('sm'),

                TextColumn::make('viaje')
                    ->label('Fecha y horario')
                    ->state(fn (Ticket $record) => $record->isPendingDate()
                        ? null
                        : trim(($record->trip?->trip_date?->format('d/m/Y') ?? '')
                            . ($record->trip?->schedule?->departure_time?->format(' H:i') ?? '')))
                    ->placeholder('Sin fecha')
                    // Ordena por la fecha real del viaje (subquery): la columna
                    // computada "viaje" no existe en la base de datos.
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy(Trip::query()
                            ->select('trip_date')
                            ->whereColumn('trips.id', 'tickets.trip_id'), $direction)),

                TextColumn::make('seat.seat_number')
                    ->label('Asiento')
                    ->placeholder('—')
                    // Mobile: el número de asiento también entra en el PDF.
                    ->visibleFrom('sm'),

                TextColumn::make('price')
                    ->label('Precio')
                    ->state(fn (Ticket $record) => $record->isReturnLeg()
                        ? '—'
                        : '$' . number_format((float) $record->price, 2, ',', '.'))
                    // Mobile: no hay espacio para las tintas del importe.
                    ->visibleFrom('sm'),
            ])
            // Mobile: las acciones viven adentro de los tres puntitos, que
            // es como el resto del panel administrativo agrupa las suyas.
            ->recordActions([
                ActionGroup::make([
                    $this->assignAction(),

                    Action::make('boleto_pdf')
                        ->label('Ver boleto')
                        ->icon('heroicon-m-document-arrow-down')
                        ->color('gray')
                        ->button()
                        ->size('sm')
                        ->url(fn (Ticket $record) => route('clientes.boletos.pdf', ['ticket' => $record->id]))
                        ->openUrlInNewTab(),
                ]),
            ]);
    }

    /** Action "Elegir fecha": form de fecha/horario/asiento + asignación inmediata. */
    private function assignAction(): Action
    {
        return Action::make('asignar_fecha')
            ->label('Elegir fecha')
            ->icon('heroicon-m-calendar-days')
            ->color('primary')
            ->button()
            ->size('sm')
            ->visible(fn (Ticket $record) => ClientTicketAssignmentService::clientOwnsTicket($record)
                && $record->isPendingDate()
                && ! $record->trashed())
            ->form(fn (Ticket $record) => ClientTicketAssignForm::make($record))
            ->action(function (Ticket $record, array $data) {
                if (! ClientTicketAssignmentService::clientOwnsTicket($record)
                    || $record->trashed()
                    || ! $record->isPendingDate()) {
                    return; // Defensa server-side; el servicio re-valida igual.
                }

                // Sin notificación se vería como que "no pasa nada" al confirmar.
                if (blank($data['seat_ids'] ?? null)) {
                    Notification::make()
                        ->title('Falta elegir el asiento')
                        ->body('Elegí tu asiento en el mapa antes de confirmar.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return; // Conservar el modal abierto con el estado del form
                }

                try {
                    app(ClientTicketAssignmentService::class)->assign($record, $data);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('No se pudo asignar la fecha')
                        ->body(implode("\n", Arr::flatten((array) $e->errors())))
                        ->danger()
                        ->persistent()
                        ->send();

                    return; // Conservar el modal abierto con el estado del form
                } catch (Throwable $e) {
                    // Ningún error debe quedar en silencio: el cliente ve el motivo.
                    report($e);

                    Notification::make()
                        ->title('No se pudo asignar la fecha')
                        ->body('Ocurrió un problema: ' . $e->getMessage() . '. Volvé a intentar en unos minutos o consultá en boletería.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $fresh = $record->fresh();

                Notification::make()
                    ->title('¡Boleto asignado!')
                    ->body('Tu boleto N°' . $fresh->id . ': '
                        . ($fresh->trip?->trip_date?->format('d/m/Y') ?? '')
                        . ($fresh->trip?->schedule?->departure_time?->format(' H:i') ?? '')
                        . ($fresh->seat?->seat_number !== null ? ' — asiento ' . $fresh->seat->seat_number : ''))
                    ->success()
                    ->actions([
                        Action::make('descargar')
                            ->label('Ver boleto')
                            ->icon('heroicon-m-document-arrow-down')
                            ->url(route('clientes.boletos.pdf', ['ticket' => $fresh->id]), shouldOpenInNewTab: true)
                            ->button(),
                    ])
                    ->send();

                $this->resetTable();
            });
    }
}
