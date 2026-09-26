<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\Schemas\TicketRescheduleForm;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\SeatReservation;
use App\Models\Ticket;
use App\Models\Trip;
use App\Services\TicketPdfService;
use App\Services\TicketRescheduleService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;


class RescheduleTicket extends ResourcePage
{
    use InteractsWithRecord;

    protected static string $resource = TicketResource::class;

    protected static ?string $breadcrumb = 'Reprogramar boleto';

    protected ?string $heading = 'Reprogramar boleto';

    protected static ?string $title = 'Reprogramar boleto';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();

        $this->form->fill([
            'scope' => 'outbound',
        ]);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::canAccess(['record' => $this->getRecord()]), 403);

        /** @var Ticket $ticket */
        $ticket = $this->getRecord();

        abort_unless($this->targetTicketId() !== null, 404, 'Boleto no encontrado.');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::check() && Auth::user()->is_admin;
    }

    public function getTitle(): string
    {
        return 'Reprogramar boleto';
    }

    /**
     * Ticket a reprogramar: el de la URL (?ticket=N para apuntar al tramo
     * correcto del pasajero) o el record de la ruta.
     */
    public function targetTicketId(): ?int
    {
        $fromQuery = request()->query('ticket');

        $id = filled($fromQuery) ? (int) $fromQuery : (int) ($this->record?->getKey() ?? 0);

        return $id > 0 ? $id : null;
    }

    public function targetTicket(): ?Ticket
    {
        $id = $this->targetTicketId();

        return $id ? Ticket::query()->find($id) : null;
    }

    public function form(Schema $schema): Schema
    {
        return TicketRescheduleForm::configure($schema);
    }

    /**
     * Punto de extensión de Livewire/Filament para el schema "form": fija el
     * statePath 'data' (como defaultForm() en EditTenantProfile).
     */
    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): \Filament\Schemas\Components\Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('confirmReschedule')
            ->footer([
                $this->getFormActionsContentComponent(),
            ]);
    }

    public function getFormActions(): array
    {
        return [
            Action::make('confirm_reschedule')
                ->label('Confirmar reprogramación')
                ->color('success')
                ->icon('heroicon-m-check-circle')
                ->requiresConfirmation()
                ->disabled(function () {
                    // getRawState() lee el estado sin validar: getState() lanzaría
                    // ValidationException al renderizar con el formulario aún vacío.
                    $state = $this->form->getRawState();

                    return blank($state['date'] ?? null)
                        || blank($state['schedule_id'] ?? null);
                })
                ->action(fn () => $this->confirmReschedule()),

            Action::make('cancel_reschedule')
                ->label('Cancelar')
                ->color('gray')
                ->action(function () {
                    // Liberar reservas de esta sesión y volver a la vista del boleto.
                    SeatReservation::releaseBySession(session()->getId());

                    $ticket = $this->targetTicket();

                    $this->redirect(TicketResource::getUrl('view', ['record' => $ticket?->getKey() ?? $this->record?->getKey()]));
                }),
        ];
    }

    public function getFormActionsContentComponent(): \Filament\Schemas\Components\Component
    {
        return Actions::make($this->getFormActions())
            ->alignment('left')
            ->fullWidth(false);
    }

    public function confirmReschedule(): void
    {
        $this->authorizeAccess();

        $ticket = $this->targetTicket();
        if (! $ticket) {
            abort(404, 'Boleto no encontrado.');
        }

        $data = $this->form->getState();

        $scope = $data['scope'] ?? 'outbound';
        $isRoundTrip = (bool) $ticket->is_round_trip;
        $isOutboundTicket = $isRoundTrip && ! is_null($ticket->return_trip_id);

        $payload = [
            'scope' => $isOutboundTicket ? $scope : 'outbound',
            'date' => $data['date'] ?? null,
            'schedule_id' => $data['schedule_id'] ?? null,
            'seat_id' => $data['seat_ids'][0] ?? null,
        ];

        if ($isOutboundTicket && $scope === 'both') {
            $payload['return_date'] = $data['return_date'] ?? null;
            $payload['return_schedule_id'] = $data['return_schedule_id'] ?? null;
            $payload['return_seat_id'] = $data['return_seat_ids'][0] ?? null;
        }

        // Confirmación de contraseña (opt-in vía Configuración). El servicio
        // la valida antes de cualquier mutación y lanza ValidationException
        // con la clave 'confirm_password'.
        $payload['confirm_password'] = $data['confirm_password'] ?? null;

        try {
            app(TicketRescheduleService::class)->reschedule($ticket, $payload);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ((array) $messages as $message) {
                    Notification::make()
                        ->title('No se pudo reprogramar')
                        ->body($message)
                        ->danger()
                        ->persistent()
                        ->send();
                }
            }

            return; // Conservar el estado del form
        }

        // Éxito: generar PDF y ofrecer descarga (patrón de CreateTicket).
        $freshTicket = $ticket->fresh()->load(['sale', 'passenger', 'trip', 'returnTrip', 'seat']);

        $sale = $freshTicket->sale;
        $pdfService = new TicketPdfService();

        $passengerTickets = $sale->tickets()
            ->where('passenger_id', $freshTicket->passenger_id)
            ->with(['trip', 'returnTrip', 'origin', 'destination', 'seat'])
            ->get();

        if ($freshTicket->is_round_trip) {
            $pdfContent = $pdfService->generateRoundTripTicket($sale, $passengerTickets);
        } else {
            $pdfContent = $pdfService->generatePassengerTickets($sale, collect([$freshTicket]));
        }

        $colectivo = str_replace(' ', '_', $freshTicket->trip->bus->name);
        $filename = "Boleto_N°{$freshTicket->id}_{$colectivo}.pdf";

        session([
            'ticket_pdf_content' => base64_encode($pdfContent),
            'ticket_pdf_filename' => $filename,
        ]);

        $downloadUrl = route('tickets.download');

        Notification::make()
            ->title('Boleto reprogramado')
            ->body("El boleto N°{$freshTicket->id} fue reprogramado correctamente.")
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar boleto')
                    ->url($downloadUrl, shouldOpenInNewTab: true)
                    ->button(),
            ])
            ->send();

        $this->js("window.open('{$downloadUrl}', '_blank');");

        $this->redirect(TicketResource::getUrl('view', ['record' => $freshTicket->id]));
    }

    /**
     * Conflicto de reserva desde el selector de asientos (mismo contrato que CreateTicket).
     */
    public function handleSeatReservationConflict($data): void
    {
        $message = $data['message'] ?? 'Conflicto de reservación detectado';

        Notification::make()
            ->title('Conflicto de reservación')
            ->body($message . '. Por favor, seleccione otros asientos.')
            ->warning()
            ->send();

        // Refrescar disponibilidad del viaje de ida
        if ($tripId = $this->data['trip_id'] ?? null) {
            $trip = Trip::find($tripId);
            if ($trip) {
                $availableSeatIds = $trip->availableSeats()->pluck('id')->toArray();
                $current = (array) ($this->data['seat_ids'] ?? []);
                $this->data['seat_ids'] = array_values(array_intersect($current, $availableSeatIds));
            }
        }

        // Refrescar disponibilidad del viaje de vuelta
        if ($returnTripId = $this->data['return_trip_id'] ?? null) {
            $trip = Trip::find($returnTripId);
            if ($trip) {
                $availableSeatIds = $trip->availableSeats()->pluck('id')->toArray();
                $current = (array) ($this->data['return_seat_ids'] ?? []);
                $this->data['return_seat_ids'] = array_values(array_intersect($current, $availableSeatIds));
            }
        }
    }

    /**
     * Notificación de expiración de reserva desde el selector (mismo contrato que CreateTicket).
     */
    public function notifyReservationExpired(): void
    {
        Notification::make()
            ->title('Asientos expirados')
            ->icon('heroicon-m-clock')
            ->body('Los asientos han sido liberados. Por favor, seleccionalos nuevamente si aún los necesitas.')
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * Liberar reservas si el admin abandona la página sin confirmar.
     * (Patrón __destruct de CreateTicket.)
     */
    public function __destruct()
    {
        try {
            if (! request()->ajax() && ! request()->hasHeader('X-Livewire')) {
                $sessionId = session()->getId();
                SeatReservation::where('user_session_id', $sessionId)->delete();
            }
        } catch (\Throwable $e) {
            // Ignorar errores en la destrucción del componente
        }
    }
}
