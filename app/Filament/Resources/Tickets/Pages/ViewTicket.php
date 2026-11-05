<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Actions\TicketDeliveryAction;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Setting;
use App\Models\Ticket;
use App\Services\TicketPdfService;
use App\Support\SeatConflict;
use App\Support\TicketDelivery;
use App\Support\WhatsAppShare;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    /** Teléfono de "Enviar por WhatsApp", resuelto una vez por request. */
    private ?string $whatsappPhone = null;

    private bool $isWhatsappPhoneResolved = false;

    /**
     * En modo link, el boleto vigente tiene su link público desde que se
     * abre el detalle: lo usan "Copiar link" de la vista previa y "Enviar
     * por WhatsApp". También abre el modal de entrega si se llega desde una
     * reprogramación en modo link (pedido en la sesión flash: al recargar
     * ya no está).
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (self::hasShareLink($this->getRecord())) {
            $this->getRecord()->shareToken();
        }

        $pending = TicketDelivery::pending();

        if ($pending !== null
            && $pending['kind'] === TicketDelivery::KIND_RESCHEDULE
            && $pending['ticket_ids'] === [(int) $this->getRecord()->getKey()]) {
            $this->defaultAction = TicketDeliveryAction::NAME;
            $this->defaultActionArguments = $pending;
        }
    }

    public function entregaBoletosAction(): Action
    {
        return TicketDeliveryAction::make();
    }

    /**
     * El boleto se entrega por link: parámetro de descarga automática
     * desactivado y boleto vigente (el link de uno eliminado da 404).
     */
    public static function hasShareLink(Ticket $ticket): bool
    {
        return ! Setting::descargaAutomaticaPdf() && ! $ticket->trashed();
    }

    private function whatsappPhone(Ticket $ticket): ?string
    {
        if (! $this->isWhatsappPhoneResolved) {
            $this->whatsappPhone = TicketDelivery::phoneForTicket($ticket);
            $this->isWhatsappPhoneResolved = true;
        }

        return $this->whatsappPhone;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit_ticket')
                ->label('Editar boleto')
                ->icon('heroicon-m-pencil-square')
                ->color('info')
                ->visible(fn (Ticket $record) => (bool) Auth::user()?->can('tickets.edit'))
                ->url(fn (Ticket $record) => TicketResource::getUrl('edit', ['record' => $record])),
            Action::make('reschedule')
                ->label('Reprogramar boleto')
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->visible(fn (Ticket $record) => (bool) Auth::user()?->can('tickets.reschedule'))
                ->authorize('reschedule')
                ->url(function (Ticket $record) {
                    $ticketId = $record->id;

                    return TicketResource::getUrl('reschedule', ['record' => $record])
                        .'?ticket='.$ticketId;
                }),
            self::configurePasswordConfirmation(
                DeleteAction::make()->icon('heroicon-m-trash'),
                withReason: true,
            ),
            self::configurePasswordConfirmation(ForceDeleteAction::make()),
            RestoreAction::make()
                // Un solo boleto vigente por viaje y asiento: si el asiento se
                // revendió mientras el boleto estaba dado de baja, la base
                // rechaza la restauración y se informa en vez de un error 500.
                ->using(function (Ticket $record, RestoreAction $action): bool {
                    try {
                        return (bool) $record->restore();
                    } catch (QueryException $e) {
                        if (! SeatConflict::isActiveSeatViolation($e)) {
                            throw $e;
                        }

                        Notification::make()
                            ->title('No se pudo restaurar el boleto')
                            ->body('El asiento '.($record->seat?->seat_number ?? $record->seat_id).' ya está ocupado por otro boleto vigente en este viaje. Reprogramá el boleto o liberá el asiento antes de restaurarlo.')
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->cancel();

                        return false;
                    }
                }),
            // Emisión por link: el link del boleto por WhatsApp al teléfono
            // de su pasajero (o del padrón). Sin teléfono no hay a quién.
            Action::make('enviar_whatsapp')
                ->label('Enviar por WhatsApp')
                ->icon('heroicon-m-paper-airplane')
                ->color('success')
                ->visible(fn (Ticket $record): bool => self::hasShareLink($record) && $this->whatsappPhone($record) !== null)
                ->url(fn (Ticket $record): ?string => $this->whatsappPhone($record) !== null
                    ? WhatsAppShare::url($this->whatsappPhone($record), $record->shareUrl())
                    : null, shouldOpenInNewTab: true),
            Action::make('download_pdf')
                ->label('Descargar')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('primary')
                ->button()->extraAttributes(
                    ['title' => 'Descargar boleto']
                )
                ->action(function (Ticket $record) {
                    $pdf = (new TicketPdfService)->forTicket($record);

                    return response()->streamDownload(
                        fn () => print ($pdf['content']),
                        $pdf['filename'],
                        ['Content-Type' => 'application/pdf']
                    );
                }),
        ];
    }

    /**
     * Modal de las acciones destructivas del boleto.
     *
     * - Si el parámetro de configuración está activo, exige la contraseña del
     *   admin logueado antes de ejecutar la acción. La validación vive como
     *   regla del campo password: si no coincide, el formulario del modal
     *   falla y la acción estándar de borrado (con sus notificaciones y halt)
     *   nunca se ejecuta. Sin override del closure de la acción.
     * - `withReason` agrega el campo MOTIVO obligatorio (sólo en la
     *   eliminación normal, `DeleteAction`): se persiste en
     *   `tickets.deletion_reason` antes del soft delete y se muestra en el
     *   detalle de la auditoría de boletos (`ticket-audit-detail`). La
     *   eliminación definitiva no lo pide porque el registro desaparece y
     *   ese motivo quedaría sin historia donde mostrarlo.
     */
    public static function configurePasswordConfirmation(
        \Filament\Actions\Action $action,
        bool $withReason = false,
    ): \Filament\Actions\Action {
        $action
            ->form(function () use ($withReason): array {
                $fields = [];

                if (Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE)) {
                    $fields[] = TextInput::make('password')
                        ->label('Tu contraseña')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                            if (! Hash::check((string) $value, Auth::user()?->password ?? '')) {
                                $fail('La contraseña no es correcta.');
                            }
                        })
                        ->helperText('Confirmá tu clave de administrador para ejecutar esta acción.');
                }

                if ($withReason) {
                    $fields[] = Textarea::make('motivo')
                        ->label('Motivo de la eliminación')
                        ->placeholder('Ej.: venta duplicada, pasajero se arrepintió…')
                        ->rows(2)
                        ->required()
                        ->maxLength(250)
                        ->helperText('Obligatorio: se guarda en el historial de boletos.')
                        ->validationMessages([
                            'required' => 'El motivo de la eliminación es obligatorio.',
                            'max' => 'El motivo no debe superar los :max caracteres.',
                        ]);
                }

                return $fields;
            });

        if ($withReason) {
            $action->before(function (array $data, Ticket $record): void {
                $record->deletion_reason = trim((string) ($data['motivo'] ?? ''));
                $record->save();
            });
        }

        return $action;
    }
}
