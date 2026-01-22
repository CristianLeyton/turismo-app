<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\SeatReservation;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Carbon\Carbon;
use Filament\Forms\Components\Repeater;
use App\Models\Trip;
use App\Models\Seat;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Button;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\Column;
use Illuminate\Support\Facades\Blade;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Wizard::make([
                Step::make('Buscar viaje')
                    ->afterValidation(function (Get $get, Set $set) {
                        // Verificar automáticamente el viaje de ida si no se ha verificado
                        $tripId = $get('trip_id');
                        $tripSearchStatus = $get('trip_search_status');

                        if (blank($tripId) || $tripSearchStatus !== 'available') {
                            // Intentar verificar el viaje de ida automáticamente
                            $originId = $get('origin_location_id');
                            $destinationId = $get('destination_location_id');
                            $scheduleId = $get('schedule_id');
                            $departureDate = $get('departure_date');
                            $passengersCount = $get('passengers_count');

                            if (!blank($originId) && !blank($destinationId) && !blank($scheduleId) && !blank($departureDate) && !blank($passengersCount)) {
                                // Implementar lógica de búsqueda directamente
                                try {
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

                                    if ($result['trip']) {
                                        $trip = $result['trip'];
                                        $requiredSeats = (int) $passengersCount;
                                        $availableSeats = $result['available_seats'];

                                        // Validar disponibilidad de asientos
                                        if ($availableSeats >= $requiredSeats) {
                                            // Viaje encontrado con suficientes asientos
                                            $set('trip_id', $trip->id);
                                            $set('trip_search_status', 'available');
                                            $set('trip_available_seats', $availableSeats);

                                            // Notificar éxito
                                            /*                                             Notification::make()
                                                ->title('Viaje de ida disponible')
                                                ->icon('heroicon-m-check-circle')
                                                ->body("Viaje de ida verificado. Asientos disponibles: {$availableSeats}")
                                                ->success()
                                                ->send(); */
                                        } else {
                                            // Asientos insuficientes
                                            Notification::make()
                                                ->title('Asientos insuficientes')
                                                ->icon('heroicon-m-exclamation-triangle')
                                                ->body("El viaje tiene {$availableSeats} asiento(s) disponible(s), pero necesita {$requiredSeats}.")
                                                ->warning()
                                                ->send();

                                            $set('trip_id', $trip->id);
                                            $set('trip_search_status', 'insufficient_seats');
                                            $set('trip_available_seats', $availableSeats);
                                        }
                                    } else {
                                        // Error al buscar viaje
                                        Notification::make()
                                            ->title('Error al buscar viaje')
                                            ->icon('heroicon-m-x-circle')
                                            ->body($result['message'])
                                            ->danger()
                                            ->send();

                                        $set('trip_id', null);
                                    }
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('Error')
                                        ->icon('heroicon-m-x-circle')
                                        ->body('Error al buscar viaje: ' . $e->getMessage())
                                        ->danger()
                                        ->send();
                                }

                                // Obtener los valores actualizados
                                $tripId = $get('trip_id');
                                $tripSearchStatus = $get('trip_search_status');
                            }
                        }

                        // Validar que se haya seleccionado un viaje de ida
                        if (blank($tripId)) {
                            Notification::make()
                                ->title('Viaje de ida requerido')
                                ->body('Debe buscar un viaje de ida antes de continuar.')
                                ->warning()
                                ->send();
                            throw new Halt();
                        }

                        // Validar que el viaje tenga estado 'available'
                        if ($tripSearchStatus !== 'available') {
                            Notification::make()
                                ->icon('heroicon-m-exclamation-triangle')
                                ->title('Viaje de ida no disponible')
                                ->body('Debe buscar un viaje de ida disponible antes de continuar.')
                                ->warning()
                                ->send();
                            throw new Halt();
                        }

                        // Si está marcado como viaje de ida y vuelta, verificar automáticamente el viaje de vuelta
                        $isRoundTrip = $get('is_round_trip');
                        if ($isRoundTrip) {
                            $returnTripId = $get('return_trip_id');
                            $returnTripSearchStatus = $get('return_trip_search_status');

                            if (blank($returnTripId) || $returnTripSearchStatus !== 'available') {
                                // Intentar verificar el viaje de vuelta automáticamente
                                $returnDate = $get('return_date');
                                $returnScheduleId = $get('return_schedule_id');
                                $originId = $get('origin_location_id');
                                $destinationId = $get('destination_location_id');
                                $passengersCount = $get('passengers_count');

                                if (!blank($returnDate) && !blank($returnScheduleId)) {
                                    // Implementar lógica de búsqueda de vuelta directamente
                                    try {
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

                                        if ($result['trip']) {
                                            $trip = $result['trip'];
                                            $requiredSeats = (int) $passengersCount;
                                            $availableSeats = $result['available_seats'];

                                            // Validar disponibilidad de asientos
                                            if ($availableSeats >= $requiredSeats) {
                                                // Viaje de vuelta encontrado con suficientes asientos
                                                $set('return_trip_id', $trip->id);
                                                $set('return_trip_search_status', 'available');
                                                $set('return_trip_available_seats', $availableSeats);

                                                // Notificar éxito
                                                /*                                                Notification::make()
                                                    ->title('Viaje de vuelta verificado')
                                                    ->icon('heroicon-m-check-circle')
                                                    ->body("Viaje de vuelta disponible. Asientos disponibles: {$availableSeats}")
                                                    ->success()
                                                    ->send(); */
                                            } else {
                                                // Asientos insuficientes para vuelta
                                                $set('return_trip_id', $trip->id);
                                                $set('return_trip_search_status', 'insufficient_seats');
                                                $set('return_trip_available_seats', $availableSeats);

                                                // Notificar asientos insuficientes
                                                Notification::make()
                                                    ->title('Asientos insuficientes para vuelta')
                                                    ->icon('heroicon-m-exclamation-triangle')
                                                    ->body("El viaje de vuelta tiene {$availableSeats} asiento(s) disponible(s), pero necesita {$requiredSeats}.")
                                                    ->warning()
                                                    ->send();
                                            }
                                        } else {
                                            // Error al buscar viaje de vuelta
                                            Notification::make()
                                                ->title('Error al buscar viaje de vuelta')
                                                ->icon('heroicon-m-x-circle')
                                                ->body($result['message'])
                                                ->danger()
                                                ->send();

                                            $set('return_trip_id', null);
                                        }
                                    } catch (\Exception $e) {
                                        Notification::make()
                                            ->title('Error')
                                            ->icon('heroicon-m-x-circle')
                                            ->body('Error al buscar viaje de vuelta: ' . $e->getMessage())
                                            ->danger()
                                            ->send();
                                    }

                                    // Obtener los valores actualizados
                                    $returnTripId = $get('return_trip_id');
                                    $returnTripSearchStatus = $get('return_trip_search_status');
                                }
                            }

                            // Validar que se haya seleccionado un viaje de vuelta
                            if (blank($returnTripId)) {
                                Notification::make()
                                    ->title('Viaje de vuelta requerido')
                                    ->body('Debe buscar un viaje de vuelta antes de continuar.')
                                    ->warning()
                                    ->send();
                                throw new Halt();
                            }

                            // Validar que el viaje de vuelta tenga estado 'available'
                            if ($returnTripSearchStatus !== 'available') {
                                Notification::make()
                                    ->title('Viaje de vuelta no disponible')
                                    ->body('Debe buscar un viaje de vuelta disponible antes de continuar.')
                                    ->warning()
                                    ->send();
                                throw new Halt();
                            }
                        }
                    })
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('origin_location_id')
                                    ->label('Origen')
                                    ->relationship(
                                        name: 'origin',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn($query) =>
                                        $query
                                            ->where('is_active', true)
                                            ->orderBy('id')
                                    )
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn($set) => [
                                        $set('destination_location_id', null),
                                        $set('schedule_id', null),
                                        $set('trip_id', null),
                                        $set('trip_search_status', null),
                                        $set('trip_available_seats', null),
                                        $set('return_trip_id', null),
                                        $set('return_trip_search_status', null),
                                        $set('return_trip_available_seats', null),
                                        $set('is_round_trip', false),
                                    ])
                                    ->validationMessages([
                                        'required' => 'Seleccione un origen',
                                    ]),

                                Select::make('destination_location_id')
                                    ->label('Destino')
                                    ->required()
                                    ->disabled(fn(Get $get) => blank($get('origin_location_id')))
                                    ->placeholder('Seleccione un origen primero')
                                    ->options(function (Get $get) {

                                        $originId = $get('origin_location_id');

                                        if (blank($originId)) {
                                            return [];
                                        }

                                        return RouteStop::query()
                                            ->withoutGlobalScopes()
                                            ->from('route_stops as destination')
                                            ->join('route_stops as origin', function ($join) use ($originId) {
                                                $join->on('destination.route_id', '=', 'origin.route_id')
                                                    ->where('origin.location_id', $originId);
                                            })

                                            // 🔒 SOLO paradas POSTERIORES
                                            ->whereColumn('destination.stop_order', '>', 'origin.stop_order')

                                            // evitar el origen
                                            ->where('destination.location_id', '!=', $originId)

                                            ->join('locations', 'locations.id', '=', 'destination.location_id')
                                            ->orderBy('destination.stop_order')

                                            ->get([
                                                'destination.location_id',
                                                'locations.name',
                                            ])

                                            ->mapWithKeys(fn($row) => [
                                                (int) $row->location_id => (string) $row->name,
                                            ])
                                            ->toArray();
                                    })
                                    ->live()
                                    ->afterStateUpdated(fn($set) => [
                                        $set('schedule_id', null),
                                        $set('trip_id', null),
                                        $set('trip_search_status', null),
                                        $set('trip_available_seats', null),
                                        $set('return_trip_id', null),
                                        $set('return_trip_search_status', null),
                                        $set('return_trip_available_seats', null),
                                        $set('is_round_trip', false),
                                    ])
                                    ->validationMessages([
                                        'required' => 'Seleccione un destino',
                                    ]),
                                DatePicker::make('departure_date')
                                    ->label('Fecha de ida')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->minDate(now()->startOfDay())
                                    ->closeOnDateSelection()
                                    ->disabledDates(function (): array {
                                        // Generar array de fechas de sábados y domingos para los próximos 2 años
                                        $disabledDates = [];
                                        $start = Carbon::today();
                                        $end = Carbon::today()->addYears(2);

                                        while ($start->lte($end)) {
                                            // 0 = Domingo, 6 = Sábado
                                            if ($start->dayOfWeek === 0 || $start->dayOfWeek === 6) {
                                                $disabledDates[] = $start->copy()->format('Y-m-d');
                                            }
                                            $start->addDay();
                                        }

                                        return $disabledDates;
                                    })
                                    /* ->helperText('Solo se permiten días laborables (lunes a viernes)') */
                                    ->live()
                                    ->afterStateUpdated(fn($set) => [
                                        $set('schedule_id', null), // Limpiar horario al cambiar fecha para revalidar horarios disponibles
                                        $set('trip_id', null),
                                        $set('trip_search_status', null),
                                        $set('trip_available_seats', null),
                                        $set('return_trip_id', null),
                                        $set('return_trip_search_status', null),
                                        $set('return_trip_available_seats', null),
                                        $set('is_round_trip', false),
                                    ])
                                    ->validationMessages([
                                        'required' => 'Seleccione una fecha de ida',
                                    ]),
                                Select::make('schedule_id')
                                    ->label('Horario de ida')
                                    ->required()
                                    ->disabled(fn(Get $get) => blank($get('origin_location_id')) || blank($get('destination_location_id')) || blank($get('departure_date')))
                                    ->placeholder(function (Get $get) {
                                        if (blank($get('origin_location_id'))) {
                                            return 'Seleccione un origen primero';
                                        }
                                        if (blank($get('destination_location_id'))) {
                                            return 'Seleccione un destino primero';
                                        }
                                        if (blank($get('departure_date'))) {
                                            return 'Seleccione una fecha primero';
                                        }

                                        $departureDate = $get('departure_date');
                                        $now = Carbon::now();

                                        // Verificar si hay horarios disponibles
                                        $schedules = Schedule::query()
                                            ->whereHas('route.stops', fn($q) => $q->where('location_id', $get('origin_location_id')))
                                            ->whereHas('route.stops', fn($q) => $q->where('location_id', $get('destination_location_id')))
                                            ->where('is_active', true)
                                            ->get()
                                            ->filter(function ($schedule) use ($get, $departureDate, $now) {
                                                if (
                                                    !$schedule->route->isValidSegment(
                                                        $get('origin_location_id'),
                                                        $get('destination_location_id')
                                                    )
                                                ) {
                                                    return false;
                                                }

                                                // Si hay una fecha de salida seleccionada, validar si ya pasó la hora
                                                if ($departureDate) {
                                                    $departure = is_string($departureDate)
                                                        ? Carbon::parse($departureDate)
                                                        : $departureDate;

                                                    // Si la fecha de salida es hoy, verificar que la hora no haya pasado
                                                    if ($departure->format('Y-m-d') === $now->format('Y-m-d')) {
                                                        $scheduleTime = Carbon::parse($schedule->departure_time)->format('H:i:s');
                                                        $currentTime = $now->format('H:i:s');

                                                        // Si la hora de salida ya pasó, no incluir este horario
                                                        if ($scheduleTime <= $currentTime) {
                                                            return false;
                                                        }
                                                    }
                                                }

                                                return true;
                                            });

                                        if ($schedules->isEmpty()) {
                                            // Verificar si es porque ya pasaron las horas o porque no hay horarios
                                            if ($departureDate) {
                                                $departure = is_string($departureDate)
                                                    ? Carbon::parse($departureDate)
                                                    : $departureDate;

                                                if ($departure->format('Y-m-d') === $now->format('Y-m-d')) {
                                                    return 'No hay horarios disponibles (ya pasaron las horas de salida)';
                                                }
                                            }
                                            return 'No hay horarios disponibles para esta ruta';
                                        }

                                        return 'Seleccione un horario';
                                    })
                                    ->options(function (Get $get) {
                                        if (blank($get('origin_location_id')) || blank($get('destination_location_id')) || blank($get('departure_date'))) {
                                            return [];
                                        }

                                        $departureDate = $get('departure_date');
                                        $now = Carbon::now();

                                        $schedules = Schedule::query()
                                            ->whereHas(
                                                'route.stops',
                                                fn($q) => $q->where('location_id', $get('origin_location_id'))
                                            )
                                            ->whereHas(
                                                'route.stops',
                                                fn($q) => $q->where('location_id', $get('destination_location_id'))
                                            )
                                            ->where('is_active', true)
                                            ->orderBy('departure_time') // Ordenar por hora de salida
                                            ->get()
                                            ->filter(function ($schedule) use ($get, $departureDate, $now) {
                                                // Validar que el segmento origen-destino sea válido para esta ruta
                                                if (
                                                    !$schedule->route->isValidSegment(
                                                        $get('origin_location_id'),
                                                        $get('destination_location_id')
                                                    )
                                                ) {
                                                    return false;
                                                }

                                                // Si hay una fecha de salida seleccionada, validar si ya pasó la hora
                                                if ($departureDate) {
                                                    $departure = is_string($departureDate)
                                                        ? Carbon::parse($departureDate)
                                                        : $departureDate;

                                                    // Si la fecha de salida es hoy, verificar que la hora no haya pasado
                                                    if ($departure->format('Y-m-d') === $now->format('Y-m-d')) {
                                                        $scheduleTime = Carbon::parse($schedule->departure_time)->format('H:i:s');
                                                        $currentTime = $now->format('H:i:s');

                                                        // Si la hora de salida ya pasó, no incluir este horario
                                                        if ($scheduleTime <= $currentTime) {
                                                            return false;
                                                        }
                                                    }
                                                }

                                                return true;
                                            });

                                        if ($schedules->isEmpty()) {
                                            return [];
                                        }

                                        return $schedules->mapWithKeys(fn($schedule) => [
                                            $schedule->id => $schedule->display_name,
                                        ]);
                                    })
                                    ->helperText(function (Get $get) {
                                        if (blank($get('origin_location_id')) || blank($get('destination_location_id')) || blank($get('departure_date'))) {
                                            return null;
                                        }

                                        $departureDate = $get('departure_date');
                                        $now = Carbon::now();

                                        $schedules = Schedule::query()
                                            ->whereHas('route.stops', fn($q) => $q->where('location_id', $get('origin_location_id')))
                                            ->whereHas('route.stops', fn($q) => $q->where('location_id', $get('destination_location_id')))
                                            ->where('is_active', true)
                                            ->get()
                                            ->filter(function ($schedule) use ($get, $departureDate, $now) {
                                                if (
                                                    !$schedule->route->isValidSegment(
                                                        $get('origin_location_id'),
                                                        $get('destination_location_id')
                                                    )
                                                ) {
                                                    return false;
                                                }

                                                // Si hay una fecha de salida seleccionada, validar si ya pasó la hora
                                                if ($departureDate) {
                                                    $departure = is_string($departureDate)
                                                        ? Carbon::parse($departureDate)
                                                        : $departureDate;

                                                    // Si la fecha de salida es hoy, verificar que la hora no haya pasado
                                                    if ($departure->format('Y-m-d') === $now->format('Y-m-d')) {
                                                        $scheduleTime = Carbon::parse($schedule->departure_time)->format('H:i:s');
                                                        $currentTime = $now->format('H:i:s');

                                                        // Si la hora de salida ya pasó, no incluir este horario
                                                        if ($scheduleTime <= $currentTime) {
                                                            return false;
                                                        }
                                                    }
                                                }

                                                return true;
                                            });

                                        if ($schedules->isEmpty()) {
                                            // Verificar si es porque ya pasaron las horas o porque no hay horarios
                                            if ($departureDate) {
                                                $departure = is_string($departureDate)
                                                    ? Carbon::parse($departureDate)
                                                    : $departureDate;

                                                if ($departure->format('Y-m-d') === $now->format('Y-m-d')) {
                                                    return 'No hay horarios disponibles para hoy (ya pasaron las horas de salida). Por favor, seleccione otra fecha.';
                                                }
                                            }
                                            return 'No hay horarios disponibles para esta ruta. Por favor, seleccione otra combinación de origen y destino.';
                                        }

                                        return null;
                                    })
                                    ->live()
                                    ->afterStateUpdated(fn($set) => [
                                        $set('trip_id', null),
                                        $set('trip_search_status', null),
                                        $set('trip_available_seats', null),
                                        $set('return_schedule_id', null), // Limpiar horario de vuelta si cambia el de ida
                                        $set('return_trip_id', null),
                                        $set('return_trip_search_status', null),
                                        $set('return_trip_available_seats', null),
                                        $set('is_round_trip', false),
                                    ])
                                    ->validationMessages([
                                        'required' => 'Seleccione un horario',
                                    ]),

                                Select::make('passengers_count')
                                    ->label('Cantidad de pasajeros')
                                    ->required()
                                    ->default(1)
                                    ->live()
                                    ->options([
                                        '1' => '1',
                                        '2' => '2',
                                        '3' => '3',
                                        '4' => '4',
                                        '5' => '5',
                                        '6' => '6',
                                        '7' => '7',
                                        '8' => '8',
                                    ])
                                    ->selectablePlaceholder(false)
                                    ->rule('in:1,2,3,4,5,6,7,8')
                                    ->validationMessages([
                                        'required' => 'Ingrese la cantidad de pasajeros',
                                        'in' => 'La cantidad de pasajeros debe ser entre 1 y 8',
                                    ])
                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {

                                        // ---- Reset de búsqueda de viaje ----
                                        $set('trip_id', null);
                                        $set('trip_search_status', null);
                                        $set('trip_available_seats', null);
                                        $set('return_trip_id', null);
                                        $set('return_trip_search_status', null);
                                        $set('return_trip_available_seats', null);
                                        $set('is_round_trip', false);

                                        // ---- Ajustar array de pasajeros ----
                                        $required = (int) $state;
                                        $current = $get('passengers') ?? [];

                                        // Recortar si sobran
                                        if (count($current) > $required) {
                                            $current = array_slice($current, 0, $required);
                                        }

                                        // Expandir si faltan
                                        if (count($current) < $required) {
                                            for ($i = count($current); $i < $required; $i++) {
                                                $current[$i] = [
                                                    'passenger_number' => $i + 1,
                                                ];
                                            }
                                        }

                                        $set('passengers', $current);
                                    }),
                            ]),



                        // Campos Hidden para almacenar el estado de la búsqueda
                        Hidden::make('trip_id')
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('seat_ids', []);
                            })
                            ->live(),
                        Hidden::make('trip_search_status')
                            ->live(),
                        Hidden::make('trip_available_seats')
                            ->live(),
                        Hidden::make('return_schedule_id')
                            ->live(),
                        Hidden::make('return_trip_id')
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('return_seat_ids', []);
                            })
                            ->live(),
                        Hidden::make('return_trip_search_status')
                            ->live(),
                        Hidden::make('return_trip_available_seats')
                            ->live(),

                        // Información del viaje y botón de búsqueda
                        /* ViewField::make('search_trip_section')
                            ->label('')
                            ->view('tickets.search-trip-section')
                            ->viewData(function (Get $get) {
                                $hasAllFields = !blank($get('origin_location_id')) &&
                                    !blank($get('destination_location_id')) &&
                                    !blank($get('schedule_id')) &&
                                    !blank($get('departure_date')) &&
                                    !blank($get('passengers_count'));

                                $tripId = $get('trip_id');
                                $searchStatus = $get('trip_search_status');
                                $availableSeats = $get('trip_available_seats');
                                $requiredSeats = (int) $get('passengers_count');

                                $trip = null;
                                if ($tripId) {
                                    $trip = Trip::find($tripId);
                                }

                                return [
                                    'hasAllFields' => $hasAllFields,
                                    'tripId' => $tripId,
                                    'trip' => $trip,
                                    'searchStatus' => $searchStatus,
                                    'availableSeats' => $availableSeats,
                                    'requiredSeats' => $requiredSeats,
                                ];
                            })
                            ->visible(fn(Get $get) => !blank($get('schedule_id')) && !blank($get('departure_date'))), */

                        ViewField::make('search_trip_button')
                            ->label('')
                            ->view('tickets.search-trip-button')
                            ->visible(false),

                        Toggle::make('is_round_trip')
                            ->label('Diferido')
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {

                                if (!$state) {
                                    $set('return_date', null);
                                    $set('return_schedule_id', null);
                                    $set('return_trip_id', null);
                                    $set('return_trip_search_status', null);
                                    $set('return_trip_available_seats', null);
                                    return;
                                }

                                // Cuando se activa el diferido, primero verificar si hay viaje de ida
                                $tripId = $get('trip_id');
                                $tripSearchStatus = $get('trip_search_status');

                                // Si no hay viaje de ida disponible, buscarlo automáticamente
                                if (blank($tripId) || $tripSearchStatus !== 'available') {
                                    $originId = $get('origin_location_id');
                                    $destinationId = $get('destination_location_id');
                                    $scheduleId = $get('schedule_id');
                                    $departureDate = $get('departure_date');
                                    $passengersCount = $get('passengers_count');

                                    if (!blank($originId) && !blank($destinationId) && !blank($scheduleId) && !blank($departureDate) && !blank($passengersCount)) {
                                        // Buscar viaje de ida
                                        try {
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

                                            if ($result['trip']) {
                                                $trip = $result['trip'];
                                                $requiredSeats = (int) $passengersCount;
                                                $availableSeats = $result['available_seats'];

                                                // Validar disponibilidad de asientos
                                                if ($availableSeats >= $requiredSeats) {
                                                    // Viaje encontrado con suficientes asientos
                                                    $set('trip_id', $trip->id);
                                                    $set('trip_search_status', 'available');
                                                    $set('trip_available_seats', $availableSeats);

                                                    // Notificar éxito
                                                    /* Notification::make()
                                                        ->title('Viaje de ida disponible')
                                                        ->icon('heroicon-m-check-circle')
                                                        ->body("Viaje de ida verificado. Asientos disponibles: {$availableSeats}")
                                                        ->success()
                                                        ->send(); */
                                                } else {
                                                    // Asientos insuficientes
                                                    Notification::make()
                                                        ->title('Asientos insuficientes')
                                                        ->icon('heroicon-m-exclamation-triangle')
                                                        ->body("El viaje tiene {$availableSeats} asiento(s) disponible(s), pero necesita {$requiredSeats}.")
                                                        ->warning()
                                                        ->send();

                                                    // Apagar el checkbox y limpiar
                                                    $set('is_round_trip', false);
                                                    return;
                                                }
                                            } else {
                                                // Error al buscar viaje
                                                Notification::make()
                                                    ->title('No se encontró viaje de ida')
                                                    ->icon('heroicon-m-x-circle')
                                                    ->body($result['message'])
                                                    ->danger()
                                                    ->send();

                                                // Apagar el checkbox
                                                $set('is_round_trip', false);
                                                return;
                                            }
                                        } catch (\Exception $e) {
                                            Notification::make()
                                                ->title('Error al buscar viaje de ida')
                                                ->icon('heroicon-m-x-circle')
                                                ->body('Error al buscar viaje: ' . $e->getMessage())
                                                ->danger()
                                                ->send();

                                            // Apagar el checkbox
                                            $set('is_round_trip', false);
                                            return;
                                        }
                                    } else {
                                        // No hay datos completos para buscar viaje de ida
                                        Notification::make()
                                            ->title('Datos incompletos')
                                            ->icon('heroicon-m-exclamation-triangle')
                                            ->body('Complete todos los campos del viaje de ida antes de activar Diferido.')
                                            ->warning()
                                            ->send();

                                        // Apagar el checkbox
                                        $set('is_round_trip', false);
                                        return;
                                    }
                                }

                                // Ahora que tenemos viaje de ida, verificar la ruta de vuelta
                                $route = Route::query()
                                    ->whereHas(
                                        'stops',
                                        fn($q) =>
                                        $q->where('location_id', $get('destination_location_id'))
                                    )
                                    ->whereHas(
                                        'stops',
                                        fn($q) =>
                                        $q->where('location_id', $get('origin_location_id'))
                                    )
                                    ->first();

                                if (!$route) {
                                    Notification::make()
                                        ->title('Ruta de vuelta inexistente')
                                        ->danger()
                                        ->send();

                                    $set('is_round_trip', false);
                                } else {
                                    // Verificar si hay datos para buscar el viaje de vuelta automáticamente
                                    $returnDate = $get('return_date');
                                    $returnScheduleId = $get('return_schedule_id');
                                    $originId = $get('origin_location_id');
                                    $destinationId = $get('destination_location_id');
                                    $passengersCount = $get('passengers_count');

                                    if (!blank($returnDate) && !blank($returnScheduleId)) {
                                        // Implementar lógica de búsqueda de vuelta directamente
                                        try {
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

                                            if ($result['trip']) {
                                                $trip = $result['trip'];
                                                $requiredSeats = (int) $passengersCount;
                                                $availableSeats = $result['available_seats'];

                                                // Validar disponibilidad de asientos
                                                if ($availableSeats >= $requiredSeats) {
                                                    // Viaje de vuelta encontrado con suficientes asientos
                                                    $set('return_trip_id', $trip->id);
                                                    $set('return_trip_search_status', 'available');
                                                    $set('return_trip_available_seats', $availableSeats);
                                                } else {
                                                    // Asientos insuficientes para vuelta
                                                    $set('return_trip_id', $trip->id);
                                                    $set('return_trip_search_status', 'insufficient_seats');
                                                    $set('return_trip_available_seats', $availableSeats);
                                                }
                                            } else {
                                                // Error al buscar viaje de vuelta
                                                $set('return_trip_id', null);
                                            }
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
                            }),


                        Grid::make(2)
                            ->schema([
                                DatePicker::make('return_date')
                                    ->label('Fecha de vuelta')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->closeOnDateSelection()
                                    ->minDate(function (Get $get) {
                                        $departureDate = $get('departure_date');
                                        if (!$departureDate) {
                                            return now()->startOfDay();
                                        }

                                        // La fecha mínima es la fecha de salida
                                        $date = is_string($departureDate)
                                            ? Carbon::parse($departureDate)
                                            : $departureDate;

                                        return $date->startOfDay();
                                    })
                                    ->disabledDates(function (): array {
                                        // Generar array de fechas de sábados y domingos para los próximos 2 años
                                        $disabledDates = [];
                                        $start = Carbon::today();
                                        $end = Carbon::today()->addYears(2);

                                        while ($start->lte($end)) {
                                            // 0 = Domingo, 6 = Sábado
                                            if ($start->dayOfWeek === 0 || $start->dayOfWeek === 6) {
                                                $disabledDates[] = $start->copy()->format('Y-m-d');
                                            }
                                            $start->addDay();
                                        }

                                        return $disabledDates;
                                    })
                                    /*                                     ->helperText(function (Get $get) {
                                                                            $departureDate = $get('departure_date');
                                                                            if ($departureDate) {
                                                                                $date = is_string($departureDate) ? Carbon::parse($departureDate) : $departureDate;
                                                                                return 'Debe ser igual o posterior a la fecha de ida (' . $date->format('d/m/Y') . '). Solo días laborables (lunes a viernes).';
                                                                            }
                                                                            return 'Debe ser igual o posterior a la fecha de ida. Solo días laborables (lunes a viernes).';
                                                                        }) */
                                    ->validationMessages([
                                        'after_or_equal' => 'La fecha de vuelta debe ser igual o posterior a la fecha de ida.',
                                        'required' => 'Seleccione una fecha de vuelta',
                                    ])
                                    ->rule('after_or_equal:departure_date')
                                    ->visible(fn(Get $get) => $get('is_round_trip'))
                                    ->live()
                                    ->afterStateUpdated(fn($set) => [
                                        $set('return_schedule_id', null),
                                        $set('return_trip_id', null),
                                        $set('return_trip_search_status', null),
                                        $set('return_trip_available_seats', null),
                                    ]),

                                Select::make('return_schedule_id')
                                    ->label('Horario de vuelta')
                                    ->required()
                                    ->visible(fn(Get $get) => $get('is_round_trip'))
                                    ->disabled(fn(Get $get) => blank($get('return_date')))
                                    ->placeholder(function (Get $get) {
                                        if (blank($get('return_date'))) {
                                            return 'Seleccione la fecha de vuelta primero';
                                        }
                                        if (blank($get('destination_location_id')) || blank($get('origin_location_id'))) {
                                            return 'Seleccione origen y destino primero';
                                        }

                                        $departureScheduleId = $get('schedule_id');
                                        $departureDate = $get('departure_date');
                                        $returnDate = $get('return_date');

                                        // Obtener el horario de ida seleccionado para comparar el departure_time
                                        $departureSchedule = null;
                                        $departureTime = null;
                                        if ($departureScheduleId) {
                                            $departureSchedule = Schedule::find($departureScheduleId);
                                            if ($departureSchedule && $departureSchedule->departure_time) {
                                                $departureTime = $departureSchedule->departure_time;
                                            }
                                        }

                                        // Verificar si las fechas son el mismo día
                                        $isSameDay = false;
                                        if ($departureDate && $returnDate) {
                                            $departure = is_string($departureDate) ? Carbon::parse($departureDate) : $departureDate;
                                            $return = is_string($returnDate) ? Carbon::parse($returnDate) : $returnDate;
                                            $isSameDay = $departure->format('Y-m-d') === $return->format('Y-m-d');
                                        }

                                        // Buscar rutas que conecten el destino (origen de vuelta) con el origen (destino de vuelta)
                                        $schedules = Schedule::query()
                                            ->whereHas('route.stops', fn($q) => $q->where('location_id', $get('destination_location_id')))
                                            ->whereHas('route.stops', fn($q) => $q->where('location_id', $get('origin_location_id')))
                                            ->where('is_active', true)
                                            ->orderBy('departure_time')
                                            ->get()
                                            ->filter(function ($schedule) use ($get) {
                                                return $schedule->route->isValidSegment(
                                                    $get('destination_location_id'),
                                                    $get('origin_location_id')
                                                );
                                            });

                                        // Si es el mismo día y hay un horario de ida seleccionado, filtrar por departure_time
                                        if ($isSameDay && $departureTime) {
                                            $schedules = $schedules->filter(function ($schedule) use ($departureTime) {
                                                if (!$schedule->departure_time) {
                                                    return false;
                                                }
                                                $scheduleTime = Carbon::parse($schedule->departure_time)->format('H:i:s');
                                                $departureTimeStr = Carbon::parse($departureTime)->format('H:i:s');
                                                return $scheduleTime > $departureTimeStr;
                                            });
                                        }

                                        if ($schedules->isEmpty()) {
                                            if ($isSameDay && $departureTime) {
                                                return 'No hay horarios disponibles (el horario de vuelta debe ser posterior al de ida)';
                                            }
                                            return 'No hay horarios disponibles para esta ruta de vuelta';
                                        }

                                        return 'Seleccione un horario de vuelta';
                                    })
                                    ->options(function (Get $get) {
                                        if (blank($get('return_date')) || blank($get('destination_location_id')) || blank($get('origin_location_id'))) {
                                            return [];
                                        }

                                        $departureScheduleId = $get('schedule_id');
                                        $departureDate = $get('departure_date');
                                        $returnDate = $get('return_date');

                                        // Obtener el horario de ida seleccionado para comparar el departure_time
                                        $departureSchedule = null;
                                        $departureTime = null;
                                        if ($departureScheduleId) {
                                            $departureSchedule = Schedule::find($departureScheduleId);
                                            if ($departureSchedule && $departureSchedule->departure_time) {
                                                $departureTime = $departureSchedule->departure_time;
                                            }
                                        }

                                        // Verificar si las fechas son el mismo día
                                        $isSameDay = false;
                                        if ($departureDate && $returnDate) {
                                            $departure = is_string($departureDate) ? Carbon::parse($departureDate) : $departureDate;
                                            $return = is_string($returnDate) ? Carbon::parse($returnDate) : $returnDate;
                                            $isSameDay = $departure->format('Y-m-d') === $return->format('Y-m-d');
                                        }

                                        // Buscar rutas que conecten el destino (origen de vuelta) con el origen (destino de vuelta)
                                        $schedules = Schedule::query()
                                            ->whereHas('route.stops', fn($q) => $q->where('location_id', $get('destination_location_id')))
                                            ->whereHas('route.stops', fn($q) => $q->where('location_id', $get('origin_location_id')))
                                            ->where('is_active', true)
                                            ->orderBy('departure_time') // Ordenar por hora de salida
                                            ->get()
                                            ->filter(function ($schedule) use ($get) {
                                                // Validar que el segmento sea válido (destino -> origen)
                                                return $schedule->route->isValidSegment(
                                                    $get('destination_location_id'),
                                                    $get('origin_location_id')
                                                );
                                            });

                                        // Si es el mismo día y hay un horario de ida seleccionado, filtrar por departure_time
                                        if ($isSameDay && $departureTime) {
                                            $schedules = $schedules->filter(function ($schedule) use ($departureTime) {
                                                // Solo mostrar horarios con departure_time posterior al de ida
                                                if (!$schedule->departure_time) {
                                                    return false;
                                                }
                                                // Comparar los tiempos: solo mostrar horarios con hora de salida mayor
                                                $scheduleTime = Carbon::parse($schedule->departure_time)->format('H:i:s');
                                                $departureTimeStr = Carbon::parse($departureTime)->format('H:i:s');
                                                return $scheduleTime > $departureTimeStr;
                                            });
                                        }

                                        return $schedules->mapWithKeys(fn($schedule) => [
                                            $schedule->id => $schedule->display_name,
                                        ]);
                                    })
                                    ->helperText(function (Get $get) {
                                        if (blank($get('return_date')) || blank($get('destination_location_id')) || blank($get('origin_location_id'))) {
                                            return null;
                                        }

                                        $departureScheduleId = $get('schedule_id');
                                        $departureDate = $get('departure_date');
                                        $returnDate = $get('return_date');

                                        // Obtener el horario de ida seleccionado para comparar el departure_time
                                        $departureSchedule = null;
                                        $departureTime = null;
                                        if ($departureScheduleId) {
                                            $departureSchedule = Schedule::find($departureScheduleId);
                                            if ($departureSchedule && $departureSchedule->departure_time) {
                                                $departureTime = $departureSchedule->departure_time;
                                            }
                                        }

                                        // Verificar si las fechas son el mismo día
                                        $isSameDay = false;
                                        if ($departureDate && $returnDate) {
                                            $departure = is_string($departureDate) ? Carbon::parse($departureDate) : $departureDate;
                                            $return = is_string($returnDate) ? Carbon::parse($returnDate) : $returnDate;
                                            $isSameDay = $departure->format('Y-m-d') === $return->format('Y-m-d');
                                        }

                                        // Buscar rutas que conecten el destino (origen de vuelta) con el origen (destino de vuelta)
                                        $schedules = Schedule::query()
                                            ->whereHas('route.stops', fn($q) => $q->where('location_id', $get('destination_location_id')))
                                            ->whereHas('route.stops', fn($q) => $q->where('location_id', $get('origin_location_id')))
                                            ->where('is_active', true)
                                            ->orderBy('departure_time')
                                            ->get()
                                            ->filter(function ($schedule) use ($get) {
                                                return $schedule->route->isValidSegment(
                                                    $get('destination_location_id'),
                                                    $get('origin_location_id')
                                                );
                                            });

                                        // Si es el mismo día y hay un horario de ida seleccionado, filtrar por departure_time
                                        if ($isSameDay && $departureTime) {
                                            $schedules = $schedules->filter(function ($schedule) use ($departureTime) {
                                                if (!$schedule->departure_time) {
                                                    return false;
                                                }
                                                $scheduleTime = Carbon::parse($schedule->departure_time)->format('H:i:s');
                                                $departureTimeStr = Carbon::parse($departureTime)->format('H:i:s');
                                                return $scheduleTime > $departureTimeStr;
                                            });
                                        }

                                        if ($schedules->isEmpty()) {
                                            if ($isSameDay && $departureTime) {
                                                return 'No hay horarios disponibles para vuelta en el mismo día. El horario de vuelta debe ser posterior al horario de ida seleccionado. Por favor, seleccione otra fecha.';
                                            }
                                            return 'No hay horarios disponibles para esta ruta de vuelta. Por favor, seleccione otra fecha o verifique la disponibilidad.';
                                        }

                                        return null;
                                    })
                                    ->live()
                                    ->afterStateUpdated(fn($set) => [
                                        $set('return_trip_id', null),
                                        $set('return_trip_search_status', null),
                                        $set('return_trip_available_seats', null),
                                    ])
                                    ->validationMessages([
                                        'required' => 'Seleccione un horario de vuelta',
                                    ]),
                            ]),
                        // Información del viaje de vuelta y botón de búsqueda
                        /* ViewField::make('return_trip_search_section')
                            ->label('')
                            ->view('tickets.search-return-trip-section')
                            ->viewData(function (Get $get) {
                                $returnTripId = $get('return_trip_id');
                                $searchStatus = $get('return_trip_search_status');
                                $availableSeats = $get('return_trip_available_seats');
                                $requiredSeats = (int) $get('passengers_count');

                                $trip = null;
                                if ($returnTripId) {
                                    $trip = Trip::find($returnTripId);
                                }

                                return [
                                    'tripId' => $returnTripId,
                                    'trip' => $trip,
                                    'searchStatus' => $searchStatus,
                                    'availableSeats' => $availableSeats,
                                    'requiredSeats' => $requiredSeats,
                                ];
                            })
                            ->visible(
                                fn(Get $get) =>
                                $get('is_round_trip') &&
                                    !blank($get('return_date'))
                            ),
 */
                        ViewField::make('search_return_trip_button')
                            ->label('')
                            ->view('tickets.search-return-trip-button')
                            ->visible(false),


                    ]),

                Step::make('Asientos (Ida)')
                    ->beforeValidation(function (Get $get, Set $set) {
                        // Limpiar reservas expiradas y liberar reservas anteriores al entrar
                        SeatReservation::cleanupExpired();
                        $sessionId = session()->getId();
                        SeatReservation::releaseBySession($sessionId);
                    })
                    ->afterValidation(function (Get $get) {
                        $required = (int) $get('passengers_count');
                        $selected = $get('seat_ids') ?? [];

                        if (!is_array($selected)) {
                            if (is_string($selected)) {
                                $selected = json_decode($selected, true) ?? [];
                            } else {
                                $selected = [];
                            }
                        }

                        if (count($selected) !== $required) {
                            Notification::make()
                                ->title('Asientos incompletos')
                                ->body("Debe seleccionar exactamente {$required} asiento(s) de ida.")
                                ->warning()
                                ->send();

                            throw new Halt();
                        }

                        // Verificar disponibilidad final de asientos
                        $tripId = $get('trip_id');
                        if ($tripId) {
                            $trip = Trip::find($tripId);
                            if ($trip) {
                                // Obtener asientos realmente disponibles
                                $availableSeatIds = $trip->availableSeats()->pluck('id')->toArray();

                                // Filtrar solo los asientos seleccionados que aún están disponibles
                                $validSelectedSeats = array_intersect($selected, $availableSeatIds);

                                // Si se eliminaron algunos asientos de la selección, notificar al usuario
                                $removedSeats = array_diff($selected, $validSelectedSeats);
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
                                        ->body('Los siguientes asientos fueron vendidos: ' . implode(', ', $removedSeatNumbers) . '. Por favor, seleccione otros asientos.')
                                        ->warning()
                                        ->persistent()
                                        ->send();

                                    throw new Halt();
                                } else {
                                    // Todos los asientos seleccionados siguen disponibles
                                    /*                                     Notification::make()
                                        ->title('Asientos de ida verificados')
                                        ->icon('heroicon-m-check-circle')
                                        ->body("Todos los asientos de ida seleccionados ({$required}) están disponibles. Puede continuar.")
                                        ->success()
                                        ->send(); */
                                }
                            }
                        }

                        logger()->info('Seat IDs', [
                            'seat_ids' => $get('seat_ids'),
                            'count' => count($get('seat_ids') ?? []),
                        ]);
                    })
                    ->schema([
                        ViewField::make('trip_required_info')
                            ->label('')
                            ->view('tickets.trip-required-info')
                            ->viewData(function (Get $get) {
                                $tripId = $get('trip_id');
                                $trip = $tripId ? Trip::find($tripId) : null;
                                $availableSeats = $trip ? $trip->remainingSeats() : 0;
                                $requiredSeats = (int) $get('passengers_count');

                                return [
                                    'tripId' => $tripId,
                                    'trip' => $trip,
                                    'availableSeats' => $availableSeats,
                                    'requiredSeats' => $requiredSeats,
                                ];
                            })
                            ->visible(fn(Get $get) => blank($get('trip_id')) || !Trip::find($get('trip_id')) || Trip::find($get('trip_id'))?->remainingSeats() < (int) $get('passengers_count')),

                        Hidden::make('seat_ids')
                            ->default([])
                            ->dehydrated()
                            ->required(
                                fn(Get $get) =>
                                !blank($get('trip_id')) &&
                                    Trip::find($get('trip_id')) &&
                                    Trip::find($get('trip_id'))?->remainingSeats() >= (int) $get('passengers_count')
                            )
                            ->rule(fn(Get $get) => function ($attribute, $value, $fail) use ($get) {
                                $required = (int) $get('passengers_count');
                                if (count($value ?? []) !== $required) {
                                    $fail("Debe seleccionar exactamente {$required} asiento(s).");
                                }
                            }),

                        ViewField::make('seat_selector')
                            ->label('Seleccione los asientos')
                            ->view('tickets.seat-selector')
                            ->viewData(function (Get $get) {
                                $tripId = $get('trip_id');
                                $trip = $tripId ? Trip::find($tripId) : null;
                                $selectedSeats = $get('seat_ids') ?? [];

                                // Asegurar que sea un array
                                if (!is_array($selectedSeats)) {
                                    if (is_string($selectedSeats)) {
                                        $selectedSeats = json_decode($selectedSeats, true) ?? [];
                                    } else {
                                        $selectedSeats = [];
                                    }
                                }

                                $requiredSeats = (int) $get('passengers_count');

                                return [
                                    'trip_id' => $tripId,
                                    'trip' => $trip,
                                    'seat_ids' => $selectedSeats,
                                    'passengers_count' => $requiredSeats,
                                    'fieldId' => 'seat_ids',
                                    'session_id' => session()->getId(),
                                    'enable_reservation' => true,
                                    'reservation_timeout' => 10,
                                ];
                            })
                            ->visible(
                                fn(Get $get) =>
                                !blank($get('trip_id')) &&
                                    Trip::find($get('trip_id')) &&
                                    Trip::find($get('trip_id'))?->remainingSeats() >= (int) $get('passengers_count')
                            ),
                    ]),
                Step::make('Asientos (Vuelta)')
                    ->visible(fn(Get $get) => $get('is_round_trip'))
                    ->beforeValidation(function (Get $get, Set $set) {
                        // Limpiar reservas expiradas y liberar reservas anteriores de vuelta al entrar
                        SeatReservation::cleanupExpired();
                        $sessionId = session()->getId();
                        $returnTripId = $get('return_trip_id');
                        if ($returnTripId) {
                            SeatReservation::where('user_session_id', $sessionId)
                                ->where('trip_id', $returnTripId)
                                ->delete();
                        }
                    })
                    ->afterValidation(function (Get $get) {
                        $required = (int) $get('passengers_count');
                        $selected = $get('return_seat_ids') ?? [];

                        if (!is_array($selected)) {
                            if (is_string($selected)) {
                                $selected = json_decode($selected, true) ?? [];
                            } else {
                                $selected = [];
                            }
                        }

                        if (count($selected) !== $required) {
                            Notification::make()
                                ->title('Asientos incompletos (vuelta)')
                                ->body("Debe seleccionar exactamente {$required} asiento(s) de vuelta.")
                                ->warning()
                                ->send();

                            throw new Halt();
                        }

                        // Verificar disponibilidad final de asientos de vuelta
                        $returnTripId = $get('return_trip_id');
                        if ($returnTripId) {
                            $returnTrip = Trip::find($returnTripId);
                            if ($returnTrip) {
                                // Obtener asientos realmente disponibles de vuelta
                                $availableSeatIds = $returnTrip->availableSeats()->pluck('id')->toArray();

                                // Filtrar solo los asientos seleccionados que aún están disponibles
                                $validSelectedSeats = array_intersect($selected, $availableSeatIds);

                                // Si se eliminaron algunos asientos de la selección, notificar al usuario
                                $removedSeats = array_diff($selected, $validSelectedSeats);
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
                                        ->body('Los siguientes asientos de vuelta fueron vendidos: ' . implode(', ', $removedSeatNumbers) . '. Por favor, seleccione otros asientos.')
                                        ->warning()
                                        ->persistent()
                                        ->send();

                                    throw new Halt();
                                } else {
                                    // Todos los asientos seleccionados siguen disponibles
                                    /*                                     Notification::make()
                                        ->title('Asientos de vuelta verificados')
                                        ->icon('heroicon-m-check-circle')
                                        ->body("Todos los asientos de vuelta seleccionados ({$required}) están disponibles. Puede continuar.")
                                        ->success()
                                        ->send(); */
                                }
                            }
                        }
                    })
                    ->schema([
                        ViewField::make('return_trip_required_info')
                            ->label('')
                            ->view('tickets.return-trip-required-info')
                            ->viewData(function (Get $get) {
                                $tripId = $get('return_trip_id');
                                $trip = $tripId ? Trip::find($tripId) : null;
                                $availableSeats = $trip ? $trip->remainingSeats() : 0;
                                $requiredSeats = (int) $get('passengers_count');

                                return [
                                    'tripId' => $tripId,
                                    'trip' => $trip,
                                    'availableSeats' => $availableSeats,
                                    'requiredSeats' => $requiredSeats,
                                ];
                            })
                            ->visible(
                                fn(Get $get) =>
                                blank($get('return_trip_id')) ||
                                    !Trip::find($get('return_trip_id')) ||
                                    Trip::find($get('return_trip_id'))?->remainingSeats() < (int) $get('passengers_count')
                            ),

                        Hidden::make('return_seat_ids')
                            ->default([])
                            ->dehydrated()
                            ->required(
                                fn(Get $get) =>
                                !blank($get('return_trip_id')) &&
                                    Trip::find($get('return_trip_id')) &&
                                    Trip::find($get('return_trip_id'))?->remainingSeats() >= (int) $get('passengers_count')
                            )
                            ->rule(fn(Get $get) => function ($attribute, $value, $fail) use ($get) {
                                $required = (int) $get('passengers_count');

                                if (count($value ?? []) !== $required) {
                                    $fail("Debe seleccionar exactamente {$required} asiento(s) de vuelta.");
                                }
                            }),

                        ViewField::make('return_seat_selector')
                            ->label('Seleccione los asientos de vuelta')
                            ->view('tickets.seat-selector')
                            ->viewData(function (Get $get) {
                                $tripId = $get('return_trip_id');
                                $trip = $tripId ? Trip::find($tripId) : null;
                                $selectedSeats = $get('return_seat_ids') ?? [];

                                // Asegurar que sea un array
                                if (!is_array($selectedSeats)) {
                                    if (is_string($selectedSeats)) {
                                        $selectedSeats = json_decode($selectedSeats, true) ?? [];
                                    } else {
                                        $selectedSeats = [];
                                    }
                                }

                                $requiredSeats = (int) $get('passengers_count');

                                return [
                                    'trip_id' => $tripId,
                                    'trip' => $trip,
                                    'seat_ids' => $selectedSeats,
                                    'passengers_count' => $requiredSeats,
                                    'fieldId' => 'return_seat_ids',
                                    'session_id' => session()->getId(),
                                    'enable_reservation' => true,
                                    'reservation_timeout' => 10,
                                ];
                            })
                            ->visible(
                                fn(Get $get) =>
                                !blank($get('return_trip_id')) &&
                                    Trip::find($get('return_trip_id')) &&
                                    Trip::find($get('return_trip_id'))?->remainingSeats() >= (int) $get('passengers_count')
                            ),
                    ]),

                Step::make('Pasajeros')
                    ->schema([
                        /*                         Text::make('Pasajeros seleccionados')
                            ->content(fn(Get $get) => $get('passengers_count') . ' pasajero(s) seleccionado(s).'),
                        Text::make('Asientos de ida seleccionados')
                            ->content(fn(Get $get) => implode(', ', $get('seat_ids'))),
                        Text::make('Asientos de vuelta seleccionados')
                            ->content(fn(Get $get) => implode(', ', $get('return_seat_ids'))), */
                        Repeater::make('passengers')
                            ->label('Pasajeros')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('first_name')
                                            ->label('Nombre')
                                            ->minLength(2)
                                            ->maxLength(80)
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Debe ingresar un nombre.',
                                                'min' => 'El nombre debe tener al menos :min caracteres.',
                                                'max' => 'El nombre no puede tener más de :max caracteres.',
                                            ]),
                                        TextInput::make('last_name')
                                            ->label('Apellido')
                                            ->minLength(2)
                                            ->maxLength(80)
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Debe ingresar un apellido.',
                                                'min' => 'El apellido debe tener al menos :min caracteres.',
                                                'maxLength' => 'El apellido no puede tener más de :max caracteres.',
                                            ]),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('dni')
                                            ->label('DNI')
                                            ->required()
                                            ->numeric()
                                            ->rules([
                                                'digits_between:7,8',
                                            ])
                                            ->validationMessages([
                                                'required' => 'Debe ingresar un DNI.',
                                                'numeric' => 'El DNI debe ser numérico.',
                                                'digits_between' => 'El DNI debe tener entre 7 y 8 dígitos.',
                                            ]),
                                        TextInput::make('phone_number')
                                            ->label('Teléfono')
                                            ->numeric()
                                            ->rules([
                                                'digits_between:7,12',
                                            ])
                                            ->validationMessages([
                                                'required' => 'Debe ingresar un número de teléfono.',
                                                'numeric' => 'El número de teléfono debe ser numérico.',
                                                'digits_between' => 'El número de teléfono debe tener entre 7 y 12 dígitos.',
                                            ]),
                                    ]),

                                Checkbox::make('travels_with_child')
                                    ->label('¿Viaja con un menor?')
                                    ->default(false)
                                    ->live()
                                    ->extraAttributes([
                                        'class' => 'toggle-checkbox'
                                    ])
                                    ->afterStateUpdated(function ($state, callable $set, $get) {
                                        $passengerIndex = $get('..');
                                        if (!$state) {
                                            $set('child_data', null);
                                        }
                                    })
                                    ->columnSpanFull(),

                                // Sección de datos del menor (condicional)
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('child_data.first_name')
                                            ->label('Nombre del menor')
                                            ->required()
                                            ->minLength(2)
                                            ->maxLength(80)
                                            ->validationMessages([
                                                'required' => 'Debe ingresar un nombre.',
                                                'min' => 'El nombre debe tener al menos :min caracteres.',
                                                'max' => 'El nombre no puede tener más de :max caracteres.',
                                            ]),

                                        TextInput::make('child_data.last_name')
                                            ->label('Apellido del menor')
                                            ->required()
                                            ->minLength(2)
                                            ->maxLength(80)
                                            ->validationMessages([
                                                'required' => 'Debe ingresar un apellido.',
                                                'min' => 'El apellido debe tener al menos :min caracteres.',
                                                'max' => 'El apellido no puede tener más de :max caracteres.',
                                            ]),

                                        TextInput::make('child_data.dni')
                                            ->label('DNI del menor')
                                            ->required()
                                            ->numeric()
                                            ->rules([
                                                'digits_between:7,8',
                                            ])
                                            ->validationMessages([
                                                'required' => 'Debe ingresar un DNI.',
                                                'numeric' => 'El DNI debe ser numérico.',
                                                'digits_between' => 'El DNI debe tener entre 7 y 8 dígitos.',
                                            ]),

                                        /* TextInput::make('child_data.age')
                                            ->label('Edad del menor')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(4)
                                            ->required(fn($get) => $get('travels_with_child'))
                                            ->visible(fn($get) => $get('travels_with_child'))
                                            ->helperText('Edad entre 0 y 4 años'), */
                                    ])
                                    ->visible(fn($get) => $get('travels_with_child'))
                                    ->live(),

                                Hidden::make('passenger_number')
                                    ->dehydrated(),

                                /*                                 Text::make('datos')
                                    ->content(
                                        function ($state, $component) {
                                            return var_dump($state);
                                        }
                                    ) */
                            ])

                            ->extraAttributes(
                                ['class' => '[&_.fi-fo-repeater-item-header-label]:text-fuchsia-600']
                            )
                            ->minItems(fn(Get $get) => (int) $get('passengers_count'))
                            ->maxItems(fn(Get $get) => (int) $get('passengers_count'))
                            ->required()
                            ->addActionLabel('Agregar pasajero')
                            ->validationMessages([
                                'minItems' => 'Debe agregar al menos {min} pasajero(s).',
                                'maxItems' => 'No puede agregar más de {max} pasajero(s).',
                                'required' => 'Debe agregar al menos un pasajero.',
                            ])
                            ->afterStateHydrated(function ($state, callable $set, Get $get) {
                                $required = (int) $get('passengers_count');

                                if (count($state ?? []) !== $required) {
                                    $passengers = [];

                                    for ($i = 0; $i < $required; $i++) {
                                        $passengers[$i] = [
                                            'passenger_number' => $i + 1, // 1, 2, 3...
                                        ];
                                    }

                                    $set('passengers', $passengers);
                                }
                            })

                            ->deletable(false)
                            ->reorderable(false)
                            /* ->grid(2) */
                            ->itemLabel(function (array $state, ?string $uuid, $component): ?string {
                                // Obtener número de pasajero
                                $passengerNumber = $state['passenger_number'] ?? 1;

                                // Obtener todos los datos del formulario
                                $formData = $component->getContainer()->getRawState();

                                // Obtener arrays de asientos
                                $seatIds = $formData['seat_ids'] ?? [];
                                $returnSeatIds = $formData['return_seat_ids'] ?? [];

                                // Depuración
                                logger()->info('itemLabel debug', [
                                    'passengerNumber' => $passengerNumber,
                                    'seatIds' => $seatIds,
                                    'returnSeatIds' => $returnSeatIds,
                                    'seatAtIndex' => $seatIds[$passengerNumber - 1] ?? 'NOT_FOUND',
                                    'returnSeatAtIndex' => $returnSeatIds[$passengerNumber - 1] ?? 'NOT_FOUND',
                                ]);

                                // Construir label
                                $label = 'Pasajero ' . $passengerNumber;

                                // Agregar asiento de ida si existe
                                if (isset($seatIds[$passengerNumber - 1]) && $seatIds[$passengerNumber - 1] !== null) {
                                    $label .= ' | Asiento ida: ' . $seatIds[$passengerNumber - 1];
                                }

                                // Agregar asiento de vuelta si existe
                                if (isset($returnSeatIds[$passengerNumber - 1]) && $returnSeatIds[$passengerNumber - 1] !== null) {
                                    $label .= ' | Asiento vuelta: ' . $returnSeatIds[$passengerNumber - 1];
                                }

                                return $label;
                            }),
                    ]),
                Step::make('Resumen')
                    ->schema([
                        ViewField::make('summary')
                            ->view('tickets.summary')
                            ->viewData(fn(Get $get) => [
                                'get' => $get,
                            ]),
                    ]),
            ])
                ->submitAction(new HtmlString('<button type="submit" class="fi-color fi-color-primary fi-bg-color-600 hover:fi-bg-color-500 dark:fi-bg-color-600 dark:hover:fi-bg-color-500 fi-text-color-0 hover:fi-text-color-0 dark:fi-text-color-0 dark:hover:fi-text-color-0 fi-btn fi-size-md  fi-ac-btn-action">Finalizar</button>'))
                ->skippable(false)
        ])->columns(0);
    }
}
