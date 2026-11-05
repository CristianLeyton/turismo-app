<?php

namespace Tests\Feature;

use App\Filament\Actions\TicketDeliveryAction;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\TicketResource;
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
use App\Services\TicketPdfService;
use App\Support\TicketDelivery;
use App\Support\WhatsAppShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Modo de emisión por link (DESCARGA_AUTOMATICA_PDF desactivado): el link
 * público entrega el PDF del boleto y la venta abre el modal de entrega.
 */
class TicketShareLinkModeTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $fixture = [];

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
        $this->assertNull(session(TicketDelivery::SESSION_KEY), 'Con descarga automática no se abre el modal de entrega.');
        $this->assertSame(url('/admin'), $this->invokeRedirectUrl());

        // El token no se genera de forcing: no está en la tabla todavía.
        $this->assertFalse(
            Ticket::query()->whereNotNull('share_token')->exists(),
            'Con descarga automática no debe generarse token al vender.',
        );
    }

    // -----------------------------------------------------------------
    // Link público: el PDF del boleto
    // -----------------------------------------------------------------

    public function test_el_link_de_un_boleto_entrega_su_pdf_en_linea(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);
        auth()->logout();

        $response = $this->get($ticket->shareUrl());

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(
            'inline; filename="'.rawurlencode(TicketPdfService::filenameFor($ticket)).'"',
            $response->headers->get('Content-Disposition'),
        );
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    public function test_la_descarga_del_link_entrega_el_pdf_como_adjunto(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);
        auth()->logout();

        $response = $this->get($ticket->sharePdfUrl('attachment'));

        $response->assertOk();
        $this->assertSame(
            'attachment; filename="'.rawurlencode(TicketPdfService::filenameFor($ticket)).'"',
            $response->headers->get('Content-Disposition'),
        );

        $this->get(route('boletos.share.pdf', ['token' => $ticket->share_token, 'disposition' => 'otra']))->assertNotFound();
    }

    public function test_el_link_de_un_boleto_borrado_da_404(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);
        $shareUrl = $ticket->shareUrl();

        $ticket->delete();

        // Borrado → el token ya no resuelve (sin SoftDeletingScope de ningun
        // lado para el fetch público): 404 limpio.
        $this->get($shareUrl)->assertNotFound();
    }

    public function test_un_token_inventado_o_de_venta_da_404(): void
    {
        $this->sellLinkMode(['81000001']);
        $saleToken = Sale::sole()->shareToken();

        $this->assertStringStartsWith('s_', $saleToken);

        $this->get('/boletos/t_nuncafue')->assertNotFound();
        $this->get('/boletos/'.$saleToken)->assertNotFound();
        $this->get('/boletos/'.$saleToken.'/pdf?disposition=attachment')->assertNotFound();
    }

    public function test_el_pdf_del_link_usa_el_color_de_la_empresa(): void
    {
        Setting::set(Setting::PRIMARY_COLOR, '#0e7490');
        $ticket = $this->sellLinkMode(['81000001']);

        $data = $this->capturePdfData(fn () => $this->get($ticket->shareUrl())->assertOk());

        BrandColorPdfTest::assertUsesBrandPalette($this, view('tickets.pdf.passenger-tickets', $data)->render());
    }

    public function test_el_link_de_la_ida_de_un_diferido_incluye_la_vuelta(): void
    {
        $this->sellUndatedRoundTrip(['81000001']);

        $ida = Ticket::query()->where('is_return_leg', false)->sole();
        $vuelta = Ticket::query()->where('is_return_leg', true)->sole();

        $data = $this->capturePdfData(fn () => $this->get($ida->fresh()->shareUrl())->assertOk());

        $this->assertEqualsCanonicalizing([$ida->id, $vuelta->id], $data['tickets']->pluck('id')->all());
        $this->assertNull($vuelta->fresh()->share_token, 'La vuelta no recibe link propio.');
    }

    // -----------------------------------------------------------------
    // Teléfonos de WhatsApp
    // -----------------------------------------------------------------

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
        Clients::create(['dni' => '81000001', 'nombre' => 'Ana', 'apellido' => 'Uno', 'telefono' => '03881239999', 'can_buy' => true]);

        $phones = array_column(WhatsAppShare::candidatesForSale(Sale::sole()), 'phone');
        sort($phones);

        $this->assertSame(
            ['543881234567', '543881239999'],
            $phones,
            'Todos los teléfonos del pasajero y del padrón, normalizados y sin duplicados.',
        );

        $this->assertCount(3, WhatsAppShare::candidatesForSale(Sale::sole(), unique: false));
    }

    public function test_la_normalizacion_del_telefono(): void
    {
        $this->assertSame('543881234567', WhatsAppShare::normalizePhone('3881234567'));
        $this->assertSame('543881234567', WhatsAppShare::normalizePhone('03881234567'));
        $this->assertSame('543881234567', WhatsAppShare::normalizePhone('(388) 123-4567'));
        $this->assertSame('5493881234567', WhatsAppShare::normalizePhone('+54 9 388 123 4567'));
        $this->assertSame('', WhatsAppShare::normalizePhone('sin número'));
    }

    // -----------------------------------------------------------------
    // Datos del modal de entrega
    // -----------------------------------------------------------------

    public function test_un_diferido_de_dos_pasajeros_entrega_dos_filas_de_ida(): void
    {
        $this->sellUndatedRoundTrip(['81000001', '81000002']);

        $sale = Sale::sole();
        $idas = $sale->tickets()->where('is_return_leg', false)->orderBy('id')->get();

        $this->assertCount(4, $sale->tickets);
        $this->assertSame($idas->modelKeys(), TicketDelivery::ticketsForSale($sale)->modelKeys());

        $rows = TicketDelivery::payload(TicketDelivery::ticketsForSale($sale))['rows'];

        $this->assertSame("Boleto N°{$idas[0]->id} — Uno Juan", $rows[0]['title']);
        $this->assertSame("Boleto N°{$idas[1]->id} — Dos Juana", $rows[1]['title']);
        $this->assertSame($idas[0]->fresh()->shareUrl(), $rows[0]['link']);
        $this->assertSame($idas[0]->fresh()->sharePdfUrl('attachment'), $rows[0]['download']);
    }

    public function test_se_preselecciona_el_telefono_del_pasajero_despues_el_padron_y_despues_el_primero(): void
    {
        $this->sellLinkMode(['81000001', '81000002', '81000003'], ['81000001' => '3881111111']);
        Clients::create(['dni' => '81000002', 'nombre' => 'Juana', 'apellido' => 'Dos', 'telefono' => '3882222222', 'can_buy' => true]);

        $payload = TicketDelivery::payload(TicketDelivery::ticketsForSale(Sale::sole()));

        $this->assertSame(
            ['543881111111', '543882222222', '543881111111'],
            array_column($payload['rows'], 'defaultPhone'),
        );
        $this->assertSame(
            ['Uno Juan — 543881111111', 'Juana Dos — 543882222222'],
            array_column($payload['phones'], 'label'),
        );
    }

    public function test_una_venta_sin_telefonos_no_tiene_telefonos_para_elegir(): void
    {
        $this->sellLinkMode(['81000001', '81000002']);

        $payload = TicketDelivery::payload(TicketDelivery::ticketsForSale(Sale::sole()));

        $this->assertSame([], $payload['phones']);
        $this->assertSame([null, null], array_column($payload['rows'], 'defaultPhone'));
    }

    public function test_el_mensaje_de_whatsapp_viene_de_parametros(): void
    {
        Setting::set(Setting::WHATSAPP_MENSAJE, 'Aca va tu boleto: {link} - buscado desde_parametros');
        $this->sellLinkMode(['81000001']);

        $payload = TicketDelivery::payload(TicketDelivery::ticketsForSale(Sale::sole()));

        $this->assertSame('Aca va tu boleto: {link} - buscado desde_parametros', $payload['template']);
    }

    // -----------------------------------------------------------------
    // Vista del modal de entrega
    // -----------------------------------------------------------------

    public function test_el_modal_muestra_las_acciones_de_cada_boleto_en_orden(): void
    {
        $this->sellLinkMode(['81000001', '81000002'], ['81000001' => '3881234567']);
        $tickets = TicketDelivery::ticketsForSale(Sale::sole());

        $html = $this->renderDeliveryModal($tickets);

        foreach ($tickets as $ticket) {
            $this->assertStringContainsString("Boleto N°{$ticket->id} — ", $html);
            $this->assertStringContainsString('href="'.e($ticket->shareUrl()).'"', $html);
            $this->assertStringContainsString('href="'.e($ticket->sharePdfUrl('attachment')).'"', $html);
        }

        $this->assertMatchesRegularExpression('/Enviar por WhatsApp.*Ver PDF.*Copiar link.*Descargar PDF/s', $html);
        $this->assertStringContainsString('Orán L → Salta L', $html);
        $this->assertStringContainsString('Uno Juan — 543881234567', $html);
        $this->assertStringContainsString('Otro número…', $html);
        $this->assertStringContainsString('https://wa.me/', $html);
    }

    public function test_los_enlaces_del_modal_no_navegan_dentro_del_panel(): void
    {
        $ticket = $this->sellLinkMode(['81000001'], ['81000001' => '3881234567']);

        $html = $this->renderDeliveryModal(collect([$ticket]));

        // El panel está en modo SPA: ningún enlace del modal puede llevar
        // wire:navigate, o la descarga y los links se rompen.
        $this->assertStringNotContainsString('wire:navigate', $html);

        // "Descargar PDF": descarga directa del navegador.
        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(e($ticket->sharePdfUrl('attachment')), '/').'"[^>]*\sdownload[=\s>]/',
            $html,
        );

        // "Ver PDF" y WhatsApp: en otra pestaña.
        $this->assertStringContainsString('href="'.e($ticket->shareUrl()).'" target="_blank"', $html);
        $this->assertStringContainsString('x-bind:target="\'_blank\'"', $html);
    }

    public function test_el_modal_con_varios_boletos_ofrece_enviar_todos(): void
    {
        $this->sellLinkMode(['81000001', '81000002']);
        [$first, $second] = TicketDelivery::ticketsForSale(Sale::sole())->all();

        $html = $this->renderDeliveryModal(collect([$first, $second]));

        $this->assertStringContainsString('Enviar todos a un número', $html);
        $this->assertStringContainsString('Enviar todos por WhatsApp', $html);

        // Las líneas de "Enviar todos": "Apellido Nombre (N°id): link".
        $expectedLines = "Uno Juan (N°{$first->id}): {$first->shareUrl()}\nDos Juana (N°{$second->id}): {$second->shareUrl()}";
        $this->assertStringContainsString(Js::from($expectedLines)->toHtml(), $html);
    }

    public function test_el_modal_con_un_boleto_no_ofrece_enviar_todos(): void
    {
        $ticket = $this->sellLinkMode(['81000001'], ['81000001' => '3881234567']);

        $html = $this->renderDeliveryModal(collect([$ticket]));

        $this->assertStringNotContainsString('Enviar todos a un número', $html);
        $this->assertStringContainsString('Enviar por WhatsApp', $html);
    }

    public function test_el_modal_sin_telefonos_muestra_el_campo_del_numero(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);

        $html = $this->renderDeliveryModal(collect([$ticket]));

        $this->assertStringNotContainsString('<select', $html);
        $this->assertStringNotContainsString('Otro número…', $html);
        $this->assertStringContainsString('type="tel"', $html);
        $this->assertStringContainsString('Ej: 3881234567', $html);
    }

    // -----------------------------------------------------------------
    // Cierre de la venta en modo link
    // -----------------------------------------------------------------

    public function test_con_modo_link_la_venta_pide_el_modal_con_un_boleto_por_pasajero(): void
    {
        $this->sellLinkMode(['81000001', '81000002'], ['81000001' => '3881234567']);

        $sale = Sale::sole();

        $this->assertSame([
            'kind' => TicketDelivery::KIND_SALE,
            'sale_id' => $sale->id,
            'ticket_ids' => $sale->tickets()->orderBy('id')->pluck('id')->all(),
        ], session(TicketDelivery::SESSION_KEY));

        // Sin pestaña nueva, sin PDF en sesión y sin link de venta.
        $this->assertNull(session('auto_download_url'));
        $this->assertNull(session('ticket_pdf_content'));
        $this->assertNull($sale->fresh()->share_token);
        $this->assertSame(2, Ticket::query()->whereNotNull('share_token')->count());

        $this->assertSame(TicketResource::getUrl('create'), $this->invokeRedirectUrl());
    }

    public function test_un_diferido_pide_el_modal_solo_con_las_idas(): void
    {
        $this->sellUndatedRoundTrip(['81000001', '81000002']);

        $idas = Ticket::query()->where('is_return_leg', false)->orderBy('id')->pluck('id')->all();

        $this->assertSame($idas, session(TicketDelivery::SESSION_KEY)['ticket_ids']);
    }

    public function test_la_venta_abre_el_modal_de_entrega_al_volver(): void
    {
        $this->sellLinkMode(['81000001', '81000002']);
        $sale = Sale::sole();
        $pending = session(TicketDelivery::SESSION_KEY);

        Livewire::test(CreateTicket::class)
            ->assertSet('defaultAction', TicketDeliveryAction::NAME)
            ->assertSet('defaultActionArguments', $pending)
            ->mountAction(TicketDeliveryAction::NAME, $pending)
            ->assertActionMounted(TicketDeliveryAction::NAME)
            ->assertMountedActionModalSee("Venta N°{$sale->id} realizada correctamente")
            ->assertMountedActionModalSee('Se vendieron 2 boletos.')
            ->assertMountedActionModalSee('Nueva venta')
            ->assertMountedActionModalSee('Ir al inicio');
    }

    public function test_el_modal_no_se_cierra_con_un_click_afuera_ni_con_escape(): void
    {
        $action = TicketDeliveryAction::make();

        $this->assertFalse($action->isModalClosedByClickingAway());
        $this->assertFalse($action->isModalClosedByEscaping());
        $this->assertTrue($action->hasModalCloseButton(), 'La X cierra el modal, como "Nueva venta" o "Cerrar".');
    }

    public function test_la_venta_de_un_pasajero_dice_un_boleto(): void
    {
        $this->sellLinkMode(['81000001']);
        $pending = session(TicketDelivery::SESSION_KEY);

        Livewire::test(CreateTicket::class)
            ->mountAction(TicketDeliveryAction::NAME, $pending)
            ->assertMountedActionModalSee('Se vendió 1 boleto.');
    }

    public function test_sin_pedido_en_la_sesion_la_venta_no_abre_el_modal(): void
    {
        $this->sellLinkMode(['81000001']);
        session()->forget(TicketDelivery::SESSION_KEY);

        Livewire::test(CreateTicket::class)
            ->assertSet('defaultAction', null);
    }

    public function test_el_modal_descarta_boletos_de_otra_venta(): void
    {
        $ajeno = $this->sellLinkMode(['81000001']);

        $this->sellLinkMode(['81000002'], [], fresh: false);
        $propio = Ticket::query()->latest('id')->first();

        $this->assertNotSame($ajeno->sale_id, $propio->sale_id);

        $tickets = TicketDeliveryAction::tickets([
            'kind' => TicketDelivery::KIND_SALE,
            'sale_id' => $propio->sale_id,
            'ticket_ids' => [$ajeno->id, $propio->id],
        ]);

        $this->assertSame([$propio->id], $tickets->modelKeys());
    }

    public function test_el_modal_descarta_boletos_que_el_usuario_no_puede_ver(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);

        $sinPermisos = User::factory()->create();
        $sinPermisos->syncRoles([]);
        $sinPermisos->syncPermissions([]);
        $this->actingAs($sinPermisos->fresh());

        $this->assertCount(0, TicketDeliveryAction::tickets([
            'kind' => TicketDelivery::KIND_SALE,
            'sale_id' => $ticket->sale_id,
            'ticket_ids' => [$ticket->id],
        ]));
    }

    // -----------------------------------------------------------------
    // Panel admin: "Enviar por WhatsApp" y link en el detalle del boleto
    // -----------------------------------------------------------------

    public function test_enviar_por_whatsapp_en_el_detalle_va_al_telefono_del_pasajero(): void
    {
        $ticket = $this->sellLinkMode(['81000001'], ['81000001' => '3881234567']);
        $this->actingAs($this->makeAdmin());

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionDoesNotExist('ver_link')
            ->assertActionVisible('enviar_whatsapp')
            ->assertActionHasUrl('enviar_whatsapp', WhatsAppShare::url('543881234567', $ticket->fresh()->shareUrl()))
            ->assertActionShouldOpenUrlInNewTab('enviar_whatsapp');

        $this->assertStringContainsString(
            rawurlencode('Podés acceder a tu(s) boleto(s) desde aquí: '.$ticket->fresh()->shareUrl()),
            WhatsAppShare::url('543881234567', $ticket->fresh()->shareUrl()),
        );
    }

    public function test_enviar_por_whatsapp_usa_el_telefono_del_padron(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);
        Clients::create(['dni' => '81000001', 'nombre' => 'Juan', 'apellido' => 'Uno', 'telefono' => '3882222222', 'can_buy' => true]);
        $this->actingAs($this->makeAdmin());

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionVisible('enviar_whatsapp')
            ->assertActionHasUrl('enviar_whatsapp', WhatsAppShare::url('543882222222', $ticket->fresh()->shareUrl()));
    }

    public function test_enviar_por_whatsapp_no_usa_el_telefono_de_otro_pasajero(): void
    {
        $this->sellLinkMode(['81000001', '81000002'], ['81000001' => '3881234567']);
        $sinTelefono = Ticket::query()->orderByDesc('id')->first();
        $this->actingAs($this->makeAdmin());

        Livewire::test(ViewTicket::class, ['record' => $sinTelefono->id])
            ->assertActionHidden('enviar_whatsapp');
    }

    public function test_enviar_por_whatsapp_oculto_con_el_parametro_activado_o_sin_configurar(): void
    {
        $ticket = $this->sellLinkMode(['81000001'], ['81000001' => '3881234567']);
        $this->actingAs($this->makeAdmin());

        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '1');
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionHidden('enviar_whatsapp');

        Setting::query()->where('key', Setting::DESCARGA_AUTOMATICA_PDF)->delete();
        Setting::flushCache();
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionHidden('enviar_whatsapp');
    }

    public function test_enviar_por_whatsapp_oculto_para_un_boleto_eliminado(): void
    {
        $ticket = $this->sellLinkMode(['81000001'], ['81000001' => '3881234567']);
        $ticket->delete();
        $this->actingAs($this->makeAdmin());

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionHidden('enviar_whatsapp');
    }

    public function test_en_modo_link_el_detalle_genera_el_link_y_ofrece_copiarlo(): void
    {
        $ticket = $this->sellLinkMode(['81000001']);
        $ticket->forceFill(['share_token' => null])->saveQuietly();
        $this->actingAs($this->makeAdmin());

        $component = Livewire::test(ViewTicket::class, ['record' => $ticket->id]);

        $shareUrl = $ticket->fresh()->shareUrl();
        $this->assertNotNull($ticket->fresh()->share_token, 'El detalle genera el link en modo link.');
        $this->assertNull($ticket->sale->fresh()->share_token, 'Nunca el link de la venta.');

        $component
            ->assertSeeHtml(Js::from($shareUrl)->toHtml())
            ->assertSee('Copiar link')
            ->assertSee('Abrir PDF')
            ->assertDontSee('Descargar PDF');
    }

    /**
     * Datos con los que se armó el PDF del boleto durante $request.
     *
     * @return array<string, mixed>
     */
    private function capturePdfData(callable $request): array
    {
        $captured = null;
        View::composer('tickets.pdf.passenger-tickets', function ($view) use (&$captured): void {
            $captured = $view->getData();
        });

        $request();

        $this->assertNotNull($captured, 'El link no armó el PDF del boleto.');

        return $captured;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    private function renderDeliveryModal(Collection $tickets): string
    {
        return view('filament.tickets.delivery-modal', TicketDelivery::payload($tickets))->render();
    }

    private function invokeRedirectUrl(): string
    {
        $method = new \ReflectionMethod(CreateTicket::class, 'getRedirectUrl');

        return $method->invoke(app(CreateTicket::class));
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
    private function sellLinkMode(array $dnis, array $telefonosPorDni = [], bool $fresh = true): Ticket
    {
        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');

        if ($fresh) {
            $this->fixture = $this->makeFixture();
            $this->actingAs(User::factory()->vendedor()->create());
        }

        $payload = $this->salePayload($this->fixture, $dnis, $telefonosPorDni);

        if (! $fresh) {
            // Otra venta en el mismo viaje: los asientos libres siguientes.
            $taken = Ticket::query()->count();
            $payload['seat_ids'] = array_map(fn (int $i): int => $taken + $i + 1, range(0, count($dnis) - 1));
        }

        $this->invokeHandleRecordCreation($payload);

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
