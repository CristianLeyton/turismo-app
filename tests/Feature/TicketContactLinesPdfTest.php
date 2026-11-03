<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Líneas de contacto en el PDF del boleto: un bloque compacto debajo del
 * asiento, con cada teléfono como enlace tel: para llamar desde el celular.
 */
class TicketContactLinesPdfTest extends TestCase
{
    use RefreshDatabase;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();

        $seller = User::factory()->vendedor()->create();
        $origin = Location::create(['name' => 'Salta', 'is_active' => true]);
        $destination = Location::create(['name' => 'Tartagal', 'is_active' => true]);
        $bus = Bus::create(['name' => 'Linea C', 'plate' => 'CCC111', 'seat_count' => 4, 'floors' => 1]);

        $this->ticket = Ticket::create([
            'sale_id' => Sale::create(['user_id' => $seller->id, 'sale_date' => now(), 'total_amount' => 20000])->id,
            'trip_id' => null,
            'seat_id' => null,
            'bus_id' => $bus->id,
            'passenger_id' => Passenger::create([
                'first_name' => 'Andrea',
                'last_name' => 'Vargas',
                'dni' => '25437575',
                'passenger_type' => 'adult',
            ])->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'price' => 20000,
        ]);
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    /**
     * @param  list<array{name: ?string, phone: string}>  $lines
     */
    private function configure(array $lines): void
    {
        Setting::set(Setting::CONTACT_LINES, json_encode($lines));
    }

    private function renderPdfHtml(): string
    {
        return view('tickets.pdf.passenger-tickets', [
            'sale' => $this->ticket->sale,
            'tickets' => collect([$this->ticket]),
            'passenger' => $this->ticket->passenger,
            'hasChild' => false,
        ])->render();
    }

    /** Texto visible del bloque, sin etiquetas ni espacios de más. */
    private function contactBlockText(string $html): string
    {
        $this->assertSame(1, preg_match("/<div class='contact-lines'>(.*?)<\\/div>/s", $html, $matches), 'El boleto debe tener el bloque de contacto.');

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($matches[1]))));
    }

    public function test_con_dos_lineas_el_boleto_las_muestra_en_una_fila(): void
    {
        $this->configure([
            ['name' => 'Orán', 'phone' => '3878-804506'],
            ['name' => 'Tartagal', 'phone' => '3873-309893'],
        ]);

        $this->assertSame(
            'Líneas de contacto: Orán: 3878-804506 · Tartagal: 3873-309893',
            $this->contactBlockText($this->renderPdfHtml()),
        );
    }

    public function test_cada_telefono_enlaza_a_su_tel_sin_guiones_y_el_nombre_queda_fuera(): void
    {
        $this->configure([
            ['name' => 'Orán', 'phone' => '3878-804506'],
            ['name' => 'Exterior', 'phone' => '+54 9 (3878) 804-506'],
        ]);

        $html = $this->renderPdfHtml();

        $this->assertStringContainsString('<a href="tel:3878804506">3878-804506</a>', $html);
        $this->assertStringContainsString('<a href="tel:+5493878804506">+54 9 (3878) 804-506</a>', $html);
        $this->assertStringNotContainsString('>Orán:</a>', $html);
    }

    public function test_una_linea_sin_nombre_muestra_solo_el_telefono(): void
    {
        $this->configure([['name' => null, 'phone' => '3878-804506']]);

        $this->assertSame('Líneas de contacto: 3878-804506', $this->contactBlockText($this->renderPdfHtml()));
    }

    public function test_sin_lineas_el_boleto_no_muestra_el_bloque(): void
    {
        $html = $this->renderPdfHtml();

        $this->assertStringNotContainsString("class='contact-lines'", $html);
        $this->assertStringNotContainsString('Líneas de contacto', $html);
    }

    public function test_un_cambio_de_telefono_se_refleja_en_el_siguiente_render(): void
    {
        $this->configure([['name' => 'Orán', 'phone' => '3878-804506']]);
        $this->assertStringContainsString('3878-804506', $this->renderPdfHtml());

        $this->configure([['name' => 'Orán', 'phone' => '3878-111222']]);

        $html = $this->renderPdfHtml();
        $this->assertStringContainsString('3878-111222', $html);
        $this->assertStringNotContainsString('3878-804506', $html);
    }

    public function test_el_pdf_generado_por_el_servicio_trae_los_enlaces_para_llamar(): void
    {
        $this->configure([
            ['name' => 'Orán', 'phone' => '3878-804506'],
            ['name' => 'Tartagal', 'phone' => '3873-309893'],
        ]);

        $pdf = app(TicketPdfService::class)->generatePassengerTickets($this->ticket->sale, collect([$this->ticket]));

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('tel:3878804506', $pdf);
        $this->assertStringContainsString('tel:3873309893', $pdf);
    }
}
