<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingTicketPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(\Carbon\Carbon::parse('2026-10-01 09:00:00'));

        $this->seller = User::factory()->vendedor()->create();
        $this->seller->givePermissionTo('tickets.vender_sin_fecha');
    }

    /** Boleto vendido sin fecha (trip_id NULL): el estado previo de la venta. */
    private function createPendingTicket(): Ticket
    {
        $tartagal = Location::create(['name' => 'Tartagal', 'is_active' => true]);
        $urundel = Location::create(['name' => 'Urundel', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea SF', 'plate' => 'SFSF11', 'seat_count' => 4, 'floors' => 1]);

        $sale = Sale::create([
            'user_id' => $this->seller->id,
            'sale_date' => now(),
            'total_amount' => 15000,
        ]);

        $passenger = Passenger::create([
            'first_name' => 'Sin',
            'last_name' => 'Fecha',
            'dni' => '11223344',
            'passenger_type' => 'adult',
        ]);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => null,
            'seat_id' => null,
            'return_trip_id' => null,
            'bus_id' => $bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $tartagal->id,
            'destination_location_id' => $urundel->id,
            'price' => 15000,
        ]);
    }

    private function renderPdfHtml(Ticket $ticket): string
    {
        return view('tickets.pdf.passenger-tickets', [
            'sale' => $ticket->sale,
            'tickets' => collect([$ticket]),
            'passenger' => $ticket->passenger,
            'hasChild' => false,
        ])->render();
    }

    public function test_el_pdf_del_boleto_sin_fecha_muestra_el_cartel_de_fecha_pendiente(): void
    {
        $ticket = $this->createPendingTicket();
        $html = $this->renderPdfHtml($ticket);

        // El cartel (banner + leyenda) reemplaza a los placeholders.
        $this->assertStringContainsString('FECHA Y HORARIO A CONFIRMAR', $html);
        $this->assertStringContainsString('queda en responsabilidad del pasajero', $html);
        // Redacción vigente desde baa13d1 (baa13d1 cambió el texto del cartel:
        // antes "sin aplicar recargos" / "la unidad cuente").
        $this->assertStringContainsString('no se aplicaran recargos', $html);
        $this->assertStringContainsString('cuenta con asientos disponibles', $html);

        // Ya no se imprimen los campos "A confirmar" / "A asignar".
        $this->assertStringNotContainsString('A confirmar', $html);
        $this->assertStringNotContainsString('A asignar', $html);
        $this->assertStringNotContainsString('Fecha del viaje:', $html);
    }

    public function test_el_pdf_del_boleto_con_fecha_no_muestra_el_cartel(): void
    {
        $ticket = $this->createPendingTicket();

        // Asignarle viaje real: ahora es un boleto normal.
        $bus = Bus::findOrFail($ticket->bus_id);
        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);
        $route = \App\Models\Route::create(['name' => 'Tartagal - Urundel', 'bus_id' => $bus->id, 'is_active' => true]);
        \App\Models\RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        \App\Models\RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);
        $schedule = \App\Models\Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
        $trip = \App\Models\Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-05',
        ]);

        $ticket->update([
            'trip_id' => $trip->id,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
        ]);

        $html = $this->renderPdfHtml($ticket->fresh());

        $this->assertStringNotContainsString('FECHA Y HORARIO A CONFIRMAR', $html);
        $this->assertStringNotContainsString('responsabilidad del pasajero', $html);
        $this->assertStringContainsString('Fecha del viaje:', $html);
    }

    public function test_el_pdf_de_un_diferido_sin_fecha_rotula_ida_y_vuelta(): void
    {
        $ida = $this->createPendingTicket();
        $ida->update(['is_round_trip' => true]);
        $vuelta = $this->createPendingReturnFor($ida);

        $html = $this->renderPdfHtmlFor(collect([$ida->fresh(), $vuelta]));

        $this->assertSame(1, preg_match_all("/class='leg-label'>IDA</", $html));
        $this->assertSame(1, preg_match_all("/class='leg-label'>VUELTA</", $html));
        // La vuelta ya no muestra el N° de la ida (confundía al pasajero).
        $this->assertStringNotContainsString('VUELTA de N°', $html);
        $this->assertSame(2, substr_count($html, "class='leg-label'"));
    }

    public function test_el_pdf_de_la_venta_rotula_cada_tramo_de_cada_pasajero(): void
    {
        $idaUno = $this->createPendingTicket();
        $idaUno->update(['is_round_trip' => true]);

        $otroPasajero = Passenger::create([
            'first_name' => 'Otro',
            'last_name' => 'Pasajero',
            'dni' => '55667788',
            'passenger_type' => 'adult',
        ]);
        $idaDos = $idaUno->replicate(['share_token']);
        $idaDos->passenger_id = $otroPasajero->id;
        $idaDos->save();

        $vueltaUno = $this->createPendingReturnFor($idaUno);
        $vueltaDos = $this->createPendingReturnFor($idaDos);

        $html = $this->renderPdfHtmlFor(collect([$idaUno->fresh(), $idaDos, $vueltaUno, $vueltaDos]));

        $this->assertSame(2, preg_match_all("/class='leg-label'>IDA</", $html));
        $this->assertSame(2, preg_match_all("/class='leg-label'>VUELTA</", $html));
        $this->assertStringNotContainsString('VUELTA de N°', $html);
    }

    public function test_el_pdf_de_un_boleto_solo_de_ida_no_lleva_etiqueta(): void
    {
        $html = $this->renderPdfHtml($this->createPendingTicket());

        $this->assertStringNotContainsString("class='leg-label'", $html);
    }

    public function test_cada_ida_va_en_la_misma_hoja_que_su_vuelta(): void
    {
        // Orden de emisión real: ida 1, ida 2, vuelta 1, vuelta 2.
        $idaUno = $this->createPendingTicket();
        $idaUno->update(['is_round_trip' => true]);

        $otroPasajero = Passenger::create([
            'first_name' => 'Otro',
            'last_name' => 'Pasajero',
            'dni' => '55667788',
            'passenger_type' => 'adult',
        ]);
        $idaDos = $idaUno->replicate(['share_token']);
        $idaDos->passenger_id = $otroPasajero->id;
        $idaDos->save();

        $vueltaUno = $this->createPendingReturnFor($idaUno);
        $vueltaDos = $this->createPendingReturnFor($idaDos);

        $sheets = TicketPdfService::sheets(collect([$idaUno->fresh(), $idaDos, $vueltaUno, $vueltaDos]));

        $this->assertSame(
            [[$idaUno->id, $vueltaUno->id], [$idaDos->id, $vueltaDos->id]],
            $sheets->map(fn ($sheet) => $sheet->pluck('id')->all())->all(),
        );

        // En el HTML: la vuelta 1 aparece antes que la ida 2.
        $html = $this->renderPdfHtmlFor(collect([$idaUno->fresh(), $idaDos, $vueltaUno, $vueltaDos]));
        $this->assertLessThan(
            strpos($html, $this->ticketNumber($idaDos)),
            strpos($html, $this->ticketNumber($vueltaUno)),
        );
        $this->assertSame(2, substr_count($html, "<div class='sheet'>"));
    }

    public function test_una_venta_solo_de_ida_sigue_de_a_dos_por_hoja(): void
    {
        $first = $this->createPendingTicket();
        $tickets = collect([$first]);

        foreach (['22000001', '22000002', '22000003'] as $dni) {
            $copy = $first->replicate(['share_token']);
            $copy->passenger_id = Passenger::create([
                'first_name' => 'Solo',
                'last_name' => 'Ida',
                'dni' => $dni,
                'passenger_type' => 'adult',
            ])->id;
            $copy->save();
            $tickets->push($copy);
        }

        $sheets = TicketPdfService::sheets($tickets);

        $this->assertSame(
            [$tickets->take(2)->pluck('id')->all(), $tickets->slice(2)->pluck('id')->values()->all()],
            $sheets->map(fn ($sheet) => $sheet->pluck('id')->all())->all(),
        );
    }

    /** Vuelta pendiente de un diferido, como la emite la venta sin fecha. */
    private function createPendingReturnFor(Ticket $ida): Ticket
    {
        return Ticket::create([
            'sale_id' => $ida->sale_id,
            'trip_id' => null,
            'seat_id' => null,
            'return_trip_id' => null,
            'bus_id' => $ida->bus_id,
            'passenger_id' => $ida->passenger_id,
            'is_round_trip' => true,
            'is_return_leg' => true,
            'origin_location_id' => $ida->destination_location_id,
            'destination_location_id' => $ida->origin_location_id,
            'price' => 0,
        ]);
    }

    private function renderPdfHtmlFor(\Illuminate\Support\Collection $tickets): string
    {
        return view('tickets.pdf.passenger-tickets', [
            'sale' => $tickets->first()->sale,
            'tickets' => $tickets,
            'passenger' => $tickets->first()->passenger,
            'hasChild' => false,
        ])->render();
    }

    public function test_el_servicio_genera_el_pdf_del_boleto_sin_fecha(): void
    {
        $ticket = $this->createPendingTicket();

        $pdf = (new TicketPdfService)->generatePassengerTickets(
            $ticket->sale,
            collect([$ticket]),
        );

        $this->assertNotEmpty($pdf);
        $this->assertSame('%PDF', substr($pdf, 0, 4));
    }

    /** N° de boleto tal como lo imprime el PDF: marcador único de cada boleto. */
    private function ticketNumber(Ticket $ticket): string
    {
        return 'BOLETO N°'.str_pad((string) $ticket->id, 10, '0', STR_PAD_LEFT);
    }
}
