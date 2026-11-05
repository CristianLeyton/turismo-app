<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Services\TicketRescheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketRescheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TicketRescheduleService $service;

    protected Location $oran;

    protected Location $salta;

    protected Bus $bus1;

    protected Bus $bus2;

    protected Route $routeIda;

    protected Route $routeVuelta;

    protected Schedule $scheduleIda;

    protected Schedule $scheduleVuelta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TicketRescheduleService::class);

        $this->oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        // Bus 1: ruta/horarios de ida y vuelta activos
        $this->bus1 = Bus::create([
            'name' => 'Linea 1',
            'plate' => 'AAA111',
            'seat_count' => 4,
            'floors' => 1,
        ]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create([
                'bus_id' => $this->bus1->id,
                'seat_number' => (string) $n,
                'is_active' => true,
                'floor' => '1',
            ]);
        }

        // Bus 2: colectivo DISTINTO con los mismos asientos numerados (para probar cambios de bus)
        $this->bus2 = Bus::create([
            'name' => 'Linea 2',
            'plate' => 'BBB222',
            'seat_count' => 4,
            'floors' => 1,
        ]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create([
                'bus_id' => $this->bus2->id,
                'seat_number' => (string) $n,
                'is_active' => true,
                'floor' => '1',
            ]);
        }

        $this->routeIda = Route::create(['name' => 'Orán - Salta', 'bus_id' => $this->bus1->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $this->routeIda->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $this->routeIda->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);

        $this->routeVuelta = Route::create(['name' => 'Salta - Orán', 'bus_id' => $this->bus1->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $this->routeVuelta->id, 'location_id' => $this->salta->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $this->routeVuelta->id, 'location_id' => $this->oran->id, 'stop_order' => 2]);

        $this->scheduleIda = Schedule::create([
            'route_id' => $this->routeIda->id,
            'name' => 'Mañana',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $this->scheduleVuelta = Schedule::create([
            'route_id' => $this->routeVuelta->id,
            'name' => 'Tarde vuelta',
            'departure_time' => '17:00',
            'arrival_time' => '21:00',
            'is_active' => true,
        ]);

        // Admin autenticado por defecto
        $admin = \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $this->actingAs($admin);
    }

    private function createRoundTripSale(string $idaDate, string $vueltaDate, int $seatNumber = 1): Ticket
    {
        $sale = Sale::create([
            'user_id' => Auth::id(),
            'sale_date' => now(),
            'total_amount' => 100,
        ]);

        $passenger = \App\Models\Passenger::create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'dni' => '12345678',
            'passenger_type' => 'adult',
        ]);

        $tripIda = Trip::create([
            'route_id' => $this->routeIda->id,
            'schedule_id' => $this->scheduleIda->id,
            'bus_id' => $this->bus1->id,
            'trip_date' => $idaDate,
        ]);

        $tripVuelta = Trip::create([
            'route_id' => $this->routeVuelta->id,
            'schedule_id' => $this->scheduleVuelta->id,
            'bus_id' => $this->bus1->id,
            'trip_date' => $vueltaDate,
        ]);

        $seatIda = Seat::where('bus_id', $this->bus1->id)->where('seat_number', (string) $seatNumber)->first();
        $seatVuelta = Seat::where('bus_id', $this->bus1->id)->where('seat_number', (string) $seatNumber)->first();

        $ticketIda = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $tripIda->id,
            'seat_id' => $seatIda->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'return_trip_id' => $tripVuelta->id,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 100,
        ]);

        Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $tripVuelta->id,
            'seat_id' => $seatVuelta->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'return_trip_id' => null,
            'origin_location_id' => $this->salta->id,
            'destination_location_id' => $this->oran->id,
            'price' => 0,
        ]);

        return $ticketIda;
    }

    public function test_mueve_solo_la_ida_a_otra_fecha_y_horario(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
        ]);

        $ticket->refresh();

        $this->assertSame('2026-10-05', $ticket->trip->trip_date->format('Y-m-d'));
        $this->assertSame('3', (string) $ticket->seat->seat_number);
        $this->assertSame($this->scheduleIda->id, $ticket->trip->schedule_id);
        // El return_trip_id no cambia al mover solo la ida
        $this->assertSame('2026-10-08', $ticket->returnTrip->trip_date->format('Y-m-d'));

        // Auditoría: exactamente una fila, del tramo de ida
        $this->assertSame(1, $ticket->dateChanges()->count());
        $change = $ticket->dateChanges()->first();
        $this->assertSame('outbound', $change->leg);
        $this->assertSame($ticket->id, $change->ticket_id);
        $this->assertSame('2026-10-01', $change->fromTrip->trip_date->format('Y-m-d'));
        $this->assertSame('2026-10-05', $change->toTrip->trip_date->format('Y-m-d'));
    }

    public function test_mueve_solo_la_vuelta_y_sincroniza_return_trip_id(): void
    {
        $ticketIda = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        $ticketVuelta = Ticket::query()
            ->where('sale_id', $ticketIda->sale_id)
            ->whereNull('return_trip_id')
            ->first();

        $this->assertNotNull($ticketVuelta);

        $this->service->reschedule($ticketVuelta, [
            'scope' => 'outbound',
            'date' => '2026-10-20',
            'schedule_id' => $this->scheduleVuelta->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '2')->first()->id,
        ]);

        $ticketVuelta->refresh();
        $ticketIda->refresh();

        $this->assertSame('2026-10-20', $ticketVuelta->trip->trip_date->format('Y-m-d'));
        $this->assertSame('2', (string) $ticketVuelta->seat->seat_number);

        // return_trip_id del boleto de IDA apunta al nuevo viaje de vuelta
        $this->assertSame($ticketVuelta->trip_id, $ticketIda->return_trip_id);

        // Auditoría: fila en el ticket de vuelta con leg = return
        $this->assertSame(1, $ticketVuelta->dateChanges()->count());
        $this->assertSame('return', $ticketVuelta->dateChanges()->first()->leg);
        $this->assertTrue($ticketIda->wasReturnLegRescheduled());
    }

    public function test_la_ida_a_precio_0_de_una_venta_mixta_se_reprograma_como_ida(): void
    {
        $sale = Sale::create(['user_id' => Auth::id(), 'sale_date' => now(), 'total_amount' => 0]);
        $passenger = \App\Models\Passenger::create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'dni' => '12345678',
            'passenger_type' => 'adult',
        ]);
        $tripIda = Trip::create([
            'route_id' => $this->routeIda->id,
            'schedule_id' => $this->scheduleIda->id,
            'bus_id' => $this->bus1->id,
            'trip_date' => '2026-10-01',
        ]);
        $seat1 = Seat::where('bus_id', $this->bus1->id)->where('seat_number', '1')->first();
        $seat2 = Seat::where('bus_id', $this->bus1->id)->where('seat_number', '2')->first();

        // Venta mixta de cortesía: ida con fecha a precio 0 y vuelta pendiente.
        $ida = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $tripIda->id,
            'seat_id' => $seat1->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => false,
            'return_trip_id' => null,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 0,
        ]);
        $vuelta = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => null,
            'seat_id' => null,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => true,
            'return_trip_id' => null,
            'bus_id' => $this->bus1->id,
            'origin_location_id' => $this->salta->id,
            'destination_location_id' => $this->oran->id,
            'price' => 0,
        ]);

        $this->service->reschedule($ida, [
            'scope' => 'both',
            'date' => '2026-10-05',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => $seat1->id,
            'return_date' => '2026-10-12',
            'return_schedule_id' => $this->scheduleVuelta->id,
            'return_seat_id' => $seat2->id,
        ]);

        $ida->refresh();
        $vuelta->refresh();

        // La ida se movió como ida (misma dirección), no como tramo de vuelta.
        $this->assertSame('2026-10-05', $ida->trip->trip_date->format('Y-m-d'));
        $this->assertSame($this->scheduleIda->id, $ida->trip->schedule_id);
        $this->assertSame($this->oran->id, $ida->origin_location_id);
        $this->assertFalse((bool) $ida->is_return_leg);

        // La vuelta pendiente se encontró por la marca y recibió su fecha.
        $this->assertSame('2026-10-12', $vuelta->trip->trip_date->format('Y-m-d'));
        $this->assertSame($seat2->id, $vuelta->seat_id);
        $this->assertSame($vuelta->trip_id, $ida->return_trip_id);
    }

    public function test_mueve_ambos_tramos_con_auditoria_en_dos_filas(): void
    {
        $ticketIda = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        $this->service->reschedule($ticketIda, [
            'scope' => 'both',
            'date' => '2026-10-05',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '4')->first()->id,
            'return_date' => '2026-10-12',
            'return_schedule_id' => $this->scheduleVuelta->id,
            'return_seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '4')->first()->id,
        ]);

        $ticketIda->refresh();

        $ticketVuelta = Ticket::query()
            ->where('sale_id', $ticketIda->sale_id)
            ->whereNull('return_trip_id')
            ->first();

        $this->assertSame('2026-10-05', $ticketIda->trip->trip_date->format('Y-m-d'));
        $this->assertSame('2026-10-12', $ticketVuelta->trip->trip_date->format('Y-m-d'));
        $this->assertSame($ticketVuelta->trip_id, $ticketIda->return_trip_id);

        // Dos filas de auditoría: una por tramo
        $this->assertSame(2, Ticket::query()->where('sale_id', $ticketIda->sale_id)->get()->flatMap->dateChanges->count());
        $legs = $ticketIda->dateChanges()->pluck('leg')->merge($ticketVuelta->dateChanges()->pluck('leg'))->sort()->values();
        $this->assertEqualsCanonicalizing(['outbound', 'return'], $legs->all());
    }

    public function test_si_la_base_rechaza_el_asiento_por_una_venta_concurrente_informa_el_conflicto(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');
        $seat3 = Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first();

        // Simula otra venta que toma el asiento 3 del viaje destino justo
        // después del control de disponibilidad (la última consulta del
        // control es la de reservas vigentes) y antes de mover el boleto:
        // el rechazo lo da el índice único de la base, no el control previo.
        $inserted = false;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$inserted, $ticket, $seat3): void {
            if ($inserted || ! str_contains($query->sql, 'from "seat_reservations"') || ! str_contains($query->sql, '"expires_at" >')) {
                return;
            }

            $inserted = true;
            $competitor = $ticket->replicate(['share_token', 'active_seat_guard']);
            $competitor->trip_id = $query->bindings[0];
            $competitor->seat_id = $seat3->id;
            $competitor->return_trip_id = null;
            $competitor->saveQuietly();
        });

        try {
            $this->service->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $this->scheduleIda->id,
                'seat_id' => $seat3->id,
            ]);
            $this->fail('Debió informar el conflicto de asiento.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'El asiento acaba de ser vendido por otro usuario. Elegí otro asiento.',
                $e->errors()['seat_id'][0],
            );
        }

        $this->assertSame('2026-10-01', $ticket->refresh()->trip->trip_date->format('Y-m-d'), 'El boleto no debe moverse.');
    }

    // ====================== Días de servicio ======================

    public function test_sin_permiso_rechaza_mover_a_un_dia_sin_servicio(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        try {
            // Domingo 04/10/2026: sin servicio con la configuración inicial.
            $this->service->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-04',
                'schedule_id' => $this->scheduleIda->id,
                'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
            ]);
            $this->fail('Debió rechazar el día sin servicio.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('date', $e->errors());
            $this->assertStringContainsString('04/10/2026 es un día sin servicio', $e->errors()['date'][0]);
        }

        $this->assertSame('2026-10-01', $ticket->refresh()->trip->trip_date->format('Y-m-d'));
    }

    public function test_sin_permiso_rechaza_confirmar_la_misma_fecha_si_quedo_sin_servicio(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        \App\Models\Setting::set(\App\Models\Setting::SERVICE_EXCEPTIONS, json_encode([
            ['date' => '2026-10-01', 'has_service' => false, 'reason' => 'Paro'],
        ]));

        $this->expectException(ValidationException::class);

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-01',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
        ]);
    }

    public function test_sin_permiso_rechaza_una_vuelta_en_dia_sin_servicio(): void
    {
        $ticketIda = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        try {
            $this->service->reschedule($ticketIda, [
                'scope' => 'both',
                'date' => '2026-10-05',
                'schedule_id' => $this->scheduleIda->id,
                'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '4')->first()->id,
                'return_date' => '2026-10-11',
                'return_schedule_id' => $this->scheduleVuelta->id,
                'return_seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '4')->first()->id,
            ]);
            $this->fail('Debió rechazar la vuelta en domingo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('return_date', $e->errors());
        }

        $this->assertSame('2026-10-01', $ticketIda->refresh()->trip->trip_date->format('Y-m-d'));
    }

    public function test_con_permiso_mueve_a_un_dia_sin_servicio(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        \App\Support\Permissions::syncToDatabase();
        Auth::user()->givePermissionTo(\App\Support\ServiceDays::PERMISSION);

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-04',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
        ]);

        $this->assertSame('2026-10-04', $ticket->refresh()->trip->trip_date->format('Y-m-d'));
    }

    public function test_falla_si_el_asiento_destino_esta_ocupado_y_no_toca_nada(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        // Ocupar el asiento 2 del viaje destino (viaje ya existente el 2026-10-05 con asiento 2 vendido)
        $otroPasajero = \App\Models\Passenger::create([
            'first_name' => 'Otro',
            'last_name' => 'Pasajero',
            'dni' => '87654321',
            'passenger_type' => 'adult',
        ]);

        $tripDestino = Trip::create([
            'route_id' => $this->routeIda->id,
            'schedule_id' => $this->scheduleIda->id,
            'bus_id' => $this->bus1->id,
            'trip_date' => '2026-10-05',
        ]);

        Ticket::create([
            'sale_id' => $ticket->sale_id,
            'trip_id' => $tripDestino->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '2')->first()->id,
            'passenger_id' => $otroPasajero->id,
            'is_round_trip' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 100,
        ]);

        $originalTripId = $ticket->trip_id;
        $originalSeatId = $ticket->seat_id;

        try {
            $this->service->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $this->scheduleIda->id,
                'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '2')->first()->id,
            ]);
            $this->fail('Debió lanzar ValidationException por asiento ocupado.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no está disponible', implode(' ', $e->errors()['seat_id'] ?? ['']));
        }

        // El ticket no cambió (transacción revertida)
        $ticket->refresh();
        $this->assertSame($originalTripId, $ticket->trip_id);
        $this->assertSame($originalSeatId, $ticket->seat_id);
        $this->assertSame(0, $ticket->dateChanges()->count());
    }

    public function test_rechaza_vuelta_anterior_a_la_ida(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        // Vuelta el MISMO día a las 07:00 (antes de la ida a las 08:00)
        // Se usa otro horario de vuelta con salida previa a la de la ida.
        $scheduleVueltaTemprano = Schedule::create([
            'route_id' => $this->routeVuelta->id,
            'name' => 'Madrugada vuelta',
            'departure_time' => '07:00',
            'arrival_time' => '09:00',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('posterior');

        $this->service->reschedule($ticket, [
            'scope' => 'both',
            'date' => '2026-10-05',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '1')->first()->id,
            'return_date' => '2026-10-05',
            'return_schedule_id' => $scheduleVueltaTemprano->id,
            'return_seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '1')->first()->id,
        ]);
    }

    public function test_permite_mover_la_vuelta_a_fecha_pasada_si_la_ida_ya_viajo(): void
    {
        // Venta vieja: ida 2026-01-10 (ya pasó), vuelta 2026-01-15 (ya pasó).
        $ticketIda = $this->createRoundTripSale('2026-01-10', '2026-01-15');

        $ticketVuelta = Ticket::query()
            ->where('sale_id', $ticketIda->sale_id)
            ->whereNull('return_trip_id')
            ->first();

        // Mover SOLO la vuelta a otra fecha pasada: debe permitirse (no hay check-in).
        $this->service->reschedule($ticketVuelta, [
            'scope' => 'outbound',
            'date' => '2026-02-20',
            'schedule_id' => $this->scheduleVuelta->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '2')->first()->id,
        ]);

        $ticketVuelta->refresh();
        $this->assertSame('2026-02-20', $ticketVuelta->trip->trip_date->format('Y-m-d'));
    }

    public function test_rechaza_ticket_soft_deleted(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');
        $ticket->delete();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('eliminado');

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
        ]);
    }

    public function test_rechaza_usuario_sin_permiso_de_reprogramar(): void
    {
        // La reprogramación ya no se gatea por `is_admin` sino por el permiso
        // `tickets.reschedule`: un usuario sin ese permiso es rechazado.
        $vendedor = \App\Models\User::create([
            'name' => 'Vendedor',
            'email' => 'vendedor@test.local',
            'username' => 'vendedor',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $this->assertFalse($vendedor->can('tickets.reschedule'));

        $this->actingAs($vendedor);

        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');
        $originalTripId = $ticket->trip_id;

        try {
            $this->service->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $this->scheduleIda->id,
                'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
            ]);
            $this->fail('Un usuario sin tickets.reschedule no debió poder reprogramar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('user', $e->errors());
        }

        // El boleto no se movió.
        $this->assertSame($originalTripId, $ticket->refresh()->trip_id);
    }

    public function test_rechaza_horario_inactivo_sin_viaje_existente(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        $scheduleInactivo = Schedule::create([
            'route_id' => $this->routeIda->id,
            'name' => 'Suspendido',
            'departure_time' => '06:00',
            'arrival_time' => '10:00',
            'is_active' => false,
        ]);

        try {
            $this->service->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $scheduleInactivo->id,
                'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
            ]);
            $this->fail('Debió rechazar un horario inactivo sin viaje ya existente.');
        } catch (ValidationException $e) {
            // findOrCreateForBooking no crea viajes con horario inactivo → no hay viaje destino.
            $this->assertStringContainsString('No se pudo resolver el viaje', implode(' ', $e->errors()['schedule_id'] ?? ['']));
        }

        // El ticket quedó intacto.
        $ticket->refresh();
        $this->assertSame('2026-10-01', $ticket->trip->trip_date->format('Y-m-d'));
    }

    public function test_mueve_a_colectivo_distinto_con_mismo_numero_de_asiento(): void
    {
        // El viaje destino usa bus2 (misma numeración 1-4): el asiento "1" existe y está libre.
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        $scheduleBus2 = Schedule::create([
            'route_id' => $this->routeIda->id, // misma ruta, otra vía: crear ruta en bus2
            'name' => 'Noche bus2',
            'departure_time' => '23:00',
            'arrival_time' => '23:59',
            'is_active' => true,
        ]);

        // Forzar que findOrCreate cree el viaje con bus2: la ruta del schedule debe ser de bus2
        $routeIdaBus2 = Route::create(['name' => 'Orán - Salta B2', 'bus_id' => $this->bus2->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeIdaBus2->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeIdaBus2->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);

        $scheduleBus2->update(['route_id' => $routeIdaBus2->id]);

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $scheduleBus2->id,
            'seat_id' => Seat::where('bus_id', $this->bus2->id)->where('seat_number', '1')->first()->id,
        ]);

        $ticket->refresh();

        $this->assertSame($this->bus2->id, $ticket->trip->bus_id);
        $this->assertSame('1', (string) $ticket->seat->seat_number);
        $this->assertTrue(Seat::where('id', $ticket->seat_id)->first()->bus_id === $this->bus2->id);
    }

    public function test_rechaza_asiento_de_otro_colectivo(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        // Viaje destino en bus2 (mismo schedule de bus1 no aplica: se crea schedule de bus2),
        // pero se envía un seat de bus1.
        $routeIdaBus2 = Route::create(['name' => 'Orán - Salta B2b', 'bus_id' => $this->bus2->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $routeIdaBus2->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $routeIdaBus2->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);

        $scheduleBus2 = Schedule::create([
            'route_id' => $routeIdaBus2->id,
            'name' => 'Noche bus2',
            'departure_time' => '23:00',
            'arrival_time' => '23:59',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no pertenece');

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $scheduleBus2->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
        ]);
    }

    public function test_puede_quedarse_en_el_mismo_asiento_en_el_mismo_viaje_cambiando_solo_horario(): void
    {
        // Caso especial: mismo bus numerado igual (bus1 en ambos horarios), se mueve
        // de un horario a otro y pide el mismo número de asiento.
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        $scheduleNoche = Schedule::create([
            'route_id' => $this->routeIda->id,
            'name' => 'Noche',
            'departure_time' => '22:00',
            'arrival_time' => '23:30',
            'is_active' => true,
        ]);

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $scheduleNoche->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '1')->first()->id,
        ]);

        $ticket->refresh();

        $this->assertSame('22:00', $ticket->trip->schedule->departure_time->format('H:i'));
        $this->assertSame('1', (string) $ticket->seat->seat_number);
    }

    public function test_liberacion_de_reservas_de_la_sesion(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');

        $sessionId = session()->getId();

        // Reserva temporal colgada de la sesión actual (simula el selector)
        \App\Models\SeatReservation::create([
            'trip_id' => $ticket->trip_id,
            'seat_id' => $ticket->seat_id,
            'user_session_id' => $sessionId,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
        ]);

        $this->assertSame(0, \App\Models\SeatReservation::where('user_session_id', $sessionId)->count());
    }

    // ====================== Una persona por viaje ======================

    private function tripFor(Schedule $schedule, Route $route, string $date): Trip
    {
        return Trip::firstOrCreate(
            ['schedule_id' => $schedule->id, 'trip_date' => $date],
            ['route_id' => $route->id, 'bus_id' => $this->bus1->id],
        );
    }

    private function sellOtherTicket(Trip $trip, string $dni, string $seatNumber = '4'): Ticket
    {
        $sale = Sale::create(['user_id' => Auth::id(), 'sale_date' => now(), 'total_amount' => 100]);
        $passenger = \App\Models\Passenger::create([
            'first_name' => 'Otro',
            'last_name' => 'Pasajero',
            'dni' => $dni,
            'passenger_type' => 'adult',
        ]);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'seat_id' => Seat::where('bus_id', $trip->bus_id)->where('seat_number', $seatNumber)->first()->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 100,
        ]);
    }

    private function assertDuplicateRejected(callable $callback): \App\Exceptions\DuplicatePassengerOnTripException
    {
        try {
            $callback();
        } catch (\App\Exceptions\DuplicatePassengerOnTripException $e) {
            return $e;
        }

        $this->fail('La reprogramación debió rechazarse por duplicado.');
    }

    public function test_rechaza_mover_a_un_viaje_donde_el_dni_ya_tiene_otro_boleto(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');
        $other = $this->sellOtherTicket($this->tripFor($this->scheduleIda, $this->routeIda, '2026-10-05'), '12345678');

        $exception = $this->assertDuplicateRejected(fn () => $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
        ]));

        $this->assertSame([$other->id], $exception->conflicts()->map(fn (array $conflict): int => $conflict['ticket']->id)->all());
        $this->assertSame('2026-10-01', $ticket->fresh()->trip->trip_date->format('Y-m-d'));
        $this->assertSame(0, $ticket->dateChanges()->count());
    }

    public function test_cambiar_de_asiento_en_el_mismo_viaje_no_cuenta_como_duplicado(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');
        // Duplicado previo a la regla en el mismo viaje: no bloquea un cambio de asiento.
        $this->sellOtherTicket($ticket->trip, '12345678');

        $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-01',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
        ]);

        $this->assertSame('3', (string) $ticket->fresh()->seat->seat_number);
    }

    public function test_ambos_tramos_con_conflicto_solo_en_la_vuelta_no_mueve_ninguno(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');
        $this->sellOtherTicket($this->tripFor($this->scheduleVuelta, $this->routeVuelta, '2026-10-20'), '12345678');

        $this->assertDuplicateRejected(fn () => $this->service->reschedule($ticket, [
            'scope' => 'both',
            'date' => '2026-10-05',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '1')->first()->id,
            'return_date' => '2026-10-20',
            'return_schedule_id' => $this->scheduleVuelta->id,
            'return_seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '1')->first()->id,
        ]));

        $ticket->refresh();
        $this->assertSame('2026-10-01', $ticket->trip->trip_date->format('Y-m-d'));
        $this->assertSame('2026-10-08', $ticket->returnTrip->trip_date->format('Y-m-d'));
        $this->assertSame(0, \App\Models\TicketDateChange::query()
            ->whereIn('ticket_id', Ticket::query()->where('sale_id', $ticket->sale_id)->pluck('id'))
            ->count());
    }

    public function test_rechaza_asignar_el_primer_viaje_si_el_dni_ya_viaja(): void
    {
        $sale = Sale::create(['user_id' => Auth::id(), 'sale_date' => now(), 'total_amount' => 100]);
        $passenger = \App\Models\Passenger::create(['first_name' => 'Juan', 'last_name' => 'Pérez', 'dni' => '12345678', 'passenger_type' => 'adult']);
        $pending = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => null,
            'seat_id' => null,
            'bus_id' => $this->bus1->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 100,
        ]);
        $this->sellOtherTicket($this->tripFor($this->scheduleIda, $this->routeIda, '2026-10-05'), '12345678');

        $this->assertDuplicateRejected(fn () => $this->service->reschedule($pending, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '1')->first()->id,
        ]));

        $this->assertNull($pending->fresh()->trip_id);
    }

    public function test_rechaza_mover_si_el_menor_ya_viaja_en_el_destino(): void
    {
        $ticket = $this->createRoundTripSale('2026-10-01', '2026-10-08');
        \App\Models\Passenger::create([
            'first_name' => 'Nene',
            'last_name' => 'Pérez',
            'dni' => '45123456',
            'parent_passenger_id' => $ticket->passenger_id,
            'passenger_type' => 'child',
        ]);
        $this->sellOtherTicket($this->tripFor($this->scheduleIda, $this->routeIda, '2026-10-05'), '45123456');

        $this->assertDuplicateRejected(fn () => $this->service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $this->scheduleIda->id,
            'seat_id' => Seat::where('bus_id', $this->bus1->id)->where('seat_number', '3')->first()->id,
        ]));

        $this->assertSame('2026-10-01', $ticket->fresh()->trip->trip_date->format('Y-m-d'));
    }
}
