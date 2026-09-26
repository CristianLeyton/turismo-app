<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Setting;
use App\Models\Ticket;
use App\Services\TicketPdfService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //EditAction::make(),
            Action::make('reschedule')
                ->label('Reprogramar boleto')
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->visible(fn (Ticket $record) => (bool) Auth::user()?->is_admin)
                ->authorize('reschedule')
                ->url(function (Ticket $record) {
                    $ticketId = $record->id;

                    return TicketResource::getUrl('reschedule', ['record' => $record])
                        . '?ticket=' . $ticketId;
                }),
            self::configurePasswordConfirmation(DeleteAction::make()->icon('heroicon-m-trash')),
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

                    $pdfService = new TicketPdfService();
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
                    $colectivo = str_replace(' ', '_', $record->trip->bus->name);
                    $filename = "Boleto_N°{$ticketId}_{$colectivo}.pdf";

                    return response()->streamDownload(
                        fn() => print($pdfContent),
                        $filename,
                        ['Content-Type' => 'application/pdf']
                    );
                })
        ];
    }

    /**
     * Si el parámetro de configuración está activo, exige la contraseña del
     * admin logueado en el modal de la acción antes de ejecutarla.
     *
     * La validación vive como regla del campo password: si no coincide, el
     * formulario del modal falla y la acción estándar de borrado (con sus
     * notificaciones y halt) nunca se ejecuta. Sin override del closure de
     * la acción.
     */
    public static function configurePasswordConfirmation(\Filament\Actions\Action $action): \Filament\Actions\Action
    {
        return $action
            ->form(fn (): array => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE)
                ? [
                    TextInput::make('password')
                        ->label('Tu contraseña')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                            if (! Hash::check((string) $value, Auth::user()?->password ?? '')) {
                                $fail('La contraseña no es correcta.');
                            }
                        })
                        ->helperText('Confirmá tu clave de administrador para ejecutar esta acción.'),
                ]
                : []);
    }
}
