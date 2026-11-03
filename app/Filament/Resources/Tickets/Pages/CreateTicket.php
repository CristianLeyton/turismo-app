<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Exceptions\DuplicatePassengerOnTripException;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Passenger;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SeatReservation;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Support\DuplicateTicketNotification;
use App\Support\SaleCutoff;
use App\Support\ServiceDays;
use App\Support\ShareLinkNotification;
use App\Support\TripPassengerConflicts;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    /*     protected static ?string $title = ' '; //alt + 255 */
    protected ?string $heading = '';

    protected static ?string $breadcrumb = 'Vender';

    protected static bool $canCreateAnother = false;

    public array $seat_ids = [];

    public array $return_seat_ids = [];

    public const DUPLICATE_PASSENGER_ACTION = 'duplicatePassenger';

    /**
     * Pasos con asientos y el campo que guarda la selección de cada uno.
     *
     * @var array<string, string>
     */
    private const SEAT_STEPS = [
        'seat_ids' => 'Asientos (Ida)',
        'return_seat_ids' => 'Asientos (Vuelta)',
    ];

    /**
     * Al presionar "Finalizar" el asistente valida todos los pasos. Si fallan
     * los asientos (p. ej. la reserva venció y se liberaron), el error queda
     * en un campo oculto de un paso anterior y no se ve: se avisa con una
     * notificación que explica el problema y cómo volver a elegirlos.
     */
    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        $failedSeatFields = collect(array_keys(self::SEAT_STEPS))
            ->filter(fn (string $field): bool => array_key_exists("data.{$field}", $exception->errors()))
            ->values();

        if ($failedSeatFields->isEmpty()) {
            return;
        }

        $this->notifySeatsNeedReselection($failedSeatFields->all());
    }

    /**
     * @param  list<string>  $failedSeatFields  'seat_ids' y/o 'return_seat_ids'.
     */
    private function notifySeatsNeedReselection(array $failedSeatFields): void
    {
        $isOnlyReturn = $failedSeatFields === ['return_seat_ids'];
        $legs = count($failedSeatFields) > 1 ? '' : ($isOnlyReturn ? ' de vuelta' : ' de ida');
        $step = self::SEAT_STEPS[$failedSeatFields[0]];

        Notification::make()
            ->title("Faltan los asientos{$legs}")
            ->body("La reserva de los asientos venció y se liberaron. Presioná «Anterior» hasta el paso «{$step}», elegí los asientos de nuevo y volvé a finalizar la venta.")
            ->icon('heroicon-m-clock')
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * Modal del paso "Pasajeros": algún DNI de la venta ya tiene boleto en
     * el viaje. Muestra una tarjeta por persona (quién, qué boleto, tramo,
     * asiento y salida) con "Ver boleto" en otra pestaña, y "Volver a
     * pasajeros", que cierra el modal sin tocar la venta. Los conflictos se
     * recalculan con los DNI y viajes recibidos.
     */
    public function duplicatePassengerAction(): Action
    {
        $conflicts = fn (array $arguments) => TripPassengerConflicts::find(
            (array) ($arguments['dnis'] ?? []),
            (array) ($arguments['tripIds'] ?? []),
        );

        return Action::make(self::DUPLICATE_PASSENGER_ACTION)
            ->modalHeading(fn (array $arguments): string => DuplicateTicketNotification::title($conflicts($arguments)))
            ->modalDescription(DuplicateTicketNotification::HINT)
            ->modalIcon('heroicon-o-identification')
            ->modalIconColor('danger')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalContent(fn (array $arguments) => view('filament.tickets.duplicate-passenger-modal', [
                'rows' => DuplicateTicketNotification::rows($conflicts($arguments)),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Volver a pasajeros');
    }

    public function getTitle(): string|Htmlable
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
            ->action(function () {
                $this->releaseSaleReservations();

                // Redirigir al admin
                $this->redirect(url('/admin'));
            })
            ->modalCancelAction(
                fn (Action $action) => $action
                    ->label('Cancelar')
                    ->color('gray')
            )

            ->modalSubmitAction(
                fn (Action $action) => $action
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
            return url('/admin');
        }

        return url('/admin');
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
                if (! is_array($seatIds)) {
                    $seatIds = json_decode($seatIds, true) ?? [];
                }

                // Verificar disponibilidad final
                $reservationResult = $trip->reserveSeatsWithLock($seatIds);
                if (! $reservationResult['success']) {
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
                        ->body('Los siguientes asientos de este viaje ya fueron vendidos: '.implode(', ', $seatNumbers).'. Por favor, seleccione otros asientos.')
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

                        $this->releaseSaleReservations($data);

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
                if (! is_array($returnSeatIds)) {
                    $returnSeatIds = json_decode($returnSeatIds, true) ?? [];
                }

                // Verificar disponibilidad final de vuelta
                $returnReservationResult = $returnTrip->reserveSeatsWithLock($returnSeatIds);
                if (! $returnReservationResult['success']) {
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
                        ->body('Los siguientes asientos de vuelta ya fueron vendidos: '.implode(', ', $seatNumbers).'. Por favor, seleccione otros asientos.')
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

                        $this->releaseSaleReservations($data);

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
    {        // -1. Defensa server-side: emitir boletos sin fecha exige el permiso
        // dedicado. El switch solo se muestra con permiso; acá se rechaza
        // quien lo fuerce por Livewire sin tenerlo.
        // Casts booleanos estrictos: un toggle apagado llega como `false` y
        // filled(false) es true en Laravel (los booleans nunca son "blank"),
        // lo que hacía vender SIN fecha ventas normales.
        if ((bool) ($data['sell_without_date_ida'] ?? false) || (bool) ($data['sell_without_date_vuelta'] ?? false)) {
            if (! auth()->user()?->can('tickets.vender_sin_fecha')) {
                Notification::make()
                    ->title('No se puede completar la venta')
                    ->icon('heroicon-m-shield-exclamation')
                    ->body('No tenés permiso para vender boletos sin fecha/horario.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();

                return $data;
            }
        }

        // 0. Validar que ningún pasajero sea un cliente baneado (defensa server-side).
        // Va antes del try para que el Halt no sea capturado por el catch genérico de abajo.
        $bannedDnis = collect($data['passengers'] ?? [])
            ->pluck('dni')
            ->filter(fn ($dni) => filled($dni))
            ->unique()
            ->values();

        if ($bannedDnis->isNotEmpty()) {
            $bannedClients = \App\Models\Clients::query()
                ->whereIn('dni', $bannedDnis->all())
                ->where('can_buy', false)
                ->get();

            if ($bannedClients->isNotEmpty()) {
                $details = $bannedClients
                    ->map(fn (\App\Models\Clients $client) => "{$client->nombre} {$client->apellido} (DNI {$client->dni})".(filled($client->comments) ? " - Motivo: {$client->comments}" : ''))
                    ->implode('; ');

                Notification::make()
                    ->title('No se puede completar la venta')
                    ->icon('heroicon-m-shield-exclamation')
                    ->body('Hay pasajeros con prohibición de compra: '.$details)
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();

                return $data;
            }
        }

        // 0.bis Venta sin fecha: sólo pasajeros registrados en el padrón de
        // clientes. El panel de clientes ubica los boletos vía el DNI del
        // padrón, así que un boleto sin fecha emitido a un DNI fuera del
        // padrón le quedaría invisible al cliente. Defensa espeja de la
        // regla del form (el vendedor puede saltársela manipulando Livewire).
        if ((bool) ($data['sell_without_date_ida'] ?? false) || (bool) ($data['sell_without_date_vuelta'] ?? false)) {
            $pendingDnis = collect($data['passengers'] ?? [])
                ->pluck('dni')
                ->filter(fn ($dni) => filled($dni))
                ->unique()
                ->values();

            $unregistered = $pendingDnis
                ->reject(fn ($dni) => \App\Models\Clients::query()->where('dni', $dni)->exists());

            if ($unregistered->isNotEmpty()) {
                $details = $unregistered->implode(', ');

                Notification::make()
                    ->title('No se puede completar la venta')
                    ->icon('heroicon-m-shield-exclamation')
                    ->body("Los boletos sin fecha sólo se venden a pasajeros registrados en el padrón de clientes. DNIs sin registrar: {$details}. Si no, el pasajero no podría ver su boleto desde el panel de clientes. Registralo en Clientes (o con la acción «Crear cliente» del pasajero) y volvé a intentar la venta.")
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();

                return $data;
            }
        }

        // 0.b Validar que los métodos de pago enviados existan y estén activos.
        // La columna ya no es ENUM (es VARCHAR + FK), así que la DB no rechaza
        // códigos raros por sí sola; validamos contra los métodos activos.
        $sentMethods = collect($data['passengers'] ?? [])
            ->pluck('payment_method')
            ->filter(fn ($method) => filled($method))
            ->unique()
            ->values();

        if ($sentMethods->isNotEmpty()) {
            $validMethods = array_keys(PaymentMethod::options());
            $invalidMethods = $sentMethods->reject(fn ($method) => in_array($method, $validMethods, true));

            if ($invalidMethods->isNotEmpty()) {
                Notification::make()
                    ->title('No se puede completar la venta')
                    ->icon('heroicon-m-banknotes')
                    ->body('Hay un método de pago inválido o inactivo en la venta. Recargá el formulario e intentá de nuevo.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();

                return $data;
            }
        }

        // 0.c Colectivo obligatorio en toda venta: el wizard siempre exige
        // bus_id, acá se defiende server-side y se acarrea a los tramos
        // pendientes para que la reprogramación posterior sólo acepte
        // horarios de rutas de ese mismo colectivo.
        if (blank($data['bus_id'] ?? null)) {
            Notification::make()
                ->title('No se puede completar la venta')
                ->icon('heroicon-m-bus')
                ->body('Seleccione el colectivo antes de completar la venta.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();

            return $data;
        }

        // 0.d Venta fuera de término: no se puede crear un pasaje de un viaje
        // que ya salió hace más de las horas configuradas (Configuración →
        // venta fuera de término), salvo con el permiso dedicado.
        // Va ANTES del try para que el Halt no lo capture el catch genérico, y
        // cubre los cuatro modos (normal, diferido con las dos fechas y los
        // mixtos con la ida o la vuelta pendientes) porque lee los viajes
        // ya resueltos en el formulario.
        if (filled($data['trip_id'] ?? null)) {
            $this->assertDepartureWithinLimit(
                Trip::find($data['trip_id']),
                $data['origin_location_id'] ?? null,
                'ida',
            );
        }

        if ((bool) ($data['is_round_trip'] ?? false) && filled($data['return_trip_id'] ?? null)) {
            $this->assertDepartureWithinLimit(
                Trip::find($data['return_trip_id']),
                $data['destination_location_id'] ?? null,
                'vuelta',
            );
        }

        // 0.e Días de servicio: cada tramo con fecha tiene que caer en un día
        // con servicio, salvo con el permiso `tickets.vender_dia_sin_servicio`.
        // Los tramos sin fecha no tienen viaje y no se evalúan.
        if (filled($data['trip_id'] ?? null)) {
            $this->assertServiceDay(Trip::find($data['trip_id']), 'ida');
        }

        if ((bool) ($data['is_round_trip'] ?? false) && filled($data['return_trip_id'] ?? null)) {
            $this->assertServiceDay(Trip::find($data['return_trip_id']), 'vuelta');
        }

        // 0.f Una persona por venta y por viaje: sin DNI repetidos dentro de
        // la venta (adultos y menores) y sin DNI que ya tengan boleto en el
        // viaje de ida o de vuelta. Sin excepciones por permiso. Se repite
        // dentro de la transacción, con los viajes bloqueados.
        $this->assertDistinctSaleDnis($data);
        $this->assertNoPassengerAlreadyOnSaleTrips($data);

        try {
            // Emisión atómica: la venta, los pasajeros y los boletos de los dos
            // tramos se confirman juntos o no se crea nada. Los viajes quedan
            // bloqueados hasta el commit (las verificaciones con bloqueo de
            // Trip corren como savepoints de esta transacción).
            $firstTicket = DB::transaction(function () use ($data): Model {
                $this->lockSaleTrips($data);

                $conflicts = TripPassengerConflicts::find(
                    TripPassengerConflicts::saleDnis($data['passengers'] ?? []),
                    TripPassengerConflicts::saleTripIds($data),
                );

                if ($conflicts->isNotEmpty()) {
                    throw new DuplicatePassengerOnTripException($conflicts);
                }

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
                    // ?? false: con el parámetro "venta con acompañante" desactivado
                    // los switches no existen en el estado del pasajero.
                    if (($passengerData['travels_with_child'] ?? false) && isset($passengerData['child_data'])) {
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

                // 2.b Crear de una: los tramos vendidos sin fecha (permiso
                // `tickets.vender_sin_fecha`) NO resuelven viaje ni reservan asiento.
                // Quedan como boletos pendientes (trip_id NULL) listos para la
                // asignaciones posterior via tickets.reschedule.
                $sellWithoutDateIda = (bool) ($data['sell_without_date_ida'] ?? false);
                $sellWithoutDateVuelta = (bool) ($data['sell_without_date_vuelta'] ?? false);

                if ($sellWithoutDateIda || $sellWithoutDateVuelta) {
                    return $this->createPendingTickets($sale, $data, $adultPassengers, $sellWithoutDateIda, $sellWithoutDateVuelta);
                }

                // 3. Preparar datos de tickets con validación de asientos
                $trip = Trip::findOrFail($data['trip_id']);
                $seatIds = $data['seat_ids'] ?? [];
                if (! is_array($seatIds)) {
                    if (is_string($seatIds)) {
                        $seatIds = json_decode($seatIds, true) ?? [];
                    } else {
                        $seatIds = [];
                    }
                }

                // Validar y reservar asientos antes de crear tickets
                $reservationResult = $trip->reserveSeatsWithLock($seatIds);
                if (! $reservationResult['success']) {
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
                        ->body('Los siguientes asientos fueron vendidos mientras completaba el formulario: '.implode(', ', $seatNumbers).'. Por favor, seleccione otros asientos.')
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
                    $travelsWithPets = $passengerData['travels_with_pets'] ?? false;
                    $petNames = $passengerData['pet_data']['pet_names'] ?? null;
                    $petCount = $passengerData['pet_data']['pet_count'] ?? null;

                    $ticketsData[] = [
                        'sale_id' => $sale->id,
                        'trip_id' => $data['trip_id'],
                        'seat_id' => $seatId,
                        'passenger_id' => $passenger->id,
                        'is_round_trip' => $data['is_round_trip'] ?? false,
                        'is_return_leg' => false,
                        'return_trip_id' => $data['return_trip_id'] ?? null,
                        'travels_with_child' => $travelsWithChild,
                        'travels_with_pets' => $travelsWithPets,
                        'pet_names' => $petNames,
                        'pet_count' => $petCount,
                        'origin_location_id' => $data['origin_location_id'],
                        'destination_location_id' => $data['destination_location_id'],
                        'price' => $passengerData['price'] ?? 0,
                        'payment_method' => $passengerData['payment_method'] ?? null,
                    ];
                }

                $result = $trip->createTicketsWithLock($ticketsData);

                // 5. Crear tickets de VUELTA (solo para adultos) si aplica
                if ($data['is_round_trip'] ?? false) {
                    $returnTrip = Trip::findOrFail($data['return_trip_id']);
                    $returnSeatIds = $data['return_seat_ids'] ?? [];
                    if (! is_array($returnSeatIds)) {
                        if (is_string($returnSeatIds)) {
                            $returnSeatIds = json_decode($returnSeatIds, true) ?? [];
                        } else {
                            $returnSeatIds = [];
                        }
                    }

                    // Validar y reservar asientos de vuelta
                    $returnReservationResult = $returnTrip->reserveSeatsWithLock($returnSeatIds);
                    if (! $returnReservationResult['success']) {
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
                            ->body('Los siguientes asientos de vuelta fueron vendidos: '.implode(', ', $seatNumbers).'. Por favor, seleccione otros asientos.')
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
                        $travelsWithPets = $passengerData['travels_with_pets'] ?? false;
                        $petNames = $passengerData['pet_data']['pet_names'] ?? null;
                        $petCount = $passengerData['pet_data']['pet_count'] ?? null;

                        $returnTicketsData[] = [
                            'sale_id' => $sale->id,
                            'trip_id' => $data['return_trip_id'],
                            'seat_id' => $seatId,
                            'passenger_id' => $passenger->id,
                            'is_round_trip' => true,
                            'is_return_leg' => true,
                            'travels_with_child' => $travelsWithChild,
                            'travels_with_pets' => $travelsWithPets,
                            'pet_names' => $petNames,
                            'pet_count' => $petCount,
                            'origin_location_id' => $data['destination_location_id'],
                            'destination_location_id' => $data['origin_location_id'],
                            'price' => 0, // Return trip price is already captured on the outbound ticket
                            'payment_method' => $passengerData['payment_method'] ?? null,
                        ];
                    }

                    $returnResult = $returnTrip->createTicketsWithLock($returnTicketsData);
                }

                // 6. Recalcular el total de la venta
                $sale->recalculateTotal();

                return $result['tickets']->first();
            });
        } catch (DuplicatePassengerOnTripException $e) {
            // Sin liberar reservas: el vendedor vuelve a la venta con sus
            // asientos para corregir los datos del pasajero.
            DuplicateTicketNotification::send($e->conflicts(), 'Volver a la venta');

            $this->halt();

            return $data;
        } catch (\Exception $e) {
            $this->releaseSaleReservations($data);

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

        // Entrega, después del commit: un fallo al generar el PDF o el link
        // no anula lo emitido (generateAndDownloadTickets maneja sus errores).
        $this->releaseSaleReservations($data);
        $this->generateAndDownloadTickets($firstTicket->sale);

        return $firstTicket;
    }

    /**
     * Bloquea los viajes de la venta (ida y vuelta) en orden de id, para que
     * dos ventas de ida y vuelta cruzadas no se bloqueen mutuamente.
     */
    private function lockSaleTrips(array $data): void
    {
        $tripIds = collect([$data['trip_id'] ?? null, ($data['is_round_trip'] ?? false) ? ($data['return_trip_id'] ?? null) : null])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($tripIds->isNotEmpty()) {
            Trip::query()->whereIn('id', $tripIds)->orderBy('id')->lockForUpdate()->get();
        }
    }

    /**
     * Guard final: una venta no puede cargar el mismo DNI dos veces, sumando
     * pasajeros adultos y menores acompañantes.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertDistinctSaleDnis(array $data): void
    {
        $passengers = $data['passengers'] ?? [];
        $repeated = TripPassengerConflicts::repeatedDnis(
            array_column(TripPassengerConflicts::saleDniSlots(is_array($passengers) ? $passengers : []), 'dni'),
        );

        if ($repeated === []) {
            return;
        }

        Notification::make()
            ->title('No se puede completar la venta')
            ->icon('heroicon-m-identification')
            ->body('Hay DNI cargados en más de un pasajero de la venta: '.implode(', ', $repeated).'. Corregí los datos e intentá de nuevo.')
            ->danger()
            ->persistent()
            ->send();

        $this->halt();
    }

    /**
     * Guard final: ningún DNI de la venta (adulto o menor) puede tener ya un
     * boleto activo en el viaje de ida o en el de vuelta. Ningún permiso lo
     * exceptúa.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNoPassengerAlreadyOnSaleTrips(array $data): void
    {
        $conflicts = TripPassengerConflicts::find(
            TripPassengerConflicts::saleDnis($data['passengers'] ?? []),
            TripPassengerConflicts::saleTripIds($data),
        );

        if ($conflicts->isEmpty()) {
            return;
        }

        DuplicateTicketNotification::send($conflicts, 'Volver a la venta');

        $this->halt();
    }

    /**
     * Guard final de días de servicio (App\Support\ServiceDays): la fecha del
     * viaje del tramo tiene que tener servicio, salvo con el permiso
     * `tickets.vender_dia_sin_servicio`.
     */
    private function assertServiceDay(?Trip $trip, string $leg): void
    {
        $reason = ServiceDays::blockReason($trip?->trip_date);

        if ($reason === null) {
            return;
        }

        Notification::make()
            ->title('Día sin servicio')
            ->icon('heroicon-m-calendar-days')
            ->danger()
            ->persistent()
            ->body("Tramo de {$leg}: {$reason}")
            ->send();

        $this->halt();
    }

    /**
     * Guard final de venta fuera de término (reglas centralizadas en
     * App\Support\SaleCutoff, las mismas que aplican en el paso 1 del wizard):
     *
     *  1. Fecha de viaje anterior a hoy, y
     *  2. el colectivo salió de la parada donde SUBE el pasajero del tramo
     *     (origen en la ida, destino en la vuelta) hace más de las horas
     *     configuradas en Configuración (Setting::VENTA_LIMITE_HORAS),
     *
     * salvo que el usuario tenga el permiso `tickets.vender_pasado_limite`
     * (nadie lo trae por defecto: se habilita por rol desde la matriz).
     * 0 horas en Configuración = sin límite (feature desactivada).
     */
    private function assertDepartureWithinLimit(?Trip $trip, int|string|null $boardingLocationId, string $leg): void
    {
        $violation = SaleCutoff::blockReasonForTrip($trip, $boardingLocationId, $leg);

        if ($violation === null) {
            return;
        }

        Notification::make()
            ->title('Venta fuera de término')
            ->icon('heroicon-m-clock')
            ->danger()
            ->persistent()
            ->body($violation)
            ->send();

        $this->halt();
    }

    /**
     * Venta sin fecha (alguno o ambos tramos, permiso `tickets.vender_sin_fecha`).
     *
     * Crea los tickets SIN viaje ni asiento (trip_id/seat_id NULL): recién
     * cuando se les asigna fecha via tickets.reschedule se resuelve el viaje
     * y se exige el asiento.
     *
     * Modos:
     *  - Ambos tramos sin fecha: un ticket por pasajero (round trip) o dos
     *    (ida y vuelta separados), todos pendientes.
     *  - Sólo ida sin fecha: tickets sin viaje, is_round_trip=false.
     *  - Mixto (ida con fecha + vuelta sin fecha): ida normal (con viaje y
     *    asiento via createTicketsWithLock) + vuelta pendiente is_return_leg=true.
     */
    private function createPendingTickets(Sale $sale, array $data, Collection $adultPassengers, bool $idaSinFecha, bool $vueltaSinFecha): Model
    {
        $isRoundTrip = (bool) ($data['is_round_trip'] ?? false);

        // ---- Ida ----
        if ($idaSinFecha) {
            // Ida sin fecha: ticket pendiente (trip_id/seat_id NULL).
            foreach ($adultPassengers as $index => $passenger) {
                $passengerData = $data['passengers'][$index] ?? [];

                $sale->addTicket([
                    'trip_id' => null,
                    'seat_id' => null,
                    'passenger_id' => $passenger->id,
                    'is_round_trip' => $isRoundTrip,
                    'is_return_leg' => false,
                    // Modo mixto invertido (ida sin fecha + vuelta CON fecha):
                    // la vuelta ya resolvió su viaje al emitirse, así que la ida
                    // debe quedar ligada a él. Sin esto, el detalle/PDF no
                    // conocen la vuelta y la validación de orden de la
                    // reprogramación no puede comparar la ida contra ella.
                    'return_trip_id' => ($isRoundTrip && ! $vueltaSinFecha && filled($data['return_trip_id'] ?? null))
                        ? (int) $data['return_trip_id']
                        : null,
                    'bus_id' => $data['bus_id'], // Se exige arriba; acarrea el colectivo para la asignación posterior.
                    'travels_with_child' => $passengerData['travels_with_child'] ?? false,
                    'travels_with_pets' => $passengerData['travels_with_pets'] ?? false,
                    'pet_names' => $passengerData['pet_data']['pet_names'] ?? null,
                    'pet_count' => $passengerData['pet_data']['pet_count'] ?? null,
                    'origin_location_id' => $data['origin_location_id'],
                    'destination_location_id' => $data['destination_location_id'],
                    'price' => $passengerData['price'] ?? 0,
                    'payment_method' => $passengerData['payment_method'] ?? null,
                ]);
            }
        } else {
            // Ida CON fecha (modo mixto: sólo la vuelta va sin fecha) →
            // mismo flujo que la venta normal, pero sin viaje de vuelta
            // asignado todavia (return_trip_id NULL).
            $trip = Trip::findOrFail($data['trip_id']);
            $seatIds = $data['seat_ids'] ?? [];
            if (! is_array($seatIds)) {
                $seatIds = is_string($seatIds) ? (json_decode($seatIds, true) ?? []) : [];
            }

            $reservationResult = $trip->reserveSeatsWithLock($seatIds);
            if (! $reservationResult['success']) {
                $seatNumbers = [];
                foreach ($reservationResult['failed_seats'] as $seatId) {
                    $seat = \App\Models\Seat::find($seatId);
                    if ($seat) {
                        $seatNumbers[] = $seat->seat_number;
                    }
                }

                Notification::make()
                    ->title('Asientos de ida no disponibles')
                    ->icon('heroicon-m-exclamation-triangle')
                    ->body('Los asientos de ida ya fueron vendidos: '.implode(', ', $seatNumbers).'. Por favor, seleccione otros.')
                    ->warning()
                    ->persistent()
                    ->send();

                throw new \Exception('Algunos asientos seleccionados ya no están disponibles.');
            }

            $ticketsData = [];
            foreach ($adultPassengers as $index => $passenger) {
                $passengerData = $data['passengers'][$index] ?? [];

                $ticketsData[] = [
                    'sale_id' => $sale->id,
                    'trip_id' => $data['trip_id'],
                    'seat_id' => $seatIds[$index] ?? null,
                    'passenger_id' => $passenger->id,
                    'is_round_trip' => $isRoundTrip,
                    'return_trip_id' => null, // La vuelta aún no tiene viaje.
                    'is_return_leg' => false, // Ida explícita, aunque el precio viniera en 0.
                    'travels_with_child' => $passengerData['travels_with_child'] ?? false,
                    'travels_with_pets' => $passengerData['travels_with_pets'] ?? false,
                    'pet_names' => $passengerData['pet_data']['pet_names'] ?? null,
                    'pet_count' => $passengerData['pet_data']['pet_count'] ?? null,
                    'origin_location_id' => $data['origin_location_id'],
                    'destination_location_id' => $data['destination_location_id'],
                    'price' => $passengerData['price'] ?? 0,
                    'payment_method' => $passengerData['payment_method'] ?? null,
                ];
            }

            $result = $trip->createTicketsWithLock($ticketsData);
        }

        // ---- Vuelta ----
        if ($isRoundTrip) {
            if ($vueltaSinFecha) {
                // Tramo de vuelta pendiente: marca explícita is_return_leg.
                foreach ($adultPassengers as $index => $passenger) {
                    $passengerData = $data['passengers'][$index] ?? [];

                    $sale->addTicket([
                        'trip_id' => null,
                        'seat_id' => null,
                        'passenger_id' => $passenger->id,
                        'is_round_trip' => true,
                        'is_return_leg' => true,
                        'return_trip_id' => null,
                        'bus_id' => $data['bus_id'], // Se exige arriba; acarrea el colectivo para la asignación posterior.
                        'travels_with_child' => $passengerData['travels_with_child'] ?? false,
                        'travels_with_pets' => $passengerData['travels_with_pets'] ?? false,
                        'pet_names' => $passengerData['pet_data']['pet_names'] ?? null,
                        'pet_count' => $passengerData['pet_data']['pet_count'] ?? null,
                        'origin_location_id' => $data['destination_location_id'],
                        'destination_location_id' => $data['origin_location_id'],
                        'price' => 0, // El precio vive en el boleto de ida.
                        'payment_method' => $passengerData['payment_method'] ?? null,
                    ]);
                }
            } else {

                // Vuelta CON fecha: flujo normal (reserva + createTicketsWithLock).
                $returnTrip = Trip::findOrFail($data['return_trip_id']);
                $returnSeatIds = $data['return_seat_ids'] ?? [];
                if (! is_array($returnSeatIds)) {
                    if (is_string($returnSeatIds)) {
                        $returnSeatIds = json_decode($returnSeatIds, true) ?? [];
                    } else {
                        $returnSeatIds = [];
                    }
                }

                $returnReservationResult = $returnTrip->reserveSeatsWithLock($returnSeatIds);
                if (! $returnReservationResult['success']) {
                    Notification::make()
                        ->title('Asientos de vuelta no disponibles')
                        ->icon('heroicon-m-exclamation-triangle')
                        ->body('Los asientos de vuelta ya fueron vendidos. Por favor, seleccione otros.')
                        ->warning()
                        ->persistent()
                        ->send();

                    throw new \Exception('Algunos asientos de vuelta seleccionados ya no están disponibles.');
                }

                $returnTicketsData = [];
                foreach ($adultPassengers as $index => $passenger) {
                    $passengerData = $data['passengers'][$index] ?? [];

                    $returnTicketsData[] = [
                        'sale_id' => $sale->id,
                        'trip_id' => $data['return_trip_id'],
                        'seat_id' => $returnSeatIds[$index] ?? null,
                        'passenger_id' => $passenger->id,
                        'is_round_trip' => true,
                        'is_return_leg' => true,
                        'travels_with_child' => $passengerData['travels_with_child'] ?? false,
                        'travels_with_pets' => $passengerData['travels_with_pets'] ?? false,
                        'pet_names' => $passengerData['pet_data']['pet_names'] ?? null,
                        'pet_count' => $passengerData['pet_data']['pet_count'] ?? null,
                        'origin_location_id' => $data['destination_location_id'],
                        'destination_location_id' => $data['origin_location_id'],
                        'price' => 0, // El precio vive en el boleto de ida.
                        'payment_method' => $passengerData['payment_method'] ?? null,
                    ];
                }

                $returnResult = $returnTrip->createTicketsWithLock($returnTicketsData);
            }
        }

        // La liberación de reservas y la entrega (PDF o link) las hace
        // handleRecordCreation después del commit.
        $sale->recalculateTotal();

        return $sale->tickets()->oldest('id')->first();
    }

    /**
     * Generar y descargar PDFs para todos los pasajeros
     */
    private function generateAndDownloadTickets(Sale $sale): void
    {
        try {
            // Flujo "emisión por link": no se genera ni descarga ningún PDF
            // acá; se presenta la página de previsualización compartible y
            // el PDF se genera al vuelo cuando se consulta o se descarga.
            if (! Setting::descargaAutomaticaPdf()) {
                $this->finishSaleWithShareLink($sale);

                return;
            }

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
                $colectivo = str_replace(' ', '_', $trip?->bus?->name ?? 'Sin fecha');
                $filename = "Boleto_N°{$ticketId}_{$colectivo}.pdf";

                $data = [
                    'sale' => $sale,
                    'tickets' => $passengerTickets,
                    'passenger' => $passenger,
                    'hasChild' => $passengerTickets->contains('travels_with_child', true),
                ];

                // Generar PDF
                $dompdf = new \Dompdf\Dompdf;
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
                // Múltiples pasajeros - generar PDFs individuales y PDF combinado
                $downloads = [];
                $ticketNumbers = [];
                $allTickets = [];

                foreach ($ticketsByPassenger as $passengerId => $passengerTickets) {
                    $passenger = $passengerTickets->first()->passenger;
                    $trip = $passengerTickets->first()->trip;
                    $seat = $passengerTickets->first()->seat;

                    $passengerName = str_replace(' ', '_', $passenger->full_name);
                    $ticketId = $passengerTickets->first()->id;
                    $colectivo = str_replace(' ', '_', $trip?->bus?->name ?? 'Sin fecha');
                    $filename = "Boleto_N°{$ticketId}_{$colectivo}.pdf";

                    // Recolectar números de boletos para el nombre del archivo combinado
                    $ticketNumbers[] = $ticketId;

                    // Recolectar todos los tickets para el PDF combinado
                    $allTickets = array_merge($allTickets, $passengerTickets->toArray());

                    $data = [
                        'sale' => $sale,
                        'tickets' => $passengerTickets,
                        'passenger' => $passenger,
                        'hasChild' => $passengerTickets->contains('travels_with_child', true),
                    ];

                    // Generar PDF individual usando la vista existente
                    $dompdf = new \Dompdf\Dompdf;
                    $dompdf->loadHtml(view('tickets.pdf.passenger-tickets', $data)->render());
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    $pdfContent = $dompdf->output();

                    $downloads[] = [
                        'filename' => $filename,
                        'content' => base64_encode($pdfContent),
                    ];
                }

                // Generar PDF combinado usando todos los tickets juntos
                $combinedFilename = 'Boletos_N°'.implode('_', $ticketNumbers).'.pdf';
                $combinedContent = $this->generateCombinedPDF($sale, $ticketsByPassenger);

                // Agregar el PDF combinado a la lista de descargas
                $downloads[] = [
                    'filename' => $combinedFilename,
                    'content' => base64_encode($combinedContent),
                ];

                // Guardar todos los PDFs en sesión para descarga múltiple
                session(['ticket_pdfs_data' => $downloads]);

                // Mostrar notificación y redirigir a la página de descarga múltiple
                $passengerCount = count($downloads);
                $downloadUrl = route('tickets.download.multiple');
                Notification::make()
                    ->title('Boletos vendidos correctamente')
                    ->icon('heroicon-m-check-circle')
                    ->body("Se han creado {$passengerCount} boletos. Elige cómo descargarlos.")
                    ->success()
                    ->send();

                // Redirigir a la página de descarga múltiple
                session()->put('auto_download_url', $downloadUrl);
            }
        } catch (\Exception $e) {
            logger()->error('Error al generar PDFs de tickets', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
     * Generar PDF combinado con todos los tickets
     */
    private function generateCombinedPDF($sale, $ticketsByPassenger): string
    {
        // Todos los boletos juntos, con el MISMO maquetado de la vista individual:
        // pares por hoja (2 por página A4) y alturas reales de cada boleto.
        // El blad ya pinta la marca "FECHA MODIFICADA" por boleto, por lo que aqúi
        // no hace falta repetirla.
        $allTickets = $ticketsByPassenger->flatten();

        $data = [
            'sale' => $sale,
            'tickets' => $allTickets,
            'passenger' => $allTickets->first()->passenger,
            'hasChild' => $allTickets->contains('travels_with_child', true),
        ];

        $dompdf = new \Dompdf\Dompdf;
        $dompdf->loadHtml(view('tickets.pdf.passenger-tickets', $data)->render());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Cierre de venta con el modo "emisión por link"
     * (Setting::DESCARGA_AUTOMATICA_PDF desactivado).
     *
     * Link permanente para el boleto del pasajero único, o el PDF combinado
     * de la venta cuando hay varios pasajeros. Sustituye la auto-descarga:
     * se abre la página de previsualización (misma mecánica window.open de
     * siempre tras el create) y la notification suma los accesos de
     * compartir por WhatsApp y descargar el PDF.
     */
    private function finishSaleWithShareLink(Sale $sale): void
    {
        try {
            $ticketsByPassenger = $sale->tickets()->with('passenger')->get()->groupBy('passenger_id');

            // Un pasajero → link del boleto de ida (su PDF incluye la vuelta
            // si es diferido); varios pasajeros → link de la venta.
            $shareable = $ticketsByPassenger->count() === 1
                ? $sale->tickets()->where('is_return_leg', false)->oldest('id')->first() ?? $sale->tickets()->oldest('id')->first()
                : $sale;

            $sharePageUrl = $shareable->shareUrl();

            // Se abre la página de previsualización tras el create:
            // despuésCreate la abre en una pestaña nueva (window.open),
            // reutilizando la mecánica existente.
            session()->put('auto_download_url', $sharePageUrl);

            $subject = $shareable instanceof \App\Models\Ticket
                ? "Boleto N°{$shareable->id} para {$shareable->passenger?->full_name}."
                : "Venta N°{$sale->id} de {$ticketsByPassenger->count()} pasajeros.";

            Notification::make()
                ->icon('heroicon-m-check-circle')
                ->title('Boleto(s) vendido(s) correctamente')
                ->body("{$subject} El link se abrió en otra pestaña.")
                ->success()
                ->actions(
                    ShareLinkNotification::actionsFor(
                        $sale,
                        $sharePageUrl,
                        $shareable->sharePdfUrl('attachment'),
                    ),
                )
                ->send();
        } catch (\Throwable $e) {
            logger()->error('Error al armar el link compartible de la venta', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Boleto(s) vendido(s) correctamente')
                ->icon('heroicon-m-exclamation-triangle')
                ->body('La venta se completó, pero falló al preparar el link del boleto. Podes descargar el PDF desde la vista del boleto.')
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
                ".implode('', array_map(function ($download) {
            return "<div class='file'>
                        <strong>{$download['filename']}</strong>
                        <br><a href='data:application/pdf;base64,{$download['content']}' 
                               download='{$download['filename']}' 
                               style='color: #007bff; text-decoration: none;'>
                            📥 Descargar
                        </a>
                    </div>";
        }, $downloads)).'
            </div>
            <script>
                // Descargar automáticamente cada archivo
                setTimeout(function() {
                    '.implode('', array_map(function ($download) {
            $filename = str_replace([' ', '-'], ['_', '_'], $download['filename']);

            return "
                    const link{$filename} = document.createElement('a');
                    link{$filename}.href = 'data:application/pdf;base64,{$download['content']}';
                    link{$filename}.download = '{$download['filename']}';
                    link{$filename}.click();";
        }, $downloads)).'
                }, 1000);
                
                // Cerrar la ventana después de 5 segundos
                setTimeout(function() {
                    window.close();
                }, 5000);
            </script>
        </body>
        </html>';
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

            if (! $result['trip']) {
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
                ->body('Error al buscar viaje: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function handleSeatReservationConflict($data): void
    {
        try {
            $message = $data['message'] ?? 'Conflicto de reservación detectado';
            $occupiedSeats = $data['occupied_seats'] ?? [];
            $reservedSeats = $data['reserved_by_others'] ?? [];
            $invalidSeats = $data['invalid_seats'] ?? [];

            // Mostrar notificación específica del conflicto
            Notification::make()
                ->icon('heroicon-m-exclamation-triangle')
                ->title('Conflicto de reservación')
                ->body($message.'. Por favor, seleccione otros asientos.')
                ->warning()
                /* ->persistent() */
                ->send();

            // Limpiar asientos no válidos de la selección actual
            $currentSeatIds = $this->data['seat_ids'] ?? [];
            if (! is_array($currentSeatIds)) {
                if (is_string($currentSeatIds)) {
                    $currentSeatIds = json_decode($currentSeatIds, true) ?? [];
                } else {
                    $currentSeatIds = [];
                }
            }

            $allInvalidSeats = array_merge($occupiedSeats, $reservedSeats, $invalidSeats);
            $validSeats = array_diff($currentSeatIds, $allInvalidSeats);

            // Actualizar solo los asientos válidos en el formulario (refresco silencioso)
            $tripId = $this->data['trip_id'] ?? null;
            if ($tripId) {
                $trip = Trip::find($tripId);
                if ($trip) {
                    $availableSeats = $trip->remainingSeats();
                    $passengersCount = (int) ($this->data['passengers_count'] ?? 1);

                    // Actualizar estado sin mostrar notificaciones adicionales
                    $this->form->fill(array_merge($this->data ?? [], [
                        'trip_available_seats' => $availableSeats,
                        'trip_search_status' => $availableSeats >= $passengersCount ? 'available' : 'insufficient_seats',
                        'seat_ids' => array_values($validSeats),
                        // IMPORTANTE: Limpiar datos de pasos posteriores para evitar notificaciones de expiración
                        'passenger_data' => [],
                        'return_seat_ids' => [],
                        'return_passenger_data' => [],
                        // Bandera para evitar notificación de expiración cuando fue conflicto
                        'skip_expiration_notification' => true,
                    ]));

                    // Forzar actualización del estado de Livewire para que el componente se refresque
                    $this->dispatch('refresh-seats');
                }
            }
        } catch (\Exception $e) {
            Log::error('Error en handleSeatReservationConflict', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            Notification::make()
                ->icon('heroicon-m-x-circle')
                ->title('Error al manejar conflicto')
                ->body('Ocurrió un error al procesar el conflicto de reservación. Por favor, recargue la página.')
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
                    if (! is_array($currentSeatIds)) {
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
                    if (! empty($removedSeats)) {
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
                            ->body('Los siguientes asientos fueron vendidos: '.implode(', ', $removedSeatNumbers).'. Su selección ha sido actualizada.')
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
                    if (! is_array($currentSeatIds)) {
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
                    if (! empty($removedSeats)) {
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
                            ->body('Los siguientes asientos de vuelta fueron vendidos: '.implode(', ', $removedSeatNumbers).'. Su selección ha sido actualizada.')
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
                ->body('No se pudo actualizar la disponibilidad de asientos: '.$e->getMessage())
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

            if (! $result['trip']) {
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
                ->body('Error al buscar viaje de vuelta: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Viajes de esta venta con reservas posibles: los actuales de cada tramo y
     * aquellos en los que se eligieron los asientos (por si el viaje cambió).
     *
     * @return array<int>
     */
    public function saleTripIds(?array $data = null): array
    {
        $data = $data ?? $this->data ?? [];

        return collect([
            $data['trip_id'] ?? null,
            $data['return_trip_id'] ?? null,
            $data['reserved_trip_id'] ?? null,
            $data['reserved_return_trip_id'] ?? null,
        ])
            ->filter(fn ($tripId) => filled($tripId))
            ->map(fn ($tripId) => (int) $tripId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Libera las reservas de la sesión solo en los viajes de esta venta: las
     * de otras ventas en curso de la misma sesión (otras pestañas) se conservan.
     *
     * @param  array<string, mixed>|null  $data  Datos de la emisión; sin ellos, el estado del formulario.
     */
    public function releaseSaleReservations(?array $data = null): void
    {
        $tripIds = $this->saleTripIds($data);

        if ($tripIds === []) {
            return;
        }

        SeatReservation::releaseBySession(session()->getId(), $tripIds);
    }

    /**
     * Método llamado desde el frontend cuando una reserva expira
     */
    public function notifyReservationExpired(): void
    {
        Notification::make()
            ->title('Asientos expirados')
            ->icon('heroicon-m-clock')
            ->body('Los asientos han sido liberados. Por favor, seleccionalos nuevamente si aún los necesitas.')
            ->persistent()
            ->warning()
            ->send();
    }
}
