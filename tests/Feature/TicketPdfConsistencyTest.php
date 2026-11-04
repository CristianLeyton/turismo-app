<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\RescheduleTicket;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Bus;
use App\Models\Clients;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Services\TicketPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Qué PDF le corresponde a un boleto y cómo se llama el archivo: tiene que
 * ser lo mismo desde cualquier acceso (panel, tabla, reprogramación, link
 * público y panel de clientes).
 *
 * La variante se verifica comparando el contenido del PDF (sin los metadatos
 * de fecha) con el que devuelve directamente el servicio.
 */
class TicketPdfConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private Location $oran;

    private Location $salta;

    private Bus $bus;

    private Schedule $schedule;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();

        $this->oran = Location::create(['name' => 'Orán C', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta C', 'is_active' => true]);

        $this->bus = Bus::create(['name' => 'Coche 3', 'plate' => 'CCC111', 'seat_count' => 4, 'floors' => 1]);
        foreach (range(1, 4) as $number) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $number, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta C', 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);

        $this->schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana C',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->superAdmin()->create();
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Nombre del archivo
    // ------------------------------------------------------------------

    public function test_nombre_de_un_boleto_de_ida_y_vuelta_usa_el_colectivo(): void
    {
        [$ida] = $this->roundTripOnePassenger();

        $this->assertSame("Boleto_N°{$ida->id}_Coche_3.pdf", $this->expectedFilename($ida));
    }

    public function test_nombre_de_un_boleto_solo_de_ida_en_venta_de_dos_pasajeros(): void
    {
        [$first] = $this->oneWayTwoPassengers();

        $this->assertSame("Boleto_N°{$first->id}_Coche_3.pdf", $this->expectedFilename($first));
    }

    public function test_nombre_de_un_boleto_sin_fecha_usa_sin_fecha(): void
    {
        $ticket = $this->undatedTicket();

        $this->assertSame("Boleto_N°{$ticket->id}_Sin_fecha.pdf", $this->expectedFilename($ticket));
    }

    // ------------------------------------------------------------------
    // Cada acceso actual entrega la misma variante y el mismo nombre
    // ------------------------------------------------------------------

    public function test_ver_boleto_descarga_la_variante_y_el_nombre_del_boleto(): void
    {
        $this->actingAs($this->admin);

        foreach ($this->fixtures() as $ticket) {
            $component = Livewire::test(ViewTicket::class, ['record' => $ticket->id])
                ->callAction('download_pdf')
                ->assertFileDownloaded($this->expectedFilename($ticket));

            $this->assertPdfMatchesExpected($ticket, $this->downloadedContent($component));
        }
    }

    public function test_la_tabla_descarga_la_variante_y_el_nombre_del_boleto(): void
    {
        $this->actingAs($this->admin);

        foreach ($this->fixtures() as $ticket) {
            $component = Livewire::test(ListTickets::class)
                ->callTableAction('download_pdf', $ticket)
                ->assertFileDownloaded($this->expectedFilename($ticket));

            $this->assertPdfMatchesExpected($ticket, $this->downloadedContent($component));
        }
    }

    public function test_el_link_publico_entrega_la_variante_y_el_nombre_del_boleto(): void
    {
        foreach ($this->fixtures() as $ticket) {
            $response = $this->get($ticket->sharePdfUrl('attachment'))->assertOk();

            $this->assertSame(
                'attachment; filename="'.rawurlencode($this->expectedFilename($ticket)).'"',
                $response->headers->get('Content-Disposition'),
            );
            $this->assertPdfMatchesExpected($ticket, $response->getContent());
        }
    }

    public function test_el_panel_de_clientes_entrega_la_variante_y_el_nombre_del_boleto(): void
    {
        foreach ($this->fixtures() as $ticket) {
            $client = Clients::query()->firstOrCreate(
                ['dni' => $ticket->passenger->dni],
                ['nombre' => 'Cliente', 'apellido' => 'Prueba'],
            );
            $this->actingAs($client, 'client');

            $response = $this->get(route('clientes.boletos.pdf', ['ticket' => $ticket->id]));

            $response->assertOk();
            $this->assertSame(
                'inline; filename="'.rawurlencode($this->expectedFilename($ticket)).'"',
                $response->headers->get('Content-Disposition'),
            );
            $this->assertPdfMatchesExpected($ticket, $response->getContent());
        }
    }

    public function test_reprogramar_con_descarga_automatica_deja_la_variante_y_el_nombre_en_sesion(): void
    {
        $this->actingAs($this->admin);
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '1');

        [$ida] = $this->roundTripOnePassenger();
        $newTrip = Trip::create([
            'route_id' => $this->schedule->route_id,
            'schedule_id' => $this->schedule->id,
            'bus_id' => $this->bus->id,
            'trip_date' => '2030-10-05',
        ]);

        Livewire::test(RescheduleTicket::class, ['record' => $ida->id])
            ->set('data.date', '2030-10-05')
            ->set('data.schedule_id', $this->schedule->id)
            ->set('data.seat_ids', [$this->seat('3')->id])
            ->call('confirmReschedule');

        $fresh = $ida->fresh();
        $this->assertSame($newTrip->id, (int) $fresh->trip_id);
        $this->assertSame($this->expectedFilename($fresh), session('ticket_pdf_filename'));
        $this->assertPdfMatchesExpected($fresh, base64_decode((string) session('ticket_pdf_content')));
    }

    public function test_las_dos_variantes_del_servicio_producen_pdf_distintos(): void
    {
        [$ida] = $this->roundTripOnePassenger();
        $service = new TicketPdfService;

        $roundTrip = $service->generateRoundTripTicket($ida->sale, $ida->sale->tickets()->get());
        $individual = $service->generatePassengerTickets($ida->sale, collect([$ida]));

        $this->assertNotSame($this->normalize($roundTrip), $this->normalize($individual));
    }

    // ------------------------------------------------------------------
    // Fuente única en el servicio
    // ------------------------------------------------------------------

    public function test_for_ticket_devuelve_contenido_y_nombre_de_cada_variante(): void
    {
        foreach ($this->fixtures() as $ticket) {
            $result = app(TicketPdfService::class)->forTicket($ticket);

            $this->assertSame($this->expectedFilename($ticket), $result['filename']);
            $this->assertPdfMatchesExpected($ticket, $result['content']);
        }
    }

    public function test_filename_for_cubre_colectivo_espacios_y_sin_fecha(): void
    {
        [$ida] = $this->roundTripOnePassenger();
        $undated = $this->undatedTicket();

        $this->assertSame("Boleto_N°{$ida->id}_Coche_3.pdf", TicketPdfService::filenameFor($ida));
        $this->assertSame("Boleto_N°{$undated->id}_Sin_fecha.pdf", TicketPdfService::filenameFor($undated));
    }

    public function test_for_ticket_de_ida_y_vuelta_solo_incluye_los_tramos_del_pasajero(): void
    {
        [$ida, , $other] = $this->roundTripTwoPassengers();

        $service = app(TicketPdfService::class);
        $passengerTickets = $ida->sale->tickets()->where('passenger_id', $ida->passenger_id)->get();

        $this->assertCount(2, $passengerTickets);
        $this->assertSame(
            $this->normalize($service->generateRoundTripTicket($ida->sale, $passengerTickets)),
            $this->normalize($service->forTicket($ida)['content']),
        );
        $this->assertNotSame($ida->passenger_id, $other->passenger_id);
    }

    // ------------------------------------------------------------------
    // Fixtures y helpers
    // ------------------------------------------------------------------

    /**
     * @return array<int, Ticket>
     */
    private function fixtures(): array
    {
        [$roundTripOutbound] = $this->roundTripOnePassenger();
        [$oneWayFirst] = $this->oneWayTwoPassengers();

        return [$roundTripOutbound, $oneWayFirst, $this->undatedTicket()];
    }

    private function expectedFilename(Ticket $ticket): string
    {
        $colectivo = str_replace(' ', '_', $ticket->trip?->bus?->name ?? 'Sin fecha');

        return "Boleto_N°{$ticket->id}_{$colectivo}.pdf";
    }

    private function assertPdfMatchesExpected(Ticket $ticket, string $content): void
    {
        $service = new TicketPdfService;
        $ticket = $ticket->fresh();

        $expected = $ticket->is_round_trip
            ? $service->generateRoundTripTicket(
                $ticket->sale,
                $ticket->sale->tickets()->where('passenger_id', $ticket->passenger_id)->get(),
            )
            : $service->generatePassengerTickets($ticket->sale, collect([$ticket]));

        $this->assertStringStartsWith('%PDF', $content);
        $this->assertSame($this->normalize($expected), $this->normalize($content));
    }

    /**
     * Quita los metadatos que cambian en cada generación (fechas e ID).
     */
    private function normalize(string $pdf): string
    {
        $pdf = preg_replace('/\/(CreationDate|ModDate) \([^)]*\)/', '', $pdf) ?? $pdf;
        $pdf = preg_replace('/\/ID ?\[[^\]]*\]/', '', $pdf) ?? $pdf;

        return $pdf;
    }

    private function downloadedContent(mixed $component): string
    {
        $download = $component->effects['download'] ?? null;

        $this->assertNotNull($download, 'La acción no devolvió una descarga.');

        return base64_decode((string) $download['content']);
    }

    private function newSale(): Sale
    {
        return Sale::create([
            'user_id' => $this->admin->id,
            'sale_date' => now(),
            'total_amount' => 15000,
        ]);
    }

    private function passenger(string $dni): Passenger
    {
        return Passenger::create([
            'first_name' => 'Juan',
            'last_name' => 'Consistencia'.$dni,
            'dni' => $dni,
            'passenger_type' => 'adult',
        ]);
    }

    private function trip(string $date): Trip
    {
        return Trip::query()->firstOrCreate([
            'route_id' => $this->schedule->route_id,
            'schedule_id' => $this->schedule->id,
            'bus_id' => $this->bus->id,
            'trip_date' => $date,
        ]);
    }

    private function seat(string $number): Seat
    {
        return Seat::query()->where('bus_id', $this->bus->id)->where('seat_number', $number)->firstOrFail();
    }

    /**
     * @return array{0: Ticket, 1: Ticket}
     */
    private function roundTripOnePassenger(): array
    {
        $sale = $this->newSale();
        $passenger = $this->passenger('30000001');

        return $this->roundTripFor($sale, $passenger, '1', '2');
    }

    /**
     * @return array{0: Ticket, 1: Ticket, 2: Ticket}
     */
    private function roundTripTwoPassengers(): array
    {
        $sale = $this->newSale();
        [$ida, $vuelta] = $this->roundTripFor($sale, $this->passenger('30000002'), '1', '2');
        [$otherIda] = $this->roundTripFor($sale, $this->passenger('30000003'), '3', '4');

        return [$ida, $vuelta, $otherIda];
    }

    /**
     * @return array{0: Ticket, 1: Ticket}
     */
    private function roundTripFor(Sale $sale, Passenger $passenger, string $idaSeat, string $vueltaSeat): array
    {
        $outbound = $this->trip('2030-10-01');
        $return = $this->trip('2030-10-08');

        $ida = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $outbound->id,
            'seat_id' => $this->seat($idaSeat)->id,
            'return_trip_id' => $return->id,
            'bus_id' => $this->bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 15000,
        ]);

        $vuelta = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $return->id,
            'seat_id' => $this->seat($vueltaSeat)->id,
            'bus_id' => $this->bus->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'is_return_leg' => true,
            'origin_location_id' => $this->salta->id,
            'destination_location_id' => $this->oran->id,
            'price' => 0,
        ]);

        return [$ida, $vuelta];
    }

    /**
     * @return array{0: Ticket, 1: Ticket}
     */
    private function oneWayTwoPassengers(): array
    {
        $sale = $this->newSale();
        $trip = $this->trip('2030-11-01');

        $tickets = [];

        foreach (['30000011' => '1', '30000012' => '2'] as $dni => $seat) {
            $tickets[] = Ticket::create([
                'sale_id' => $sale->id,
                'trip_id' => $trip->id,
                'seat_id' => $this->seat($seat)->id,
                'bus_id' => $this->bus->id,
                'passenger_id' => $this->passenger($dni)->id,
                'is_round_trip' => false,
                'is_return_leg' => false,
                'origin_location_id' => $this->oran->id,
                'destination_location_id' => $this->salta->id,
                'price' => 15000,
            ]);
        }

        return $tickets;
    }

    private function undatedTicket(): Ticket
    {
        return Ticket::create([
            'sale_id' => $this->newSale()->id,
            'trip_id' => null,
            'seat_id' => null,
            'bus_id' => $this->bus->id,
            'passenger_id' => $this->passenger('30000021')->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 15000,
        ]);
    }
}
