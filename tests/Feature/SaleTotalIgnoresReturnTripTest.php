<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTotalIgnoresReturnTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_total_ignores_return_trip_tickets(): void
    {
        $user = User::create([
            'name' => 'CasaCentral',
            'surname' => 'Central',
            'phone' => '0000',
            'email' => 'test@example.com',
            'username' => 'testadmin',
            'password' => 'test1234',
            'is_admin' => true,
        ]);

        $bus = Bus::create(['name' => 'TUR 123', 'seat_count' => 20]);
        $origin = Location::create(['name' => 'A', 'is_active' => true]);
        $destination = Location::create(['name' => 'B', 'is_active' => true]);
        $route = Route::create([
            'bus_id' => $bus->id,
            'name' => 'R-1',
            'is_active' => true,
        ]);
        RouteStop::create([
            'route_id' => $route->id,
            'location_id' => $origin->id,
            'stop_order' => 1,
        ]);
        RouteStop::create([
            'route_id' => $route->id,
            'location_id' => $destination->id,
            'stop_order' => 2,
        ]);
        $schedule = Schedule::create([
            'route_id' => $route->id,
            'departure_time' => '08:00',
            'arrival_time' => '10:00',
        ]);
        $trip = Trip::create([
            'bus_id' => $bus->id,
            'schedule_id' => $schedule->id,
            'route_id' => $route->id,
            'trip_date' => now()->toDateString(),
        ]);
        $returnSchedule = Schedule::create([
            'route_id' => $route->id,
            'departure_time' => '12:00',
            'arrival_time' => '14:00',
        ]);
        $returnTrip = Trip::create([
            'bus_id' => $bus->id,
            'schedule_id' => $returnSchedule->id,
            'route_id' => $route->id,
            'trip_date' => now()->addDay()->toDateString(),
        ]);

        $passenger = Passenger::create([
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'dni' => '12345678',
            'phone_number' => '0000',
            'passenger_type' => 'adult',
        ]);

        $seat = Seat::create([
            'bus_id' => $bus->id,
            'seat_number' => '1A',
            'floor' => 1,
            'is_active' => true,
        ]);

        $returnSeat = Seat::create([
            'bus_id' => $bus->id,
            'seat_number' => '1B',
            'floor' => 1,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'user_id' => $user->id,
            'sale_date' => now(),
            'total_amount' => 0,
        ]);

        Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'passenger_id' => $passenger->id,
            'seat_id' => $seat->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'price' => 1000,
            'payment_method' => 'cash',
            'is_round_trip' => false,
        ]);

        Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $returnTrip->id,
            'passenger_id' => $passenger->id,
            'seat_id' => $returnSeat->id,
            'origin_location_id' => $destination->id,
            'destination_location_id' => $origin->id,
            'price' => 900,
            'payment_method' => 'cash',
            'is_round_trip' => true,
        ]);

        $sale->recalculateTotal();
        $sale->refresh();

        $this->assertEquals(1000, $sale->total_amount);
        $this->assertDatabaseHas('tickets', [
            'id' => $returnSeat->id,
            'sale_id' => $sale->id,
            'price' => 900,
            'is_round_trip' => true,
        ]);
    }
}
