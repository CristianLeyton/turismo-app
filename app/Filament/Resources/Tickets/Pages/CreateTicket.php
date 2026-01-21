<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Passenger;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Sale;
use App\Services\TicketPdfService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    /*     protected static ?string $title = ' '; //alt + 255 */
    protected ?string $heading = 'Vender boleto';
    protected static ?string $breadcrumb = 'Vender';

    protected static bool $canCreateAnother = false;

    public array $seat_ids = [];

    public array $return_seat_ids = [];

    public function getTitle(): string | Htmlable
    {
        return __('');
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancelar')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Los cambios se perderán')
            ->modalDescription('¿Desea continuar?')
            ->action(fn() => $this->redirect(url('/admin')))
            ->modalCancelAction(
                fn(Action $action) =>
                $action
                    ->label('Cancelar')
                    ->color('gray')
            )

            ->modalSubmitAction(
                fn(Action $action) =>
                $action
                    ->label('Aceptar')
                    ->color('primary')
            )
            ->modalIconColor('primary');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Finalizar')
            ->hidden();
    }

    protected function getRedirectUrl(): string
    {
        // Si hay una descarga de ticket pendiente, será manejada por afterCreate
        if (session()->has('auto_download_url')) {
            return $this->url('/admin');
        }
        return $this->url('/admin');
    }

    protected function afterCreate(): void
    {
        // Si hay una descarga de ticket pendiente, abrir en nueva pestaña
        if (session()->has('auto_download_url')) {
            $url = session('auto_download_url');
            session()->forget('auto_download_url');

            // Usar Filament para ejecutar JavaScript que abra la URL en nueva pestaña
            $this->js("window.open('{$url}', '_blank');");
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        /* return Notification::make()
            ->success()
            ->title('Boleto(s) vendido(s) correctamente')
            ->body('Se estan descargando los tickets. Espere un momento por favor.')
        ; */
        return null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validación final de asientos antes de crear
        if (isset($data['trip_id']) && isset($data['seat_ids'])) {
            $trip = Trip::find($data['trip_id']);
            if ($trip) {
                $seatIds = $data['seat_ids'];
                if (!is_array($seatIds)) {
                    $seatIds = json_decode($seatIds, true) ?? [];
                }

                // Verificar disponibilidad final
                $reservationResult = $trip->reserveSeatsWithLock($seatIds);
                if (!$reservationResult['success']) {
                    $failedSeats = $reservationResult['failed_seats'];
                    $seatNumbers = [];

                    foreach ($failedSeats as $seatId) {
                        $seat = \App\Models\Seat::find($seatId);
                        if ($seat) {
                            $seatNumbers[] = $seat->seat_number;
                        }
                    }

                    Notification::make()
                        ->icon('heroicon-m-exclamation-triangle')
                        ->title('Asientos de ida no disponibles')
                        ->body('Los siguientes asientos de este viaje ya fueron vendidos: ' . implode(', ', $seatNumbers) . '. Por favor, seleccione otros asientos.')
                        ->warning()
                        ->persistent()
                        ->send();

                    // Limpiar asientos no disponibles
                    $availableSeats = array_diff($seatIds, $failedSeats);
                    $data['seat_ids'] = array_values($availableSeats);

                    if (count($availableSeats) < (int) $data['passengers_count']) {
                        // En lugar de lanzar excepción, retornamos null para cancelar la creación
                        Notification::make()
                            ->title('No se puede completar la venta')
                            ->icon('heroicon-m-x-circle')
                            /* ->body('No hay suficientes asientos disponibles para todos los pasajeros. Por favor, seleccione otros asientos o reduzca la cantidad de pasajeros.') */
                            ->danger()
                            ->persistent()
                            ->send();

                        // Cancelar la creación del registro
                        $this->halt();
                        return $data;
                    }
                }
            }
        }

        // Validación final para asientos de vuelta
        if (($data['is_round_trip'] ?? false) && isset($data['return_trip_id']) && isset($data['return_seat_ids'])) {
            $returnTrip = Trip::find($data['return_trip_id']);
            if ($returnTrip) {
                $returnSeatIds = $data['return_seat_ids'];
                if (!is_array($returnSeatIds)) {
                    $returnSeatIds = json_decode($returnSeatIds, true) ?? [];
                }

                // Verificar disponibilidad final de vuelta
                $returnReservationResult = $returnTrip->reserveSeatsWithLock($returnSeatIds);
                if (!$returnReservationResult['success']) {
                    $failedSeats = $returnReservationResult['failed_seats'];
                    $seatNumbers = [];

                    foreach ($failedSeats as $seatId) {
                        $seat = \App\Models\Seat::find($seatId);
                        if ($seat) {
                            $seatNumbers[] = $seat->seat_number;
                        }
                    }

                    Notification::make()
                        ->title('Asientos de vuelta no disponibles')
                        ->icon('heroicon-m-exclamation-triangle')
                        ->body('Los siguientes asientos de vuelta ya fueron vendidos: ' . implode(', ', $seatNumbers) . '. Por favor, seleccione otros asientos.')
                        ->warning()
                        ->persistent()
                        ->send();

                    // Limpiar asientos no disponibles
                    $availableSeats = array_diff($returnSeatIds, $failedSeats);
                    $data['return_seat_ids'] = array_values($availableSeats);

                    if (count($availableSeats) < (int) $data['passengers_count']) {
                        // En lugar de lanzar excepción, cancelamos la creación
                        Notification::make()
                            ->title('No se puede completar la venta')
                            ->icon('heroicon-m-x-circle')
                            /* ->body('No hay suficientes asientos disponibles para el viaje de vuelta. Por favor, seleccione otros asientos o reduzca la cantidad de pasajeros.') */
                            ->danger()
                            /* ->persistent() */
                            ->send();

                        // Cancelar la creación del registro
                        $this->halt();
                        return $data;
                    }
                }
            }
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            // 1. Crear la venta usando el método del modelo
            $sale = Sale::createNew(Auth::id());

            // 2. Crear todos los pasajeros (adultos y menores)
            $allPassengers = collect();
            $adultPassengers = collect();

            foreach ($data['passengers'] as $index => $passengerData) {
                // Crear pasajero adulto
                $adultPassenger = Passenger::create([
                    'first_name' => $passengerData['first_name'],
                    'last_name' => $passengerData['last_name'],
                    'dni' => $passengerData['dni'],
                    'phone_number' => $passengerData['phone_number'] ?? null,
                    'email' => $passengerData['email'] ?? null,
                    'passenger_type' => 'adult',
                ]);

                $allPassengers->push($adultPassenger);
                $adultPassengers->push($adultPassenger);

                // Crear pasajero del menor si existe
                if ($passengerData['travels_with_child'] && isset($passengerData['child_data'])) {
                    $childPassenger = Passenger::create([
                        'first_name' => $passengerData['child_data']['first_name'],
                        'last_name' => $passengerData['child_data']['last_name'],
                        'dni' => $passengerData['child_data']['dni'],
                        'parent_passenger_id' => $adultPassenger->id,
                        'passenger_type' => 'child',
                    ]);
                    $allPassengers->push($childPassenger);
                }
            }

            // 3. Preparar datos de tickets con validación de asientos
            $trip = Trip::findOrFail($data['trip_id']);
            $seatIds = $data['seat_ids'] ?? [];
            if (!is_array($seatIds)) {
                if (is_string($seatIds)) {
                    $seatIds = json_decode($seatIds, true) ?? [];
                } else {
                    $seatIds = [];
                }
            }

            // Validar y reservar asientos antes de crear tickets
            $reservationResult = $trip->reserveSeatsWithLock($seatIds);
            if (!$reservationResult['success']) {
                $failedSeatIds = $reservationResult['failed_seats'];
                $seatNumbers = [];

                foreach ($failedSeatIds as $seatId) {
                    $seat = \App\Models\Seat::find($seatId);
                    if ($seat) {
                        $seatNumbers[] = $seat->seat_number;
                    }
                }

                Notification::make()
                    ->title('Asientos de ida no disponibles')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->body('Los siguientes asientos fueron vendidos mientras completaba el formulario: ' . implode(', ', $seatNumbers) . '. Por favor, seleccione otros asientos.')
                    ->warning()
                    ->persistent()
                    ->send();

                throw new \Exception('Algunos asientos seleccionados ya no están disponibles.');
            }

            // 4. Crear tickets de ida usando el método con bloqueo
            $ticketsData = [];
            foreach ($adultPassengers as $index => $passenger) {
                $seatId = $seatIds[$index] ?? null;
                $passengerData = $data['passengers'][$index] ?? [];
                $travelsWithChild = $passengerData['travels_with_child'] ?? false;

                $ticketsData[] = [
                    'sale_id' => $sale->id,
                    'trip_id' => $data['trip_id'],
                    'seat_id' => $seatId,
                    'passenger_id' => $passenger->id,
                    'is_round_trip' => $data['is_round_trip'] ?? false,
                    'return_trip_id' => $data['return_trip_id'] ?? null,
                    'travels_with_child' => $travelsWithChild,
                    'origin_location_id' => $data['origin_location_id'],
                    'destination_location_id' => $data['destination_location_id'],
                    'price' => 0,
                ];
            }

            $result = $trip->createTicketsWithLock($ticketsData);
            if (!$result['success']) {
                $failedTickets = $result['failed_tickets'];
                $seatNumbers = [];

                foreach ($failedTickets as $failedTicket) {
                    if (isset($failedTicket['seat_id'])) {
                        $seat = \App\Models\Seat::find($failedTicket['seat_id']);
                        if ($seat) {
                            $seatNumbers[] = $seat->seat_number;
                        }
                    }
                }

                Notification::make()
                    ->title('No se pudo completar la venta')
                    ->icon('heroicon-m-x-circle')
                    ->body('Los siguientes asientos fueron vendidos en el último momento: ' . implode(', ', $seatNumbers) . '. Por favor, intente nuevamente con otros asientos.')
                    ->danger()
                    ->persistent()
                    ->send();

                throw new \Exception('No se pudieron vender todos los asientos seleccionados.');
            }

            // 5. Crear tickets de VUELTA (solo para adultos) si aplica
            if ($data['is_round_trip'] ?? false) {
                $returnTrip = Trip::findOrFail($data['return_trip_id']);
                $returnSeatIds = $data['return_seat_ids'] ?? [];
                if (!is_array($returnSeatIds)) {
                    if (is_string($returnSeatIds)) {
                        $returnSeatIds = json_decode($returnSeatIds, true) ?? [];
                    } else {
                        $returnSeatIds = [];
                    }
                }

                // Validar y reservar asientos de vuelta
                $returnReservationResult = $returnTrip->reserveSeatsWithLock($returnSeatIds);
                if (!$returnReservationResult['success']) {
                    $failedSeatIds = $returnReservationResult['failed_seats'];
                    $seatNumbers = [];

                    foreach ($failedSeatIds as $seatId) {
                        $seat = \App\Models\Seat::find($seatId);
                        if ($seat) {
                            $seatNumbers[] = $seat->seat_number;
                        }
                    }

                    Notification::make()
                        ->title('Asientos de vuelta no disponibles')
                        ->icon('heroicon-m-exclamation-triangle')
                        ->body('Los siguientes asientos de vuelta fueron vendidos: ' . implode(', ', $seatNumbers) . '. Por favor, seleccione otros asientos.')
                        ->warning()
                        ->persistent()
                        ->send();

                    throw new \Exception('Algunos asientos de vuelta seleccionados ya no están disponibles.');
                }

                // Crear tickets de vuelta
                $returnTicketsData = [];
                foreach ($adultPassengers as $index => $passenger) {
                    $seatId = $returnSeatIds[$index] ?? null;
                    $passengerData = $data['passengers'][$index] ?? [];
                    $travelsWithChild = $passengerData['travels_with_child'] ?? false;

                    $returnTicketsData[] = [
                        'sale_id' => $sale->id,
                        'trip_id' => $data['return_trip_id'],
                        'seat_id' => $seatId,
                        'passenger_id' => $passenger->id,
                        'is_round_trip' => true,
                        'travels_with_child' => $travelsWithChild,
                        'origin_location_id' => $data['destination_location_id'],
                        'destination_location_id' => $data['origin_location_id'],
                        'price' => 0,
                    ];
                }

                $returnResult = $returnTrip->createTicketsWithLock($returnTicketsData);
                if (!$returnResult['success']) {
                    $failedTickets = $returnResult['failed_tickets'];
                    $seatNumbers = [];

                    foreach ($failedTickets as $failedTicket) {
                        if (isset($failedTicket['seat_id'])) {
                            $seat = \App\Models\Seat::find($failedTicket['seat_id']);
                            if ($seat) {
                                $seatNumbers[] = $seat->seat_number;
                            }
                        }
                    }

                    Notification::make()
                        ->title('No se pudo completar la venta de vuelta')
                        ->icon('heroicon-m-x-circle')
                        ->body('Los siguientes asientos de vuelta fueron vendidos en el último momento: ' . implode(', ', $seatNumbers) . '. Por favor, intente nuevamente con otros asientos.')
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new \Exception('No se pudieron vender todos los asientos de vuelta seleccionados.');
                }
            }

            // 6. Recalcular el total de la venta
            $sale->recalculateTotal();

            // 7. Generar y descargar PDFs automáticamente
            $this->generateAndDownloadTickets($sale);

            return $result['tickets']->first();
        } catch (\Exception $e) {
            // Manejar errores y mostrar notificación adecuada
            Notification::make()
                ->icon('heroicon-m-x-circle')
                ->title('Error al vender boleto')
                ->body($e->getMessage())
                ->danger()
                ->send();

            // Relanzar la excepción para que Filament maneje el error
            throw $e;
        }
    }

    /**
     * Generar y descargar PDFs para todos los pasajeros
     */
    private function generateAndDownloadTickets(Sale $sale): void
    {
        try {
            $ticketsByPassenger = $sale->tickets()
                ->with(['passenger', 'trip', 'returnTrip', 'origin', 'destination', 'seat'])
                ->get()
                ->groupBy('passenger_id');

            if ($ticketsByPassenger->count() === 1) {
                // Un solo pasajero - generar PDF y guardar en sesión
                $passengerTickets = $ticketsByPassenger->first();
                $passenger = $passengerTickets->first()->passenger;
                $trip = $passengerTickets->first()->trip;
                $seat = $passengerTickets->first()->seat;

                $passengerName = str_replace(' ', '_', $passenger->full_name);
                $ticketId = $passengerTickets->first()->id;
                $colectivo = str_replace(' ', '_', $trip->bus->name);
                $filename = "Boleto_N°{$ticketId}_{$colectivo}.pdf";

                $data = [
                    'sale' => $sale,
                    'tickets' => $passengerTickets,
                    'passenger' => $passenger,
                    'hasChild' => $passengerTickets->contains('travels_with_child', true),
                ];

                // Generar PDF
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml(view('tickets.pdf.passenger-tickets', $data)->render());
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdfContent = $dompdf->output();

                // Guardar en sesión para descarga
                session(['ticket_pdf_content' => base64_encode($pdfContent)]);
                session(['ticket_pdf_filename' => $filename]);

                // Mostrar notificación con descarga automática
                $downloadUrl = route('tickets.download');
                Notification::make()
                    ->icon('heroicon-m-check-circle')
                    ->title('Boleto vendido correctamente')
                    ->body("El boleto para {$passenger->full_name} se está descargando automáticamente...")
                    ->success()
                    ->send();

                // Guardar URL de descarga para redirección automática
                session()->put('auto_download_url', $downloadUrl);
            } else {
                // Múltiples pasajeros - generar todos los PDFs
                $downloads = [];

                foreach ($ticketsByPassenger as $passengerId => $passengerTickets) {
                    $passenger = $passengerTickets->first()->passenger;
                    $trip = $passengerTickets->first()->trip;
                    $seat = $passengerTickets->first()->seat;

                    $passengerName = str_replace(' ', '_', $passenger->full_name);
                    $ticketId = $passengerTickets->first()->id;
                    $colectivo = str_replace(' ', '_', $trip->bus->name);
                    $filename = "Boleto_N°{$ticketId}_{$colectivo}.pdf";

                    $data = [
                        'sale' => $sale,
                        'tickets' => $passengerTickets,
                        'passenger' => $passenger,
                        'hasChild' => $passengerTickets->contains('travels_with_child', true),
                    ];

                    // Generar PDF
                    $dompdf = new \Dompdf\Dompdf();
                    $dompdf->loadHtml(view('tickets.pdf.passenger-tickets', $data)->render());
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    $pdfContent = $dompdf->output();

                    $downloads[] = [
                        'filename' => $filename,
                        'content' => base64_encode($pdfContent)
                    ];
                }

                // Guardar en sesión para descarga múltiple
                session(['ticket_pdfs_data' => $downloads]);

                // Mostrar notificación con descarga automática
                $passengerCount = count($downloads);
                $downloadUrl = route('tickets.download.multiple');
                Notification::make()
                    ->title('Boletos vendidos correctamente')
                    ->icon('heroicon-m-check-circle')
                    ->body("Se han creado {$passengerCount} boletos. Se están descargando automáticamente...")
                    ->success()
                    ->send();

                // Guardar URL de descarga para redirección automática
                session()->put('auto_download_url', $downloadUrl);
            }
        } catch (\Exception $e) {
            logger()->error('Error al generar PDFs de tickets', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->title('Error al generar tickets PDF')
                ->icon('heroicon-m-exclamation-triangle')
                ->body('Se generaron los tickets pero hubo un error al crear los PDFs. Contacte al administrador.')
                ->warning()
                ->send();
        }
    }

    /**
     * Crear página HTML para descarga múltiple de archivos
     */
    private function createMultipleDownloadPage(array $downloads): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Descargando Tickets...</title>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .message { font-size: 18px; color: #333; margin-bottom: 20px; }
                .files { margin: 20px 0; }
                .file { margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='message'>
                <h2>🎫 Descargando tus tickets...</h2>
                <p>Los archivos se descargarán automáticamente. Si no se descargan, haz clic en los enlaces:</p>
            </div>
            <div class='files'>
                " . implode('', array_map(function ($download) {
            return "<div class='file'>
                        <strong>{$download['filename']}</strong>
                        <br><a href='data:application/pdf;base64,{$download['content']}' 
                               download='{$download['filename']}' 
                               style='color: #007bff; text-decoration: none;'>
                            📥 Descargar
                        </a>
                    </div>";
        }, $downloads)) . "
            </div>
            <script>
                // Descargar automáticamente cada archivo
                setTimeout(function() {
                    " . implode('', array_map(function ($download) {
            $filename = str_replace([' ', '-'], ['_', '_'], $download['filename']);
            return "
                    const link{$filename} = document.createElement('a');
                    link{$filename}.href = 'data:application/pdf;base64,{$download['content']}';
                    link{$filename}.download = '{$download['filename']}';
                    link{$filename}.click();";
        }, $downloads)) . "
                }, 1000);
                
                // Cerrar la ventana después de 5 segundos
                setTimeout(function() {
                    window.close();
                }, 5000);
            </script>
        </body>
        </html>";
    }

    public function searchTrip(): void
    {
        try {
            // Obtener valores directamente del formulario sin validar
            $originId = $this->data['origin_location_id'] ?? null;
            $destinationId = $this->data['destination_location_id'] ?? null;
            $scheduleId = $this->data['schedule_id'] ?? null;
            $departureDate = $this->data['departure_date'] ?? null;
            $passengersCount = $this->data['passengers_count'] ?? null;

            // Validar campos requeridos manualmente
            if (blank($originId) || blank($destinationId) || blank($scheduleId) || blank($departureDate) || blank($passengersCount)) {
                Notification::make()
                    ->title('Campos incompletos')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->body('Por favor, complete todos los campos: Origen, Destino, Horario, Fecha y Cantidad de pasajeros.')
                    ->warning()
                    ->send();

                return;
            }

            // Formatear la fecha correctamente (Y-m-d)
            $tripDate = is_string($departureDate)
                ? $departureDate
                : (is_object($departureDate)
                    ? $departureDate->format('Y-m-d')
                    : \Carbon\Carbon::parse($departureDate)->format('Y-m-d'));

            // Buscar o crear el viaje
            $result = Trip::findOrCreateForBooking(
                $scheduleId,
                $tripDate,
                $originId,
                $destinationId
            );

            if (!$result['trip']) {
                Notification::make()
                    ->title('Error al buscar viaje')
                    ->icon('heroicon-m-x-circle')
                    ->body($result['message'])
                    ->danger()
                    ->send();

                // Preservar datos y limpiar trip_id
                $existingData = $this->data ?? [];
                $existingData['trip_id'] = null;
                $this->form->fill($existingData);
                return;
            }

            $trip = $result['trip'];
            $requiredSeats = (int) $passengersCount;
            $availableSeats = $result['available_seats'];

            // Validar disponibilidad de asientos
            if ($availableSeats < $requiredSeats) {
                Notification::make()
                    ->title('Asientos insuficientes')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->body("El viaje tiene {$availableSeats} asiento(s) disponible(s), pero necesita {$requiredSeats}.")
                    ->warning()
                    ->send();

                // Preservar todos los datos existentes y actualizar solo los campos necesarios
                $this->form->fill(array_merge($this->data ?? [], [
                    'trip_id' => $trip->id,
                    'trip_search_status' => 'insufficient_seats',
                    'trip_available_seats' => $availableSeats,
                ]));
                return;
            }

            // Viaje encontrado con suficientes asientos
            Notification::make()
                ->icon('heroicon-m-check-circle')
                ->title('Viaje disponible')
                ->body("Viaje encontrado. Asientos disponibles: {$availableSeats}")
                ->success()
                ->send();

            // Preservar todos los datos existentes y actualizar solo los campos necesarios
            // Usar array_merge para combinar datos existentes con los nuevos
            $this->form->fill(array_merge($this->data ?? [], [
                'trip_id' => $trip->id,
                'trip_search_status' => 'available',
                'trip_available_seats' => $availableSeats,
            ]));
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->icon('heroicon-m-x-circle')
                ->body('Error al buscar viaje: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshSeatAvailability(): void
    {
        try {
            $tripId = $this->data['trip_id'] ?? null;
            $returnTripId = $this->data['return_trip_id'] ?? null;

            if ($tripId) {
                $trip = Trip::find($tripId);
                if ($trip) {
                    $availableSeats = $trip->remainingSeats();
                    $passengersCount = (int) ($this->data['passengers_count'] ?? 1);

                    // Obtener asientos actualmente seleccionados
                    $currentSeatIds = $this->data['seat_ids'] ?? [];
                    if (!is_array($currentSeatIds)) {
                        if (is_string($currentSeatIds)) {
                            $currentSeatIds = json_decode($currentSeatIds, true) ?? [];
                        } else {
                            $currentSeatIds = [];
                        }
                    }

                    // Obtener asientos realmente disponibles
                    $availableSeatIds = $trip->availableSeats()->pluck('id')->toArray();

                    // Filtrar solo los asientos seleccionados que aún están disponibles
                    $validSelectedSeats = array_intersect($currentSeatIds, $availableSeatIds);

                    // Si se eliminaron algunos asientos de la selección, notificar al usuario
                    $removedSeats = array_diff($currentSeatIds, $validSelectedSeats);
                    if (!empty($removedSeats)) {
                        $removedSeatNumbers = [];
                        foreach ($removedSeats as $seatId) {
                            $seat = \App\Models\Seat::find($seatId);
                            if ($seat) {
                                $removedSeatNumbers[] = $seat->seat_number;
                            }
                        }

                        Notification::make()
                            ->title('Asientos de ida no disponibles')
                            ->icon('heroicon-m-exclamation-triangle')
                            ->body('Los siguientes asientos fueron vendidos: ' . implode(', ', $removedSeatNumbers) . '. Su selección ha sido actualizada.')
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        // Todos los asientos seleccionados siguen disponibles
                        $selectedCount = count($validSelectedSeats);
                        Notification::make()
                            ->title('Disponibilidad de ida actualizada')
                            ->icon('heroicon-m-check-circle')
                            ->body("Todos los asientos de ida seleccionados ({$selectedCount}) siguen disponibles. Puede continuar con la venta.")
                            ->success()
                            ->send();
                    }

                    // Actualizar estado de disponibilidad y limpiar selección si es necesario
                    $this->form->fill(array_merge($this->data ?? [], [
                        'trip_available_seats' => $availableSeats,
                        'trip_search_status' => $availableSeats >= $passengersCount ? 'available' : 'insufficient_seats',
                        'seat_ids' => array_values($validSelectedSeats), // Actualizar con solo los asientos válidos
                    ]));

                    // Si no hay suficientes asientos, limpiar selección completamente
                    if ($availableSeats < $passengersCount) {
                        $this->form->fill(array_merge($this->data ?? [], [
                            'seat_ids' => [],
                        ]));

                        Notification::make()
                            ->title('Asientos insuficientes')
                            ->icon('heroicon-m-exclamation-triangle')
                            ->body("El viaje ahora tiene {$availableSeats} asiento(s) disponible(s), pero necesita {$passengersCount}. Por favor, seleccione otros asientos.")
                            ->warning()
                            ->send();
                    }
                }
            }

            if ($returnTripId) {
                $returnTrip = Trip::find($returnTripId);
                if ($returnTrip) {
                    $availableSeats = $returnTrip->remainingSeats();
                    $passengersCount = (int) ($this->data['passengers_count'] ?? 1);

                    // Obtener asientos actualmente seleccionados para vuelta
                    $currentSeatIds = $this->data['return_seat_ids'] ?? [];
                    if (!is_array($currentSeatIds)) {
                        if (is_string($currentSeatIds)) {
                            $currentSeatIds = json_decode($currentSeatIds, true) ?? [];
                        } else {
                            $currentSeatIds = [];
                        }
                    }

                    // Obtener asientos realmente disponibles para vuelta
                    $availableSeatIds = $returnTrip->availableSeats()->pluck('id')->toArray();

                    // Filtrar solo los asientos seleccionados que aún están disponibles
                    $validSelectedSeats = array_intersect($currentSeatIds, $availableSeatIds);

                    // Si se eliminaron algunos asientos de la selección de vuelta, notificar
                    $removedSeats = array_diff($currentSeatIds, $validSelectedSeats);
                    if (!empty($removedSeats)) {
                        $removedSeatNumbers = [];
                        foreach ($removedSeats as $seatId) {
                            $seat = \App\Models\Seat::find($seatId);
                            if ($seat) {
                                $removedSeatNumbers[] = $seat->seat_number;
                            }
                        }

                        Notification::make()
                            ->title('Asientos de vuelta no disponibles')
                            ->icon('heroicon-m-exclamation-triangle')
                            ->body('Los siguientes asientos de vuelta fueron vendidos: ' . implode(', ', $removedSeatNumbers) . '. Su selección ha sido actualizada.')
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        // Todos los asientos de vuelta seleccionados siguen disponibles
                        $selectedCount = count($validSelectedSeats);
                        Notification::make()
                            ->title('Disponibilidad de vuelta actualizada')
                            ->icon('heroicon-m-check-circle')
                            ->body("Todos los asientos de vuelta seleccionados ({$selectedCount}) siguen disponibles. Puede continuar con la venta.")
                            ->success()
                            ->send();
                    }

                    // Actualizar estado de disponibilidad de vuelta y limpiar selección
                    $this->form->fill(array_merge($this->data ?? [], [
                        'return_trip_available_seats' => $availableSeats,
                        'return_trip_search_status' => $availableSeats >= $passengersCount ? 'available' : 'insufficient_seats',
                        'return_seat_ids' => array_values($validSelectedSeats), // Actualizar con solo los asientos válidos
                    ]));

                    // Si no hay suficientes asientos de vuelta, limpiar selección completamente
                    if ($availableSeats < $passengersCount) {
                        $this->form->fill(array_merge($this->data ?? [], [
                            'return_seat_ids' => [],
                        ]));

                        Notification::make()
                            ->title('Asientos insuficientes (vuelta)')
                            ->icon('heroicon-m-exclamation-triangle')
                            ->body("El viaje de vuelta ahora tiene {$availableSeats} asiento(s) disponible(s), pero necesita {$passengersCount}. Por favor, seleccione otros asientos.")
                            ->warning()
                            ->send();
                    }
                }
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al actualizar disponibilidad')
                ->icon('heroicon-m-x-circle')
                ->body('No se pudo actualizar la disponibilidad de asientos: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function searchReturnTrip(): void
    {
        try {
            // Obtener valores directamente del formulario sin validar
            $originId = $this->data['origin_location_id'] ?? null;
            $destinationId = $this->data['destination_location_id'] ?? null;
            $returnDate = $this->data['return_date'] ?? null;
            $returnScheduleId = $this->data['return_schedule_id'] ?? null;
            $passengersCount = $this->data['passengers_count'] ?? null;

            // Validar campos requeridos manualmente
            if (blank($originId) || blank($destinationId) || blank($returnDate) || blank($returnScheduleId) || blank($passengersCount)) {
                Notification::make()
                    ->title('Campos incompletos')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->body('Por favor, complete todos los campos necesarios para el viaje de vuelta: Fecha y Horario.')
                    ->warning()
                    ->send();

                return;
            }

            // Formatear la fecha correctamente
            $returnDateFormatted = is_string($returnDate)
                ? $returnDate
                : (is_object($returnDate)
                    ? $returnDate->format('Y-m-d')
                    : \Carbon\Carbon::parse($returnDate)->format('Y-m-d'));

            // Buscar o crear el viaje de vuelta usando el horario seleccionado
            $result = Trip::findOrCreateForBooking(
                $returnScheduleId,
                $returnDateFormatted,
                $destinationId, // Origen de vuelta = destino de ida
                $originId // Destino de vuelta = origen de ida
            );

            if (!$result['trip']) {
                Notification::make()
                    ->title('Error al buscar viaje de vuelta')
                    ->icon('heroicon-m-x-circle')
                    ->body($result['message'])
                    ->danger()
                    ->send();

                // Preservar datos y limpiar return_trip_id
                $existingData = $this->data ?? [];
                $existingData['return_trip_id'] = null;
                $this->form->fill($existingData);
                return;
            }

            $trip = $result['trip'];
            $requiredSeats = (int) $passengersCount;
            $availableSeats = $result['available_seats'];

            // Validar disponibilidad de asientos
            if ($availableSeats < $requiredSeats) {
                Notification::make()
                    ->title('Asientos insuficientes para vuelta')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->body("El viaje de vuelta tiene {$availableSeats} asiento(s) disponible(s), pero necesita {$requiredSeats}.")
                    ->warning()
                    ->send();

                // Preservar todos los datos existentes y actualizar solo los campos necesarios
                $this->form->fill(array_merge($this->data ?? [], [
                    'return_trip_id' => $trip->id,
                    'return_trip_search_status' => 'insufficient_seats',
                    'return_trip_available_seats' => $availableSeats,
                ]));
                return;
            }

            // Viaje de vuelta encontrado con suficientes asientos
            Notification::make()
                ->title('Viaje de vuelta disponible')
                ->icon('heroicon-m-check-circle')
                ->body("Viaje de vuelta encontrado. Asientos disponibles: {$availableSeats}")
                ->success()
                ->send();

            // Preservar todos los datos existentes y actualizar solo los campos necesarios
            $this->form->fill(array_merge($this->data ?? [], [
                'return_trip_id' => $trip->id,
                'return_trip_search_status' => 'available',
                'return_trip_available_seats' => $availableSeats,
            ]));
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->icon('heroicon-m-x-circle')
                ->body('Error al buscar viaje de vuelta: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
