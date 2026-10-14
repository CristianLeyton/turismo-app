<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Setting;
use App\Models\Ticket;
use App\Services\TicketPdfService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

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
            RestoreAction::make(),
            Action::make('download_pdf')
                ->label('Descargar')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('primary')
                ->button()->extraAttributes(
                    ['title' => 'Descargar boleto']
                )
                ->action(function (Ticket $record) {

                    $pdfService = new TicketPdfService;
                    $sale = $record->sale;

                    if ($record->is_round_trip) {
                        $passengerTickets = $sale->tickets()
                            ->where('passenger_id', $record->passenger_id)
                            ->with(['trip', 'returnTrip', 'origin', 'destination', 'seat'])
                            ->get();

                        $pdfContent = $pdfService->generateRoundTripTicket($sale, $passengerTickets);
                    } else {
                        $pdfContent = $pdfService->generatePassengerTickets($sale, collect([$record]));
                    }

                    $ticketId = $record->id;
                    $colectivo = str_replace(' ', '_', $record->trip?->bus?->name ?? 'Sin fecha');
                    $filename = "Boleto_N°{$ticketId}_{$colectivo}.pdf";

                    return response()->streamDownload(
                        fn () => print ($pdfContent),
                        $filename,
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
