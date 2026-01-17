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

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected static ?string $title = 'Vender pasaje';

    protected static ?string $breadcrumb = 'Vender';

    protected static bool $canCreateAnother = false;

    public array $seat_ids = [];

    public array $return_seat_ids = [];

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Vender pasaje')
            ->hidden();
    }

    protected function getRedirectUrl(): string
    {
        // Si hay una descarga de ticket pendiente, será manejada por afterCreate
        if (session()->has('auto_download_url')) {
            return $this->getResource()::getUrl('index');
        }
        return $this->getResource()::getUrl('index');
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
        return Notification::make()
            ->success()
            ->title('Pasaje(s) vendido(s) correctamente')
            ->body('Se estan descargando los tickets. Espere un momento por favor.')
        ;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // 1. Crear la venta usando el método del modelo
        $sale = Sale::createNew(Auth::id());

        // 2. Crear todos los pasajeros (adultos y niños)
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

            // Crear pasajero niño si existe
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

        // 3. Crear tickets SOLO para adultos
        $seatIds = $data['seat_ids'] ?? [];
        if (!is_array($seatIds)) {
            if (is_string($seatIds)) {
                $seatIds = json_decode($seatIds, true) ?? [];
            } else {
                $seatIds = [];
            }
        }

        // Log para depuración: qué seat_ids recibimos
        logger()->info('Creating tickets (ida) - seat_ids', [
            'seat_ids' => $seatIds,
            'adult_passengers_count' => $adultPassengers->count(),
        ]);

        foreach ($adultPassengers as $index => $passenger) {
            $seatId = $seatIds[$index] ?? null;
            $passengerData = $data['passengers'][$index] ?? [];
            $travelsWithChild = $passengerData['travels_with_child'] ?? false;

            $ticket = $sale->tickets()->create([
                'trip_id' => $data['trip_id'],
                'seat_id' => $seatId,
                'passenger_id' => $passenger->id,
                'is_round_trip' => $data['is_round_trip'] ?? false,
                'return_trip_id' => $data['return_trip_id'] ?? null,
                'travels_with_child' => $travelsWithChild,
                'origin_location_id' => $data['origin_location_id'],
                'destination_location_id' => $data['destination_location_id'],
                'price' => 0,
            ]);

            logger()->info('Ticket creado (ida)', [
                'passenger_index' => $index,
                'passenger_id' => $passenger->id,
                'seat_id_assigned' => $seatId,
                'travels_with_child' => $travelsWithChild,
                'ticket' => $ticket->toArray(),
            ]);
        }

        // 4. Crear tickets de VUELTA (solo para adultos)
        if ($data['is_round_trip'] ?? false) {
            $returnSeatIds = $data['return_seat_ids'] ?? [];
            if (!is_array($returnSeatIds)) {
                if (is_string($returnSeatIds)) {
                    $returnSeatIds = json_decode($returnSeatIds, true) ?? [];
                } else {
                    $returnSeatIds = [];
                }
            }

            foreach ($adultPassengers as $index => $passenger) {
                $seatId = $returnSeatIds[$index] ?? null;
                $passengerData = $data['passengers'][$index] ?? [];
                $travelsWithChild = $passengerData['travels_with_child'] ?? false;

                $sale->tickets()->create([
                    'trip_id' => $data['return_trip_id'],
                    'seat_id' => $seatId,
                    'passenger_id' => $passenger->id,
                    'is_round_trip' => true,
                    'travels_with_child' => $travelsWithChild,
                    'origin_location_id' => $data['destination_location_id'],
                    'destination_location_id' => $data['origin_location_id'],
                    'price' => 0,
                ]);
            }
        }

        // 5. Recalcular el total de la venta
        $sale->recalculateTotal();

        // 6. Generar y descargar PDFs automáticamente
        $this->generateAndDownloadTickets($sale);

        return $adultPassengers->first()->tickets()->first();
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
                $tripId = $trip->id;
                $seatNumber = $seat ? $seat->number : 'SinAsiento';
                $filename = "{$passengerName}_Viaje{$tripId}_Asiento{$seatNumber}.pdf";

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
                    ->title('✅ Ticket Generado')
                    ->body("El ticket para {$passenger->full_name} se está descargando automáticamente...")
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
                    $tripId = $trip->id;
                    $seatNumber = $seat ? $seat->number : 'SinAsiento';
                    $filename = "{$passengerName}_Viaje{$tripId}_Asiento{$seatNumber}.pdf";

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
                    ->title('✅ Tickets Generados')
                    ->body("Se generaron {$passengerCount} tickets. Se están descargando automáticamente...")
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
                ->body('Error al buscar viaje: ' . $e->getMessage())
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
                ->body('Error al buscar viaje de vuelta: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
