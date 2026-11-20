<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\ViewTicket;
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
use App\Services\TicketPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * PDF de un boleto para el personal del panel (`admin.boletos.pdf`): sesión
 * del panel + permiso `tickets.view`, boletos eliminados incluidos, sin
 * generar links públicos. El detalle del boleto sí genera el link del
 * boleto en modo link (para "Copiar link" y "Enviar por WhatsApp").
 */
class AdminTicketPdfTest extends TestCase
{
    use RefreshDatabase;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $oran = Location::create(['name' => 'Orán A', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta A', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Coche 7', 'plate' => 'AAA111', 'seat_count' => 2, 'floors' => 1]);
        $seat = Seat::create(['bus_id' => $bus->id, 'seat_number' => '1', 'is_active' => true, 'floor' => '1']);

        $route = Route::create(['name' => 'Orán - Salta A', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana A',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2030-10-01',
        ]);

        $sale = Sale::create([
            'user_id' => User::factory()->vendedor()->create()->id,
            'sale_date' => now(),
            'total_amount' => 15000,
        ]);

        $this->ticket = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'seat_id' => $seat->id,
            'bus_id' => $bus->id,
            'passenger_id' => Passenger::create([
                'first_name' => 'Juan',
                'last_name' => 'Panel',
                'dni' => '30111999',
                'passenger_type' => 'adult',
            ])->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 15000,
        ]);
    }

    public function test_usuario_con_permiso_recibe_el_pdf_en_linea(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $response = $this->get(route('admin.boletos.pdf', ['ticket' => $this->ticket->id]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame(
            'inline; filename="'.rawurlencode(TicketPdfService::filenameFor($this->ticket)).'"',
            $response->headers->get('Content-Disposition'),
        );
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_attachment_descarga_con_el_nombre_del_boleto(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $response = $this->get(route('admin.boletos.pdf', ['ticket' => $this->ticket->id, 'disposition' => 'attachment']));

        $response->assertOk();
        $this->assertSame(
            'attachment; filename="'.rawurlencode(TicketPdfService::filenameFor($this->ticket)).'"',
            $response->headers->get('Content-Disposition'),
        );
    }

    public function test_invitado_no_recibe_el_pdf_ni_un_error_500(): void
    {
        $response = $this->get(route('admin.boletos.pdf', ['ticket' => $this->ticket->id]));

        $response->assertRedirect();
        $this->assertStringContainsString('/admin/login', (string) $response->headers->get('Location'));
    }

    public function test_usuario_sin_permiso_recibe_403(): void
    {
        $user = User::factory()->vendedor()->create();
        $user->syncRoles([]);
        $this->actingAs($user);

        $this->get(route('admin.boletos.pdf', ['ticket' => $this->ticket->id]))->assertForbidden();
    }

    public function test_boleto_eliminado_devuelve_el_pdf(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());
        $this->ticket->delete();

        $response = $this->get(route('admin.boletos.pdf', ['ticket' => $this->ticket->id]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_boleto_inexistente_devuelve_404(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $this->get(route('admin.boletos.pdf', ['ticket' => 999999]))->assertNotFound();
    }

    public function test_disposition_invalida_devuelve_404(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $this->get(route('admin.boletos.pdf', ['ticket' => $this->ticket->id, 'disposition' => 'otra']))->assertNotFound();
    }

    public function test_no_se_persiste_share_token_en_el_boleto_ni_en_la_venta(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $this->get(route('admin.boletos.pdf', ['ticket' => $this->ticket->id]))->assertOk();
        $this->get(route('admin.boletos.pdf', ['ticket' => $this->ticket->id, 'disposition' => 'attachment']))->assertOk();

        $this->assertNull($this->ticket->fresh()->share_token);
        $this->assertNull($this->ticket->sale->fresh()->share_token);
    }

    public function test_ver_boleto_muestra_la_vista_previa_con_la_ruta_del_panel(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->assertSee('Vista previa')
            ->assertSeeHtml(route('admin.boletos.pdf', ['ticket' => $this->ticket->id, 'disposition' => 'inline']))
            ->assertDontSeeHtml('/boletos/t_');

        $this->assertNull($this->ticket->fresh()->share_token);
        $this->assertNull($this->ticket->sale->fresh()->share_token);
    }

    public function test_ver_boleto_eliminado_tambien_muestra_la_vista_previa(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());
        $this->ticket->delete();

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->assertSee('Vista previa')
            ->assertSeeHtml('admin/boletos/'.$this->ticket->id.'/pdf');
    }

    public function test_la_vista_previa_no_depende_del_parametro_de_descarga_automatica(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        foreach (['1', '0'] as $value) {
            Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, $value);

            Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
                ->assertSee('Vista previa')
                ->assertSeeHtml('admin/boletos/'.$this->ticket->id.'/pdf');
        }

        Setting::flushCache();
    }

    public function test_con_descarga_automatica_el_detalle_no_crea_links_y_ofrece_descargar(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '1');

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->assertSee('Descargar PDF')
            ->assertDontSee('Copiar link')
            ->assertActionHidden('enviar_whatsapp');

        $this->assertNull($this->ticket->fresh()->share_token);
        $this->assertNull($this->ticket->sale->fresh()->share_token);
        Setting::flushCache();
    }

    public function test_copiar_link_de_la_vista_previa_usa_el_color_de_la_empresa_con_contorno(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');

        $html = Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])->html();

        // Dos botones: el del encabezado (visor embebido) y el de la tarjeta (celular).
        preg_match_all('/<button\b[^>]*>(?:(?!<\/button>).)*?Copiar link/s', $html, $matches);
        $this->assertCount(2, $matches[0]);

        foreach ($matches[0] as $button) {
            $this->assertStringContainsString('fi-color-primary', $button);
            $this->assertStringContainsString('fi-outlined', $button);
            $this->assertStringNotContainsString('fi-color-info', $button);
        }

        Setting::flushCache();
    }

    public function test_en_modo_link_el_detalle_crea_el_link_del_boleto_y_no_el_de_la_venta(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->assertSee('Copiar link')
            ->assertDontSee('Descargar PDF');

        $token = $this->ticket->fresh()->share_token;
        $this->assertNotNull($token);
        $this->assertNull($this->ticket->sale->fresh()->share_token);

        // Abrir el detalle de nuevo no cambia el link.
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id]);
        $this->assertSame($token, $this->ticket->fresh()->share_token);
        Setting::flushCache();
    }

    public function test_en_modo_link_un_boleto_eliminado_no_crea_link(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        $this->ticket->delete();

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->assertSee('Vista previa')
            ->assertDontSee('Copiar link');

        $this->assertNull($this->ticket->fresh()->share_token);
        Setting::flushCache();
    }
}
