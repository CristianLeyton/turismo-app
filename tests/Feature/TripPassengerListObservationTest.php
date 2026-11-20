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
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lista de pasajeros de un viaje (modal "Ver detalles", PDF y Excel):
 * columna Observación al final, Teléfono oculto en modal y PDF pero no en
 * el Excel (spec trip-passenger-list).
 */
class TripPassengerListObservationTest extends TestCase
{
    use RefreshDatabase;

    private const OBSERVATION = 'Baja en el peaje de Metán';

    private Trip $trip;

    private Ticket $withObservation;

    private Ticket $withoutObservation;

    protected function tearDown(): void
    {
        // La caché de Setting es estática: sin esto el parámetro prendido
        // pasaría a otros tests del mismo proceso.
        Setting::flushCache();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Las observaciones arrancan apagadas: estos tests las prenden y
        // los casos con el parámetro apagado lo apagan explícitamente.
        Setting::flushCache();
        Setting::set(Setting::OBSERVACIONES_VENTA, '1');

        $oran = Location::create(['name' => 'Orán L', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta L', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Coche L', 'plate' => 'LLL111', 'seat_count' => 4, 'floors' => 1]);
        $seat1 = Seat::create(['bus_id' => $bus->id, 'seat_number' => '1', 'is_active' => true, 'floor' => '1']);
        $seat2 = Seat::create(['bus_id' => $bus->id, 'seat_number' => '2', 'is_active' => true, 'floor' => '1']);

        $route = Route::create(['name' => 'Orán - Salta L', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana L',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $this->trip = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2030-10-01',
        ]);

        $sale = Sale::create([
            'user_id' => User::factory()->vendedor()->create()->id,
            'sale_date' => now(),
            'total_amount' => 30000,
        ]);

        // Adulto con menor acompañante y observación.
        $adult = Passenger::create([
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'dni' => '28333444',
            'phone_number' => '3815550001',
            'passenger_type' => 'adult',
        ]);
        Passenger::create([
            'first_name' => 'Tomás',
            'last_name' => 'Gómez',
            'dni' => '50111222',
            'passenger_type' => 'child',
            'parent_passenger_id' => $adult->id,
        ]);

        $this->withObservation = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $this->trip->id,
            'seat_id' => $seat1->id,
            'passenger_id' => $adult->id,
            'travels_with_child' => true,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 15000,
            'observation' => self::OBSERVATION,
        ]);

        $this->withoutObservation = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $this->trip->id,
            'seat_id' => $seat2->id,
            'passenger_id' => Passenger::create([
                'first_name' => 'Luis',
                'last_name' => 'Díaz',
                'dni' => '31555666',
                'phone_number' => '3815559999',
                'passenger_type' => 'adult',
            ])->id,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 15000,
        ]);
    }

    // ====================== Datos ======================

    public function test_la_observacion_va_solo_en_la_fila_del_adulto(): void
    {
        $rows = $this->trip->getPassengersWithDetails();

        $adultRow = $rows->first(fn (array $row): bool => $row['type'] === 'adult' && $row['ticket_id'] === $this->withObservation->id);
        $childRow = $rows->firstWhere('type', 'child');

        $this->assertSame(self::OBSERVATION, $adultRow['observation']);
        $this->assertNotNull($childRow);
        $this->assertNull($childRow['observation'] ?? null);
    }

    // ====================== Modal ======================

    public function test_el_modal_muestra_observacion_al_final_y_oculta_telefono(): void
    {
        $html = $this->renderModal();

        $this->assertStringNotContainsString('Teléfono', $html);
        $this->assertStringNotContainsString('3815550001', $html);
        $this->assertSeeInOrderHtml($html, ['Boleto N°', 'Nombre', 'DNI', 'Asiento', 'Origen', 'Destino', 'Observación']);
    }

    public function test_el_modal_muestra_la_observacion_una_sola_vez_y_destacada(): void
    {
        $html = $this->renderModal();

        $this->assertSame(1, substr_count($html, self::OBSERVATION));
        $this->assertStringContainsString('bg-amber-50', $html);
        $this->assertSame(1, substr_count($html, 'bg-amber-50'), 'Solo la celda con observación va destacada.');
        // Recuadro redondeado, como en el detalle del boleto.
        $this->assertMatchesRegularExpression('/<div\s+class="rounded-lg border-l-4 border-amber-400 bg-amber-50[^"]*">'.preg_quote(e(self::OBSERVATION), '/').'<\/div>/', $html);
    }

    public function test_el_modal_muestra_un_guion_para_el_pasajero_sin_observacion(): void
    {
        $html = $this->renderModal();

        $this->assertMatchesRegularExpression('/text-gray-400 dark:text-gray-500">-<\/td>/', $html);
    }

    public function test_el_modal_puede_volver_a_mostrar_el_telefono(): void
    {
        $html = view('filament.trips.trip-details', [
            'trip' => $this->trip,
            'passengers' => $this->trip->getPassengersWithDetails(),
            'showPhone' => true,
        ])->render();

        $this->assertStringContainsString('Teléfono', $html);
        $this->assertStringContainsString('3815550001', $html);
    }

    // ====================== PDF ======================

    public function test_el_pdf_muestra_observacion_y_oculta_telefono(): void
    {
        $html = $this->renderPdf();

        $this->assertStringNotContainsString('Teléfono', $html);
        $this->assertStringNotContainsString('3815550001', $html);
        $this->assertSame(1, substr_count($html, self::OBSERVATION));
        // Recuadro redondeado, como en el detalle del boleto.
        $this->assertStringContainsString('<td><div class="observation">'.e(self::OBSERVATION).'</div></td>', $html);
        $this->assertMatchesRegularExpression('/\.observation \{[^}]*border-radius: 6px;/', $html);
        $this->assertStringContainsString('<td class="observation-empty">-</td>', $html);
        $this->assertSeeInOrderHtml($html, ['>Boleto<', '>Nombre<', '>DNI<', '>Asiento<', '>Origen<', '>Destino<', '>Observación<']);
    }

    public function test_el_pdf_se_genera_con_una_observacion_de_280_caracteres(): void
    {
        $text = mb_substr(str_repeat('Texto largo ', 30), 0, 279).'.';
        $this->assertSame(280, mb_strlen($text));
        $this->withObservation->update(['observation' => $text]);

        $pdf = app(\App\Services\TripPdfService::class)->generateTripDetailsPdf($this->trip->fresh());

        $this->assertStringStartsWith('%PDF', $pdf->output());
    }

    // ====================== Excel ======================

    public function test_el_excel_mantiene_telefono_y_suma_observaciones_al_final(): void
    {
        $export = app(TripExcelService::class)->generateTripDetailsExcel($this->trip);
        $html = $export->view()->render();

        $this->assertStringContainsString('Teléfono', $html);
        $this->assertStringContainsString('3815550001', $html);
        $this->assertSeeInOrderHtml($html, ['<th>Viaja con</th>', '<th>Observaciones</th>', '</tr>']);
        $this->assertSame(1, substr_count($html, self::OBSERVATION));
        $this->assertSame(['I' => 50], $export->columnWidths());
    }

    // ====================== Parámetro apagado ======================

    public function test_con_el_parametro_apagado_el_modal_vuelve_a_la_tabla_anterior(): void
    {
        Setting::set(Setting::OBSERVACIONES_VENTA, '0');

        $html = $this->renderModal();

        $this->assertStringContainsString('Teléfono', $html);
        $this->assertStringContainsString('3815550001', $html);
        $this->assertStringNotContainsString('Observación', $html);
        $this->assertStringNotContainsString(self::OBSERVATION, $html);
        $this->assertSeeInOrderHtml($html, ['Boleto N°', 'Nombre', 'DNI', 'Teléfono', 'Asiento', 'Origen', 'Destino', '</tr>']);
    }

    public function test_con_el_parametro_apagado_el_pdf_vuelve_a_la_tabla_anterior(): void
    {
        Setting::set(Setting::OBSERVACIONES_VENTA, '0');

        $html = $this->renderPdf();

        $this->assertStringContainsString('>Teléfono<', $html);
        $this->assertStringContainsString('3815550001', $html);
        $this->assertStringNotContainsString('>Observación<', $html);
        $this->assertStringNotContainsString(self::OBSERVATION, $html);
    }

    public function test_con_el_parametro_apagado_el_excel_no_tiene_la_columna_observaciones(): void
    {
        Setting::set(Setting::OBSERVACIONES_VENTA, '0');

        $export = app(TripExcelService::class)->generateTripDetailsExcel($this->trip);
        $html = $export->view()->render();

        $this->assertStringContainsString('Teléfono', $html);
        $this->assertStringNotContainsString('Observaciones', $html);
        $this->assertStringNotContainsString(self::OBSERVATION, $html);
        $this->assertSame([], $export->columnWidths());
    }

    // ====================== Helpers ======================

    private function renderModal(): string
    {
        return view('filament.trips.trip-details', [
            'trip' => $this->trip,
            'passengers' => $this->trip->getPassengersWithDetails(),
        ])->render();
    }

    private function renderPdf(): string
    {
        $passengers = $this->trip->getPassengersWithDetails();
        $stops = $this->trip->route->stops()->with('location')->get();

        return view('pdf.trip-details', [
            'trip' => $this->trip,
            'passengers' => $passengers,
            'firstStop' => $stops->first(),
            'lastStop' => $stops->last(),
            'stops' => $stops,
            'stopsCount' => $stops->count(),
            'passengersCount' => $passengers->count(),
        ])->render();
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertSeeInOrderHtml(string $html, array $needles): void
    {
        $offset = 0;
        foreach ($needles as $needle) {
            $position = strpos($html, $needle, $offset);
            $this->assertNotFalse($position, "No se encontró «{$needle}» en el orden esperado.");
            $offset = $position + strlen($needle);
        }
    }
}
