<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Carbon\Carbon;
use Filament\Forms\Components\Repeater;
use App\Models\Trip;
use App\Models\Seat;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Button;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Tables\Columns\Column;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Wizard::make([
                Step::make('Buscar viaje')
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
                                    ->helperText('Solo se permiten días laborables (lunes a viernes)')
                                    ->live()
                                    ->afterStateUpdated(fn($set) => [
                                        $set('schedule_id', null), // Limpiar horario al cambiar fecha para revalidar horarios disponibles
                                        $set('trip_id', null),
                                        $set('trip_search_status', null),
                                        $set('trip_available_seats', null),
                                        $set('return_trip_id', null),
                                        $set('return_trip_search_status', null),
                                        $set('return_trip_available_seats', null),
                                    ])
                                    ->validationMessages([
                                        'required' => 'Seleccione una fecha de ida',
                                    ]),
                                Select::make('schedule_id')
                                    ->label('Horario de ida')
                                    ->required()
                                    ->disabled(fn(Get $get) => blank($get('origin_location_id')) || blank($get('destination_location_id')))
                                    ->placeholder(function (Get $get) {
                                        if (blank($get('origin_location_id'))) {
                                            return 'Seleccione un origen primero';
                                        }
                                        if (blank($get('destination_location_id'))) {
                                            return 'Seleccione un destino primero';
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
                                                if (!$schedule->route->isValidSegment(
                                                    $get('origin_location_id'),
                                                    $get('destination_location_id')
                                                )) {
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
                                        if (blank($get('origin_location_id')) || blank($get('destination_location_id'))) {
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
                                                if (!$schedule->route->isValidSegment(
                                                    $get('origin_location_id'),
                                                    $get('destination_location_id')
                                                )) {
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
                                        if (blank($get('origin_location_id')) || blank($get('destination_location_id'))) {
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
                                                if (!$schedule->route->isValidSegment(
                                                    $get('origin_location_id'),
                                                    $get('destination_location_id')
                                                )) {
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
                                    ])
                                    ->validationMessages([
                                        'required' => 'Seleccione un horario',
                                    ]),
                            ]),

                        TextInput::make('passengers_count')
                            ->label('Cantidad de pasajeros')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn($set) => [
                                $set('trip_id', null),
                                $set('trip_search_status', null),
                                $set('trip_available_seats', null),
                            ])
                            ->validationMessages([
                                'required' => 'Ingrese la cantidad de pasajeros',
                            ]),

                        // Campos Hidden para almacenar el estado de la búsqueda
                        Hidden::make('trip_id')
                            ->live(),
                        Hidden::make('trip_search_status')
                            ->live(),
                        Hidden::make('trip_available_seats')
                            ->live(),
                        Hidden::make('return_schedule_id')
                            ->live(),
                        Hidden::make('return_trip_id')
                            ->live(),
                        Hidden::make('return_trip_search_status')
                            ->live(),
                        Hidden::make('return_trip_available_seats')
                            ->live(),

                        // Información del viaje y botón de búsqueda
                        // Información del viaje y botón de búsqueda
                        ViewField::make('search_trip_section')
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
                            ->visible(fn(Get $get) => !blank($get('schedule_id')) && !blank($get('departure_date'))),

                        ViewField::make('search_trip_button')
                            ->label('')
                            ->view('tickets.search-trip-button')
                            ->visible(
                                fn(Get $get) =>
                                !blank($get('schedule_id')) &&
                                    !blank($get('departure_date')) &&
                                    !blank($get('origin_location_id')) &&
                                    !blank($get('destination_location_id')) &&
                                    !blank($get('passengers_count')) &&
                                    (blank($get('trip_id')) || $get('trip_search_status') !== 'available')
                            ),

                        Toggle::make('is_round_trip')
                            ->label('¿Viaje de ida y vuelta?')
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {

                                if (! $state) {
                                    $set('return_date', null);
                                    $set('return_schedule_id', null);
                                    $set('return_trip_id', null);
                                    $set('return_trip_search_status', null);
                                    $set('return_trip_available_seats', null);
                                    return;
                                }

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

                                if (! $route) {
                                    Notification::make()
                                        ->title('Ruta de vuelta inexistente')
                                        ->danger()
                                        ->send();

                                    $set('is_round_trip', false);
                                }
                            }),


                        Grid::make(2)
                            ->schema([
                                DatePicker::make('return_date')
                                    ->label('Fecha de vuelta')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
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
                                    ->helperText(function (Get $get) {
                                        $departureDate = $get('departure_date');
                                        if ($departureDate) {
                                            $date = is_string($departureDate) ? Carbon::parse($departureDate) : $departureDate;
                                            return 'Debe ser igual o posterior a la fecha de ida (' . $date->format('d/m/Y') . '). Solo días laborables (lunes a viernes).';
                                        }
                                        return 'Debe ser igual o posterior a la fecha de ida. Solo días laborables (lunes a viernes).';
                                    })
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
                        ViewField::make('return_trip_search_section')
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

                        ViewField::make('search_return_trip_button')
                            ->label('')
                            ->view('tickets.search-return-trip-button')
                            ->visible(
                                fn(Get $get) =>
                                $get('is_round_trip') &&
                                    !blank($get('return_date')) &&
                                    !blank($get('return_schedule_id')) &&
                                    !blank($get('passengers_count')) &&
                                    (blank($get('return_trip_id')) || $get('return_trip_search_status') !== 'available')
                            ),


                    ]),

                Step::make('Asientos (Ida)')
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

                        Select::make('seat_ids')
                            ->label('Seleccione los asientos')
                            ->multiple()
                            ->required()
                            ->options(
                                fn(Get $get) =>
                                Trip::find($get('trip_id'))
                                    ?->availableSeats()
                                    ->pluck('seat_number', 'id')
                                    ?? []
                            )
                            ->rule(fn(Get $get) => 'size:' . $get('passengers_count'))
                            ->visible(
                                fn(Get $get) =>
                                !blank($get('trip_id')) &&
                                    Trip::find($get('trip_id')) &&
                                    Trip::find($get('trip_id'))?->remainingSeats() >= (int) $get('passengers_count')
                            ),
                    ]),

                Step::make('Pasajeros')
                    ->schema([
                        Repeater::make('passengers')
                            ->label('Datos de pasajeros')
                            ->schema([
                                TextInput::make('first_name')->required(),
                                TextInput::make('last_name')->required(),
                                TextInput::make('dni')->required(),
                                DatePicker::make('birth_date')->required(),
                                TextInput::make('phone_number'),
                                TextInput::make('email'),
                            ])
                            ->minItems(fn(Get $get) => (int) $get('passengers_count'))
                            ->maxItems(fn(Get $get) => (int) $get('passengers_count'))
                            ->required(),
                    ]),

                Step::make('Asientos (Vuelta)')
                    ->visible(fn(Get $get) => $get('is_round_trip'))
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

                        Select::make('return_seat_ids')
                            ->label('Seleccione los asientos de vuelta')
                            ->multiple()
                            ->options(
                                fn(Get $get) =>
                                Trip::find($get('return_trip_id'))
                                    ?->availableSeats()
                                    ->pluck('seat_number', 'id')
                                    ?? []
                            )
                            ->rule(fn(Get $get) => 'size:' . $get('passengers_count'))
                            ->visible(
                                fn(Get $get) =>
                                !blank($get('return_trip_id')) &&
                                    Trip::find($get('return_trip_id')) &&
                                    Trip::find($get('return_trip_id'))?->remainingSeats() >= (int) $get('passengers_count')
                            ),
                    ]),

                Step::make('Resumen')
                    ->schema([
                        ViewField::make('summary')
                            ->view('tickets.summary')
                            ->viewData(fn(Get $get) => [
                                'get' => $get,
                            ]),
                    ]),
            ]),
        ])->columns(0);
    }
}
