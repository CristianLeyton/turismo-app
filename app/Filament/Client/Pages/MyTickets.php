<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Schemas\ClientTicketAssignForm;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Services\ClientTicketAssignmentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
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
 * Sólo se llega acá identificado (login del guard client), y en
 * cada query/action se re-verifica el DNI de la sesión: el panel nunca confía
 * en IDs que vengan por la URL o el form.
 */
class MyTickets extends BaseDashboard implements HasTable
{
    use InteractsWithTable;

    public const TAB_PENDING = 'pendientes';

    public const TAB_UPCOMING = 'proximos';

    public const TAB_HISTORY = 'historial';

    /**
     * Pestaña activa. Arranca en "Pendientes" sólo si hay boletos sin fecha:
     * es lo único que el cliente puede operar.
     */
    public ?string $activeTab = null;

    public function mount(): void
    {
        $this->activeTab ??= ClientTicketAssignmentService::pendingTicketsForCurrentClient()->exists()
            ? self::TAB_PENDING
            : self::TAB_UPCOMING;
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
    }

    public function getTitle(): string
    {
        return 'Mis boletos';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.client.pending-notice')
                ->viewData(function (): array {
                    $count = ClientTicketAssignmentService::pendingTicketsForCurrentClient()->count();

                    return [
                        'pendingCount' => $count,
                        // Ya en "Pendientes", "Ver pendientes" no llevaría a ningún lado.
                        'onPendingTab' => $this->activeTab === self::TAB_PENDING,
                        // Con uno solo, el aviso lo nombra y su botón lo abre; con varios, no elige por el cliente.
                        'singlePending' => $count === 1
                            ? ClientTicketAssignmentService::pendingTicketsForCurrentClient()->with(['origin', 'destination'])->first()
                            : null,
                    ];
                }),

            View::make('filament.client.next-trip')
                ->viewData(fn (): array => [
                    'ticket' => ClientTicketAssignmentService::nextTripTicketForCurrentClient(),
                ]),

            Tabs::make('Boletos')
                ->livewireProperty('activeTab')
                ->tabs([
                    self::TAB_PENDING => Tab::make('Pendientes')
                        ->badge(fn (): int => ClientTicketAssignmentService::pendingTicketsForCurrentClient()->count())
                        ->badgeColor('warning'),
                    self::TAB_UPCOMING => Tab::make('Próximos')
                        ->badge(fn (): int => ClientTicketAssignmentService::upcomingTicketsForCurrentClient()->count())
                        ->badgeColor('primary'),
                    self::TAB_HISTORY => Tab::make('Historial')
                        ->badge(fn (): int => ClientTicketAssignmentService::pastTicketsForCurrentClient()->count())
                        ->badgeColor('gray'),
                ]),

            EmbeddedTable::make(),

            View::make('filament.client.help-contact-lines')
                ->viewData(fn (): array => [
                    'contactLines' => Setting::contactLines(),
                ]),
        ]);
    }

    /**
     * Botón "Ver pendientes" del aviso (con dos o más boletos sin fecha): pasa
     * a la pestaña "Pendientes", donde cada boleto tiene su "Elegir fecha".
     * No abre ningún formulario: el cliente elige cuál.
     */
    public function showPendingTickets(): void
    {
        $this->activeTab = self::TAB_PENDING;
        $this->resetPage();
    }

    /**
     * Botón "Elegir fecha" del aviso (con un solo boleto sin fecha): pasa a
     * "Pendientes" (el boleto tiene que estar en la tabla para poder
     * operarlo) y abre "Elegir fecha" de ese boleto.
     */
    public function chooseDateOfOldestPending(): void
    {
        $ticket = ClientTicketAssignmentService::oldestPendingTicketForCurrentClient();

        if ($ticket === null) {
            return;
        }

        $this->activeTab = self::TAB_PENDING;
        $this->resetPage();

        $this->mountTableAction('asignar_fecha', (string) $ticket->id);
    }

    /**
     * Boletos de la pestaña activa, con lo que necesitan las columnas.
     */
    private function ticketsForActiveTab(): Builder
    {
        return match ($this->activeTab) {
            self::TAB_HISTORY => ClientTicketAssignmentService::pastTicketsForCurrentClient(),
            self::TAB_UPCOMING => ClientTicketAssignmentService::upcomingTicketsForCurrentClient(),
            default => ClientTicketAssignmentService::pendingTicketsForCurrentClient(),
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->ticketsForActiveTab()
                ->with(['passenger', 'origin', 'destination', 'trip.schedule', 'trip.bus', 'seat']))
            // Pendientes: el más nuevo arriba. Próximos: sale primero el que viaja antes.
            // Historial: el viaje más reciente primero.
            ->defaultSort(fn (Builder $query): Builder => match ($this->activeTab) {
                self::TAB_UPCOMING => $query->orderBy($this->tripDateSubquery(), 'asc')->orderBy('tickets.id'),
                self::TAB_HISTORY => $query->orderBy($this->tripDateSubquery(), 'desc')->orderBy('tickets.id', 'desc'),
                default => $query->orderBy('tickets.id', 'desc'),
            })
            ->defaultPaginationPageOption(25)
            ->recordUrl(null)
            ->recordAction(null)
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateHeading(fn (): string => match ($this->activeTab) {
                self::TAB_HISTORY => 'Todavía no tenés viajes realizados',
                self::TAB_UPCOMING => 'No tenés viajes próximos',
                default => 'No tenés boletos pendientes de fecha',
            })
            ->emptyStateDescription(fn (): string => match ($this->activeTab) {
                self::TAB_HISTORY => 'Cuando hagas un viaje, lo vas a ver acá.',
                self::TAB_UPCOMING => 'Los boletos con fecha asignada aparecen acá hasta el día del viaje.',
                default => 'Cuando compres un boleto sin fecha, lo vas a ver acá para que elijas cuándo viajar.',
            })
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->visibleFrom('md')
                    ->alignCenter(),

                TextColumn::make('passenger.full_name')
                    ->label('Pasajero')
                    // Mobile: sólo N°, tramo y fecha caben en la pantalla.
                    ->visibleFrom('sm')
                    ->sortable(false),

                TextColumn::make('tramo')
                    ->label('Tramo')
                    ->state(fn (Ticket $record) => trim(
                        ($record->origin?->name ?? '?').' → '.($record->destination?->name ?? '?')
                    ))
                    ->badge()
                    ->color('warning'),

                // Identidad clara del tramo: "Ida" para la ida del diferido y
                // "Vuelta de N°X" para la vuelta, con el N° del boleto de ida
                // que lleva el precio. Un boleto sólo de ida (sin diferido)
                // muestra la etiqueta neutra "Solo ida".
                TextColumn::make('tipo')
                    ->badge()
                    ->state(fn (Ticket $record) => match (true) {
                        $record->isReturnLeg() => ($outbound = $record->roundTripOutboundTicket())
                            ? 'Vuelta de N°'.$outbound->id
                            : 'Vuelta',
                        $record->isPartOfRoundTrip() || (bool) $record->is_round_trip => 'Ida',
                        default => 'Solo ida',
                    })
                    ->color(fn (string $state): string => $state === 'Solo ida' ? 'gray' : 'primary')
                    // La identidad del tramo no entra en mobile.
                    ->visibleFrom('sm'),

                TextColumn::make('estado')
                    ->badge()
                    ->state(fn (Ticket $record) => $record->isPendingDate() ? 'Pendiente' : 'Confirmado')
                    ->color(fn (string $state) => $state === 'Pendiente' ? 'warning' : 'success')
                    // Mobile: la fecha "Sin fecha" ya lo dice.
                    ->visibleFrom('sm'),

                TextColumn::make('viaje')
                    ->label('Fecha y horario')
                    ->state(fn (Ticket $record) => $record->isPendingDate()
                        ? 'Sin fecha'
                        : trim(($record->trip?->trip_date?->format('d/m/Y') ?? '')
                            .($record->trip?->schedule?->departure_time?->format(' H:i') ?? '')))
                    ->badge()
                    ->color(fn (Ticket $record): string => $record->isPendingDate() ? 'warning' : 'info')
                    // Ordena por la fecha real del viaje (subquery): la columna
                    // computada "viaje" no existe en la base de datos.
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy($this->tripDateSubquery(), $direction)),

                TextColumn::make('seat.seat_number')
                    ->label('Asiento')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->placeholder('—')
                    // Mobile: el número de asiento también entra en el PDF.
                    ->visibleFrom('sm'),

                TextColumn::make('price')
                    ->label('Precio')
                    ->state(fn (Ticket $record) => $record->isReturnLeg()
                        ? '—'
                        : '$'.number_format((float) $record->price, 2, ',', '.'))
                    // Mobile: no hay espacio para las tintas del importe.
                    ->visibleFrom('sm'),
            ])
            ->recordActions([
                // Menú de tres puntitos (sólo celular): los ítems conservan su label.
                ActionGroup::make([
                    $this->assignAction(),
                    $this->viewTicketAction(),
                    $this->downloadTicketAction(),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->hiddenLabel()
                    ->size('sm')
                    ->extraAttributes(['class' => 'md:hidden']),

                // Escritorio: "Elegir fecha" siempre a la vista, con su texto.
                $this->assignAction()->extraAttributes(['class' => 'hidden md:inline-flex']),

                $this->viewTicketAction()
                    ->hiddenLabel()
                    ->extraAttributes(['title' => 'Ver boleto', 'class' => 'hidden md:inline-flex']),

                $this->downloadTicketAction()
                    ->hiddenLabel()
                    ->extraAttributes(['title' => 'Descargar boleto', 'class' => 'hidden md:inline-flex'], merge: true),
            ]);
    }

    /** Fecha del viaje del boleto, para ordenar sin traer el viaje por fila. */
    private function tripDateSubquery(): Builder
    {
        return Trip::query()
            ->select('trip_date')
            ->whereColumn('trips.id', 'tickets.trip_id');
    }

    /** Action "Ver boleto": abre el boleto del cliente en una pestaña nueva. */
    private function viewTicketAction(): Action
    {
        return Action::make('boleto_pdf')
            ->label('Ver boleto')
            ->icon('heroicon-m-eye')
            ->color('gray')
            ->button()
            ->size('sm')
            ->url(fn (Ticket $record) => route('clientes.boletos.show', ['ticket' => $record->id]))
            ->openUrlInNewTab();
    }

    /**
     * Action "Descargar": baja el PDF del boleto (la ruta valida que sea del
     * cliente). Se abre fuera del modo SPA para que el navegador lo descargue.
     */
    private function downloadTicketAction(): Action
    {
        return Action::make('descargar_pdf')
            ->label('Descargar')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('primary')
            ->button()
            ->size('sm')
            ->url(fn (Ticket $record) => route('clientes.boletos.pdf', ['ticket' => $record->id]))
            ->openUrlInNewTab()
            ->extraAttributes(['download' => '']);
    }

    /** Action "Elegir fecha": form de fecha/horario/asiento + asignación inmediata. */
    private function assignAction(): Action
    {
        return Action::make('asignar_fecha')
            ->label('Elegir fecha')
            ->icon('heroicon-m-calendar-days')
            ->color('warning')
            ->button()
            ->size('sm')
            ->visible(fn (Ticket $record) => ClientTicketAssignmentService::clientOwnsTicket($record)
                && $record->isPendingDate()
                && ! $record->trashed())
            ->steps(fn (Ticket $record) => ClientTicketAssignForm::steps($record))
            ->modalSubmitActionLabel('Sí, confirmar fecha')
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
                    // Queda registrado para el personal; el cliente no ve el detalle técnico.
                    report($e);

                    Notification::make()
                        ->title('No se pudo asignar la fecha')
                        ->body('Ocurrió un problema inesperado. Volvé a intentar en unos minutos o comunicate con boletería.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $fresh = $record->fresh();

                Notification::make()
                    ->title('¡Boleto asignado!')
                    ->body('Tu boleto N°'.$fresh->id.': '
                        .($fresh->trip?->trip_date?->format('d/m/Y') ?? '')
                        .($fresh->trip?->schedule?->departure_time?->format(' H:i') ?? '')
                        .($fresh->seat?->seat_number !== null ? ' — asiento '.$fresh->seat->seat_number : ''))
                    ->success()
                    ->actions([
                        Action::make('descargar')
                            ->label('Ver boleto')
                            ->icon('heroicon-m-eye')
                            ->url(route('clientes.boletos.show', ['ticket' => $fresh->id]), shouldOpenInNewTab: true)
                            ->button(),
                    ])
                    ->send();

                $this->resetTable();
            });
    }
}
