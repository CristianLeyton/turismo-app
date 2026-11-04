<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Bus;
use App\Models\Clients;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Support\ShareLinkNotification;
use App\Support\WhatsAppShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature temporal: modo de emisión por link (DESCARGA_AUTOMATICA_PDF off).
 */
class TicketShareLinkModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        Cache::forget('payment_methods.options');
        Cache::forget('payment_methods.all');
        Setting::flushCache();
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_con_modo_descarga_la_venta_mantiene_el_comportamiento_actual(): void
    {
        // Sin tocar el parámetro: default ON (descarga automática).
        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        $this->invokeHandleRecordCreation($this->salePayload($f, ['81000001']));

        $this->assertSame(route('tickets.download'), session('auto_download_url'));
        $this->assertNotNull(session('ticket_pdf_content'));

        // El token no se genera de forcing: no está en la tabla todavía.
        $this->assertFalse(
            Ticket::query()->whereNotNull('share_token')->exists(),
            'Con descarga automática no debe generarse token al vender.',
        );
    }

    public function test_con_modo_link_un_pasajero_abre_el_share_del_boleto(): void
    {
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        $this->invokeHandleRecordCreation($this->salePayload($f, ['81000001'], ['81000001' => '3881234567']));

        $ticket = Ticket::sole();
        $this->assertNotNull($ticket->share_token);

        $shareUrl = $ticket->shareUrl();
        $this->assertSame($shareUrl, session('auto_download_url'), 'La venta abre la página del link.');

        // No se generó PDF en sesión (el flujo viejo quedó fuera).
        $this->assertNull(session('ticket_pdf_content'));

        // Página de preview accesible sin sesión/login.
        $this->get($shareUrl)
            ->assertOk()
            ->assertSee('Descargar PDF')
            ->assertSee('<span>Enviar</span>', false);

        // PDF inline (preview).
        $inline = $this->get($ticket->sharePdfUrl('inline'));
        $inline->assertOk();
        $this->assertSame('application/pdf', $inline->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $inline->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-cache', (string) $inline->headers->get('Cache-Control'));

        // PDF attachment (descarga).
        $download = $this->get($ticket->sharePdfUrl('attachment'));
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));

        // Token inventado → 404.
        $this->get('/boletos/t_nuncafue')->assertNotFound();
    }

    public function test_con_modo_link_varios_pasajeros_abre_el_share_de_la_venta(): void
    {
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        $this->invokeHandleRecordCreation($this->salePayload(
            $f,
            ['81000001', '81000002'],
        ));

        $sale = Sale::sole();
        $this->assertNotNull($sale->share_token);

        // Dos pasajeros → abre la página combinada de la venta.
        $this->assertSame($sale->shareUrl(), session('auto_download_url'));

        $response = $this->get($sale->shareUrl())
            ->assertOk()
            ->assertSee('Boletos de la venta');

        // PDF combinado.
        $pdf = $this->get($sale->sharePdfUrl('inline'));
        $pdf->assertOk();
        $this->assertSame(200, $pdf->getStatusCode());
    }

    public function test_los_teléfonos_de_pasajeros_y_padron_se_deduplican(): void
    {
        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        $this->invokeHandleRecordCreation($this->salePayload(
            $f,
            ['81000001', '81000002'],
            ['81000002' => '3881234567'], // duplicado con el padrón
        ));

        // Padrón: DNI del segundo pasajero con el MISMO teléfono de compra
        // (duplicado) y el primero con otro teléfono.
        Clients::create(['dni' => '81000002', 'nombre' => 'Ana', 'apellido' => 'Dos', 'telefono' => '3881234567', 'can_buy' => true]);
        Clients::create(['dni' => '81000001', 'nombre' => 'Ana', 'apellido' => 'Uno', 'telefono' => '3881239999', 'can_buy' => true]);

        $sale = Sale::sole();
        $targets = WhatsAppShare::forSale($sale, 'https://example.com/link');

        $phones = array_column($targets, 'phone');

        sort($phones);

        $this->assertSame(
            ['543881234567', '543881239999'],
            $phones,
            'Todos los teléfonos del pasajero y del padrón, normalizados y sin duplicados.',
        );

        // El mensaje lleva el link reemplazado en el texto default.
        $expectedText = str_replace('{link}', 'https://example.com/link', Setting::WHATSAPP_MENSAJE_DEFAULT);
        $this->assertStringContainsString($expectedText, urldecode($targets[0]['url']));
    }

    public function test_el_mensaje_de_whatsapp_viene_de_parametros(): void
    {
        Setting::set(Setting::WHATSAPP_MENSAJE, 'Aca va tu boleto: {link} - buscado desde_parametros');

        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        $this->invokeHandleRecordCreation($this->salePayload($f, ['81000001'], ['81000001' => '3881234567']));

        $ticket = Ticket::sole();
        $targets = WhatsAppShare::forSale($ticket->sale, $ticket->shareUrl());

        $this->assertStringContainsString('desde_parametros', $targets[0]['url']);
    }

    public function test_el_link_de_un_boleto_borrado_da_404(): void
    {
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        $this->invokeHandleRecordCreation($this->salePayload($f, ['81000001'], ['81000001' => '3881234567']));

        $ticket = Ticket::sole();
        $shareUrl = $ticket->shareUrl();

        $ticket->delete();

        // Borrado → el token ya no resuelve (sin SoftDeletingScope de ningun
        // lado para el fetch público): 404 limpio.
        $this->get($shareUrl)->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Página pública: encabezado, bloques, acciones y vista previa
    // -----------------------------------------------------------------

    public function test_la_pagina_de_un_boleto_muestra_titulo_ruta_y_tarjetas(): void
    {
        $ticket = $this->sellLinkMode(['81000001'], ['81000001' => '3881234567']);

        $this->get($ticket->shareUrl())
            ->assertOk()
            ->assertSee("Boleto N°{$ticket->id}")
            ->assertSee('Orán L → Salta L')
            ->assertSeeInOrder(['Pasajero', 'Detalles de venta']);
    }

    public function test_la_pagina_de_una_venta_muestra_un_bloque_por_boleto(): void
    {
        $this->sellLinkMode(['81000001', '81000002']);

        $sale = Sale::sole();
        [$first, $second] = $sale->tickets()->orderBy('id')->get()->all();

        $response = $this->get($sale->shareUrl())
            ->assertOk()
            ->assertSee("Boletos de la venta N°{$sale->id}")
            ->assertSeeInOrder([
                "Boleto N°{$first->id} — Uno Juan",
                "Boleto N°{$second->id} — Dos Juana",
                'Detalles de venta',
            ]);

        $this->assertSame(1, substr_count($response->getContent(), 'Detalles de venta'), 'Una sola tarjeta de detalles de venta.');
    }

    public function test_un_diferido_con_vuelta_asignada_muestra_ida_y_vuelta(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);

        $returnTrip = Trip::findOrCreateForBooking($ticket->trip->schedule_id, '2030-10-05', $ticket->origin_location_id, $ticket->destination_location_id)['trip'];
        $ticket->update(['is_round_trip' => true, 'return_trip_id' => $returnTrip->id]);

        $this->get($ticket->shareUrl())
            ->assertOk()
            ->assertSee('Ida:')
            ->assertSee('Vuelta:')
            ->assertSee('05/10/2030');

        $this->get('/boletos/t_noexiste')->assertNotFound();
        $this->get('/boletos/s_noexiste')->assertNotFound();
    }

    public function test_con_un_telefono_las_acciones_van_en_orden(): void
    {
        $ticket = $this->sellLinkMode(['81000001'], ['81000001' => '3881234567']);

        $this->get($ticket->shareUrl())
            ->assertOk()
            ->assertSeeInOrder(['<span>Enviar</span>', 'Descargar PDF', 'Copiar link'], false)
            ->assertDontSee('No hay teléfonos cargados');
    }

    public function test_con_varios_telefonos_hay_un_boton_por_destinatario(): void
    {
        $this->sellLinkMode(['81000001', '81000002'], ['81000001' => '3881234567', '81000002' => '3881239999']);

        $this->get(Sale::sole()->shareUrl())
            ->assertOk()
            ->assertSeeInOrder(['Enviar a Uno Juan', 'Enviar a Dos Juana', 'Descargar PDF', 'Copiar link'])
            ->assertDontSee('<span>Enviar</span>', false);
    }

    public function test_sin_telefonos_no_hay_whatsapp_y_se_aclara(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);

        $this->get($ticket->shareUrl())
            ->assertOk()
            ->assertDontSee('<span>Enviar</span>', false)
            ->assertDontSee('Enviar a ')
            ->assertSee('No hay teléfonos cargados')
            ->assertSeeInOrder(['Descargar PDF', 'Copiar link']);
    }

    public function test_la_vista_previa_ofrece_abrir_el_pdf_y_el_selector_de_tema(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);

        $this->get($ticket->shareUrl())
            ->assertOk()
            ->assertSee('Abrir PDF')
            ->assertSee($ticket->sharePdfUrl('inline'), false)
            ->assertSee('data-theme-option="light"', false)
            ->assertSee('data-theme-option="dark"', false)
            ->assertSee('data-theme-option="system"', false);
    }

    public function test_la_notificacion_ofrece_abrir_copiar_descargar_y_whatsapp_en_orden(): void
    {
        $ticket = $this->sellLinkMode(['81000001'], ['81000001' => '3881234567']);
        $shareUrl = $ticket->shareUrl();

        $actions = ShareLinkNotification::actionsFor($ticket->sale, $shareUrl, $ticket->sharePdfUrl('attachment'));

        $this->assertSame(
            ['abrir_link', 'copiar_link', 'descargar_pdf', 'compartir_whatsapp'],
            array_map(fn ($action) => $action->getName(), $actions),
        );

        $this->assertSame($shareUrl, $actions[0]->getUrl());
        $this->assertTrue($actions[0]->shouldOpenUrlInNewTab());

        // "Copiar link" no navega: dispara el evento con la URL, y sobrevive
        // a la serialización de la notification.
        $copy = \Filament\Actions\Action::fromArray($actions[1]->toArray());
        $this->assertNull($copy->getUrl());
        $this->assertSame(ShareLinkNotification::COPY_EVENT, $copy->getEvent());
        $this->assertSame(['url' => $shareUrl], $copy->getEventData());
        $this->assertFalse($copy->shouldClose());

        $this->assertSame($ticket->sharePdfUrl('attachment'), $actions[2]->getUrl());
        $this->assertStringStartsWith('https://wa.me/', $actions[3]->getUrl());
    }

    public function test_la_notificacion_sin_telefonos_no_tiene_whatsapp(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);

        $actions = ShareLinkNotification::actionsFor($ticket->sale, $ticket->shareUrl(), $ticket->sharePdfUrl('attachment'));

        $this->assertSame(
            ['abrir_link', 'copiar_link', 'descargar_pdf'],
            array_map(fn ($action) => $action->getName(), $actions),
        );
    }

    public function test_la_notificacion_con_varios_telefonos_lleva_whatsapp_a_la_pagina(): void
    {
        $this->sellLinkMode(['81000001', '81000002'], ['81000001' => '3881234567', '81000002' => '3881239999']);
        $sale = Sale::sole();

        $actions = ShareLinkNotification::actionsFor($sale, $sale->shareUrl(), $sale->sharePdfUrl('attachment'));

        $this->assertSame($sale->shareUrl(), end($actions)->getUrl());
    }

    public function test_el_panel_escucha_el_evento_de_copiar_link(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertSee("window.addEventListener('".ShareLinkNotification::COPY_EVENT."'", false);
    }

    // -----------------------------------------------------------------
    // Panel admin: "Ver link" en el detalle del boleto
    // -----------------------------------------------------------------

    public function test_ver_link_en_el_detalle_con_el_parametro_desactivado(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);
        $this->actingAs($this->makeAdmin());

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionVisible('ver_link')
            ->assertActionHasUrl('ver_link', route('admin.boletos.link', ['ticket' => $ticket->id]))
            ->assertActionShouldOpenUrlInNewTab('ver_link');

        $this->assertStringContainsString('/boletos/t_', $ticket->fresh()->shareUrl());
    }

    public function test_ver_link_oculto_con_el_parametro_activado(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '1');
        $this->actingAs($this->makeAdmin());

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionHidden('ver_link');
    }

    public function test_ver_link_oculto_con_el_parametro_sin_configurar(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);
        Setting::query()->where('key', Setting::DESCARGA_AUTOMATICA_PDF)->delete();
        Setting::flushCache();
        $this->actingAs($this->makeAdmin());

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionHidden('ver_link');
    }

    public function test_ver_link_oculto_para_un_boleto_eliminado(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);
        $ticket->delete();
        $this->actingAs($this->makeAdmin());

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionHidden('ver_link');
    }

    // -----------------------------------------------------------------
    // Diferido: sólo las idas en pantalla, todos los boletos en el PDF
    // -----------------------------------------------------------------

    public function test_el_link_de_una_venta_diferida_muestra_solo_las_idas(): void
    {
        $this->sellUndatedRoundTrip(['81000001', '81000002']);

        $sale = Sale::sole();
        $idas = $sale->tickets()->where('is_return_leg', false)->orderBy('id')->get();
        $vueltas = $sale->tickets()->where('is_return_leg', true)->orderBy('id')->get();
        $this->assertCount(2, $idas);
        $this->assertCount(2, $vueltas);

        $response = $this->get($sale->shareUrl())
            ->assertOk()
            ->assertSeeInOrder([
                "Boleto N°{$idas[0]->id} — Uno Juan",
                "Boleto N°{$idas[1]->id} — Dos Juana",
            ]);

        foreach ($vueltas as $vuelta) {
            $response->assertDontSee("Boleto N°{$vuelta->id} —");
            $response->assertSee("Boleto de vuelta N°{$vuelta->id}");
        }
    }

    public function test_el_pdf_del_link_de_una_venta_diferida_incluye_todos_los_boletos(): void
    {
        $this->sellUndatedRoundTrip(['81000001', '81000002']);

        $sale = Sale::sole();

        $captured = null;
        \Illuminate\Support\Facades\View::composer('tickets.pdf.passenger-tickets', function ($view) use (&$captured): void {
            $captured = $view->getData()['tickets'];
        });

        $this->get($sale->sharePdfUrl('attachment'))->assertOk();

        $this->assertNotNull($captured);
        $this->assertEqualsCanonicalizing($sale->tickets()->pluck('id')->all(), $captured->pluck('id')->all());
        $this->assertCount(4, $captured);
    }

    public function test_un_diferido_de_un_pasajero_entrega_el_link_de_la_ida(): void
    {
        $this->sellUndatedRoundTrip(['81000001']);

        $ida = Ticket::query()->where('is_return_leg', false)->sole();
        $vuelta = Ticket::query()->where('is_return_leg', true)->sole();

        $this->assertSame($ida->fresh()->shareUrl(), session('auto_download_url'));
        $this->assertNull($vuelta->fresh()->share_token, 'La vuelta no recibe link propio.');

        $this->get($ida->fresh()->shareUrl())
            ->assertOk()
            ->assertSee('Asiento ida')
            ->assertSee("Boleto de vuelta N°{$vuelta->id}");
    }

    /**
     * Venta en modo link, ida y vuelta sin fecha.
     *
     * @param  array<int, string>  $dnis
     */
    private function sellUndatedRoundTrip(array $dnis): void
    {
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        $f = $this->makeFixture();

        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo('tickets.vender_sin_fecha');
        $this->actingAs($seller);

        foreach ($dnis as $dni) {
            Clients::create(['dni' => $dni, 'nombre' => 'Padrón', 'apellido' => 'Test', 'can_buy' => true]);
        }

        $payload = array_merge($this->salePayload($f, $dnis), [
            'sell_without_date_ida' => true,
            'sell_without_date_vuelta' => true,
            'is_round_trip' => true,
            'trip_id' => null,
            'seat_ids' => [],
        ]);

        $this->invokeHandleRecordCreation($payload);
    }

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin link',
            'email' => 'admin-link@test.local',
            'username' => 'admin-link',
            'password' => bcrypt('secreto123'),
            'is_admin' => true,
        ]);
    }

    /**
     * Venta en modo link: devuelve el primer boleto emitido.
     *
     * @param  array<int, string>  $dnis
     * @param  array<string, string>  $telefonosPorDni
     */
    private function sellLinkMode(array $dnis, array $telefonosPorDni = []): Ticket
    {
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        $f = $this->makeFixture();
        $this->actingAs(User::factory()->vendedor()->create());

        $this->invokeHandleRecordCreation($this->salePayload($f, $dnis, $telefonosPorDni));

        return Ticket::query()->orderBy('id')->firstOrFail();
    }

    // -----------------------------------------------------------------
    // Harness (espeja TicketSaleCutoffTest)
    // -----------------------------------------------------------------

    private function invokeHandleRecordCreation(array $data): Ticket
    {
        $page = app(CreateTicket::class);

        $method = new \ReflectionMethod(CreateTicket::class, 'handleRecordCreation');
        $method->setAccessible(true);

        return $method->invoke($page, $data);
    }

    private function makeFixture(): array
    {
        $oran = Location::create(['name' => 'Orán L', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta L', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea L', 'plate' => 'LLL111', 'seat_count' => 8, 'floors' => 1]);
        foreach (range(1, 8) as $n) {
            Seat::create(['bus_id' => $bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }

        $route = Route::create(['name' => 'Orán - Salta L', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1, 'departure_offset_minutes' => 0]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana L',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::findOrCreateForBooking($schedule->id, '2030-10-01', $oran->id, $salta->id)['trip'];
        $this->assertNotNull($trip);

        return compact('oran', 'salta', 'bus', 'schedule', 'trip');
    }

    /**
     * @param  array<int, string>  $dnis
     * @param  array<string, string>  $telefonosPorDni  DNI => teléfono
     */
    private function salePayload(array $f, array $dnis, array $telefonosPorDni = []): array
    {
        $passengers = [];
        $names = ['Juan Uno', 'Juana Dos', 'Juan Tres', 'José Cuatro'];

        foreach ($dnis as $index => $dni) {
            $nombre = $names[$index] ?? 'Pasajero '.($index + 1);

            $passengers[] = [
                'first_name' => explode(' ', $nombre)[0],
                'last_name' => explode(' ', $nombre)[1] ?? 'Test',
                'dni' => $dni,
                'phone_number' => $telefonosPorDni[$dni] ?? null,
                'email' => null,
                'travels_with_child' => false,
                'travels_with_pets' => false,
                'price' => 15000,
                'payment_method' => 'efectivo',
            ];
        }

        return [
            'bus_id' => $f['trip']->bus_id,
            'sell_without_date_ida' => false,
            'sell_without_date_vuelta' => false,
            'passengers_count' => (string) count($passengers),
            'passengers' => $passengers,
            'origin_location_id' => $f['oran']->id,
            'destination_location_id' => $f['salta']->id,
            'is_round_trip' => false,
            'trip_id' => $f['trip']->id,
            'seat_ids' => array_map(fn ($i) => $i + 1, range(0, count($passengers) - 1)),
            'return_trip_id' => null,
            'return_seat_ids' => [],
        ];
    }
}
