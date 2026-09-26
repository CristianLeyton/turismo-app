<?php

namespace Tests\Feature;

use App\Models\Seat;
use App\Models\SeatReservation;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\Trip;
use App\Services\TicketRescheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Verifica que reprogramar no pise asientos de otros pasajeros:
 * - Los demás tickets del viaje de origen conservan su asiento.
 * - La unique (trip_id, seat_id, deleted_at) protege contra dobles asignaciones.
 * - El pasajero puede conservar su número de asiento cuando el bus destino lo permite.
 */
class RescheduleDoesNotAffectOtherSeatsTest extends TestCase
{
    use RefreshDatabase;

    protected TicketRescheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TicketRescheduleService::class);

        $admin = \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin2@test.local',
            'username' => 'admin2',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $this->actingAs($admin);
    }

    public function test_los_demas_tickets_del_viaje_origen_conservan_su_asiento(): void
    {
        $oran = \App\Models\Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = \App\Models\Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = \App\Models\Bus::create([
            'name' => 'Linea X',
            'plate' => 'XXX111',
            'seat_count' => 4,
            'floors' => 1,
        ]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create([
                'bus_id' => $bus->id,
                'seat_number' => (string) $n,
                'is_active' => true,
                'floor' => '1',
            ]);
        }

        $route = \App\Models\Route::create(['name' => 'Orán - Salta X', 'bus_id' => $bus->id, 'is_active' => true]);
        \App\Models\RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        \App\Models\RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $scheduleManana = \App\Models\Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana X',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $scheduleNoche = \App\Models\Schedule::create([
            'route_id' => $route->id,
            'name' => 'Noche X',
            'departure_time' => '22:00',
            'arrival_time' => '23:30',
            'is_active' => true,
        ]);

        $tripOrigen = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $scheduleManana->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-01',
        ]);

        $sale = Sale::create([
            'user_id' => Auth::id(),
            'sale_date' => now(),
            'total_amount' => 300,
        ]);

        // Tres pasajeros: el del asiento 1 se reprograma; 2 y 3 deben quedar intactos.
        $seats = [
            '1' => Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first(),
            '2' => Seat::where('bus_id', $bus->id)->where('seat_number', '2')->first(),
            '3' => Seat::where('bus_id', $bus->id)->where('seat_number', '3')->first(),
        ];

        $tickets = [];
        foreach ($seats as $number => $seat) {
            $passenger = \App\Models\Passenger::create([
                'first_name' => "Pasajero {$number}",
                'last_name' => 'Test',
                'dni' => "1111111{$number}",
                'passenger_type' => 'adult',
            ]);

            $tickets[$number] = Ticket::create([
                'sale_id' => $sale->id,
                'trip_id' => $tripOrigen->id,
                'seat_id' => $seat->id,
                'passenger_id' => $passenger->id,
                'is_round_trip' => false,
                'origin_location_id' => $oran->id,
                'destination_location_id' => $salta->id,
                'price' => 100,
            ]);
        }

        // El pasajero del asiento 1 se reprograma al viaje nocturno, asiento 4.
        $this->service->reschedule($tickets['1'], [
            'scope' => 'outbound',
            'date' => '2026-10-02',
            'schedule_id' => $scheduleNoche->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '4')->first()->id,
        ]);

        $tickets['1']->refresh();
        $tickets['2']->refresh();
        $tickets['3']->refresh();

        // El reprogramado: nuevo viaje y nuevo asiento
        $this->assertSame('2026-10-02', $tickets['1']->trip->trip_date->format('Y-m-d'));
        $this->assertSame('4', (string) $tickets['1']->seat->seat_number);

        // Los demás: mismo viaje y mismo asiento
        $this->assertSame($tripOrigen->id, $tickets['2']->trip_id);
        $this->assertSame('2', (string) $tickets['2']->seat->seat_number);
        $this->assertSame($tripOrigen->id, $tickets['3']->trip_id);
        $this->assertSame('3', (string) $tickets['3']->seat->seat_number);

        // El asiento 1 quedó LIBRE en el viaje origen (disponible para nueva venta)
        $libre = $tripOrigen->availableSeats()->pluck('id')->contains($seats['1']->id);
        $this->assertTrue($libre, 'El asiento 1 debería quedar libre en el viaje de origen.');
    }

    public function test_unique_trip_seat_protege_contra_doble_asignacion(): void
    {
        $oran = \App\Models\Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = \App\Models\Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = \App\Models\Bus::create([
            'name' => 'Linea U',
            'plate' => 'UUU111',
            'seat_count' => 2,
            'floors' => 1,
        ]);
        foreach ([1, 2] as $n) {
            Seat::create([
                'bus_id' => $bus->id,
                'seat_number' => (string) $n,
                'is_active' => true,
                'floor' => '1',
            ]);
        }

        $route = \App\Models\Route::create(['name' => 'Orán - Salta U', 'bus_id' => $bus->id, 'is_active' => true]);
        \App\Models\RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        \App\Models\RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = \App\Models\Schedule::create([
            'route_id' => $route->id,
            'name' => 'Único U',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $tripDestino = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-02',
        ]);

        // Viaje destino ya tiene el asiento 1 vendido a otro pasajero.
        $otro = \App\Models\Passenger::create([
            'first_name' => 'Ocupante',
            'last_name' => 'Asiento',
            'dni' => '99999999',
            'passenger_type' => 'adult',
        ]);

        $saleOtro = Sale::create([
            'user_id' => Auth::id(),
            'sale_date' => now(),
            'total_amount' => 100,
        ]);

        Ticket::create([
            'sale_id' => $saleOtro->id,
            'trip_id' => $tripDestino->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first()->id,
            'passenger_id' => $otro->id,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
        ]);

        // Un ticket a reprogramar hacia ese mismo asiento 1 (ocupado).
        $tripOrigen = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-01',
        ]);

        $sale = Sale::create([
            'user_id' => Auth::id(),
            'sale_date' => now(),
            'total_amount' => 100,
        ]);

        $pasajero = \App\Models\Passenger::create([
            'first_name' => 'Reprogramado',
            'last_name' => 'Test',
            'dni' => '88888888',
            'passenger_type' => 'adult',
        ]);

        $ticket = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $tripOrigen->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '2')->first()->id,
            'passenger_id' => $pasajero->id,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-02',
            'schedule_id' => $schedule->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first()->id,
        ]);

        // Verificar que el ocupante original conserva el asiento (por si el test llega aquí)
        $this->assertDatabaseHas('tickets', [
            'trip_id' => $tripDestino->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first()->id,
            'passenger_id' => $otro->id,
        ]);
    }

    public function test_reserva_temporal_de_otra_sesion_bloquea_el_asiento_destino(): void
    {
        $oran = \App\Models\Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = \App\Models\Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = \App\Models\Bus::create([
            'name' => 'Linea R',
            'plate' => 'RRR111',
            'seat_count' => 2,
            'floors' => 1,
        ]);
        foreach ([1, 2] as $n) {
            Seat::create([
                'bus_id' => $bus->id,
                'seat_number' => (string) $n,
                'is_active' => true,
                'floor' => '1',
            ]);
        }

        $route = \App\Models\Route::create(['name' => 'Orán - Salta R', 'bus_id' => $bus->id, 'is_active' => true]);
        \App\Models\RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        \App\Models\RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = \App\Models\Schedule::create([
            'route_id' => $route->id,
            'name' => 'Único R',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $tripOrigen = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-01',
        ]);

        $tripDestino = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-02',
        ]);

        $sale = Sale::create([
            'user_id' => Auth::id(),
            'sale_date' => now(),
            'total_amount' => 100,
        ]);

        $pasajero = \App\Models\Passenger::create([
            'first_name' => 'Reprogramado',
            'last_name' => 'Reserva',
            'dni' => '77777777',
            'passenger_type' => 'adult',
        ]);

        $ticket = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $tripOrigen->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first()->id,
            'passenger_id' => $pasajero->id,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
        ]);

        // Otra sesión reservó el asiento 2 del viaje destino (5 min).
        SeatReservation::create([
            'trip_id' => $tripDestino->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '2')->first()->id,
            'user_session_id' => 'otra-sesion-xyz',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('no está disponible');

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-02',
            'schedule_id' => $schedule->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '2')->first()->id,
        ]);
    }

    public function test_puede_conservar_el_mismo_numero_de_asiento_cuando_el_bus_destino_lo_permite(): void
    {
        $oran = \App\Models\Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = \App\Models\Location::create(['name' => 'Salta', 'is_active' => true]);

        // Dos buses con la MISMA numeración.
        $busA = \App\Models\Bus::create([
            'name' => 'Linea A',
            'plate' => 'AAA999',
            'seat_count' => 2,
            'floors' => 1,
        ]);
        $busB = \App\Models\Bus::create([
            'name' => 'Linea B',
            'plate' => 'BBB999',
            'seat_count' => 2,
            'floors' => 1,
        ]);

        foreach ([$busA, $busB] as $bus) {
            foreach ([1, 2] as $n) {
                Seat::create([
                    'bus_id' => $bus->id,
                    'seat_number' => (string) $n,
                    'is_active' => true,
                    'floor' => '1',
                ]);
            }
        }

        $routeA = \App\Models\Route::create(['name' => 'Orán - Salta A', 'bus_id' => $busA->id, 'is_active' => true]);
        \App\Models\RouteStop::create(['route_id' => $routeA->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        \App\Models\RouteStop::create(['route_id' => $routeA->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $routeB = \App\Models\Route::create(['name' => 'Orán - Salta B', 'bus_id' => $busB->id, 'is_active' => true]);
        \App\Models\RouteStop::create(['route_id' => $routeB->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        \App\Models\RouteStop::create(['route_id' => $routeB->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $scheduleA = \App\Models\Schedule::create([
            'route_id' => $routeA->id,
            'name' => 'A mañana',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $scheduleB = \App\Models\Schedule::create([
            'route_id' => $routeB->id,
            'name' => 'B noche',
            'departure_time' => '23:00',
            'arrival_time' => '23:59',
            'is_active' => true,
        ]);

        $tripOrigen = Trip::create([
            'route_id' => $routeA->id,
            'schedule_id' => $scheduleA->id,
            'bus_id' => $busA->id,
            'trip_date' => '2026-10-01',
        ]);

        $sale = Sale::create([
            'user_id' => Auth::id(),
            'sale_date' => now(),
            'total_amount' => 100,
        ]);

        $pasajero = \App\Models\Passenger::create([
            'first_name' => 'Mantiene',
            'last_name' => 'Numero',
            'dni' => '66666666',
            'passenger_type' => 'adult',
        ]);

        $ticket = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $tripOrigen->id,
            'seat_id' => Seat::where('bus_id', $busA->id)->where('seat_number', '2')->first()->id,
            'passenger_id' => $pasajero->id,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
        ]);

        // Reprogramar al bus B, pidiendo el asiento "2" (mismo número, bus distinto).
        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-02',
            'schedule_id' => $scheduleB->id,
            'seat_id' => Seat::where('bus_id', $busB->id)->where('seat_number', '2')->first()->id,
        ]);

        $ticket->refresh();

        $this->assertSame($busB->id, $ticket->trip->bus_id);
        $this->assertSame('2', (string) $ticket->seat->seat_number);

        // El asiento 2 del bus A quedó libre en el viaje origen
        $libre = $tripOrigen->availableSeats()->pluck('id')
            ->contains(Seat::where('bus_id', $busA->id)->where('seat_number', '2')->first()->id);
        $this->assertTrue($libre);
    }
}
