<?php

namespace Tests\Feature;

use App\Filament\Pages\TicketSettings;
use App\Filament\Resources\Tickets\Pages\RescheduleTicket;
use App\Filament\Resources\Tickets\TicketResource;
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
use App\Support\ServiceDays;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class TicketSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-settings@test.local',
            'username' => 'admin-settings',
            'password' => bcrypt('secreto123'),
            'is_admin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::flushCache();

        parent::tearDown();
    }

    // ====================== Página Configuración ======================

    public function test_la_pagina_configuracion_renderiza_para_admin(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->assertOk()
            ->assertSee('Parámetros')
            ->assertSee('Confirmación de contraseña')
            ->assertSee('Al borrar boletos')
            ->assertSee('Al reprogramar boletos')
            ->assertSee('Al editar boletos');

        $response = $this->get(TicketSettings::getUrl());

        $response->assertOk();
        $response->assertSee('Configuración');
    }

    public function test_no_admin_no_puede_acceder_a_configuracion(): void
    {
        $vendedor = User::create([
            'name' => 'Vendedor',
            'email' => 'vendedor-settings@test.local',
            'username' => 'vendedor-settings',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $this->actingAs($vendedor);

        $this->assertFalse(TicketSettings::canAccess());
        $this->assertFalse(TicketSettings::shouldRegisterNavigation());

        $response = $this->get(TicketSettings::getUrl());

        $response->assertForbidden();
    }

    public function test_guardar_configuracion_persiste_los_toggles(): void
    {
        $this->actingAs($this->admin);

        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE));
        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_EDIT));

        Livewire::test(TicketSettings::class)
            ->set('data.require_password_ticket_delete', true)
            ->set('data.require_password_ticket_reschedule', true)
            ->set('data.require_password_ticket_edit', true)
            ->call('save')
            ->assertOk();

        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE));
        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_EDIT));

        $this->assertDatabaseHas('settings', [
            'key' => Setting::REQUIRE_PASSWORD_TICKET_DELETE,
            'value' => '1',
        ]);

        // Apagarlos de vuelta.
        Livewire::test(TicketSettings::class)
            ->set('data.require_password_ticket_delete', false)
            ->call('save');

        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
    }

    // ====================== Venta fuera de término (horas) ======================

    public function test_la_pagina_muestra_las_horas_de_venta_y_el_default_es_cinco(): void
    {
        $this->actingAs($this->admin);

        Setting::flushCache();

        Livewire::test(TicketSettings::class)
            ->assertOk()
            ->assertSee('Venta fuera de término')
            ->assertSee('Horas de venta después de la salida')
            ->assertSet('data.venta_limite_horas', Setting::VENTA_LIMITE_HORAS_DEFAULT);
    }

    public function test_guardar_persiste_las_horas_de_venta(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 8)
            ->call('save')
            ->assertOk();

        $this->assertDatabaseHas('settings', [
            'key' => Setting::VENTA_LIMITE_HORAS,
            'value' => '8',
        ]);

        $this->assertSame(8, Setting::ventaLimiteHoras());

        // 0 es válido: desactiva el límite.
        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 0)
            ->call('save');

        $this->assertSame(0, Setting::ventaLimiteHoras());

        // Un valor negativo se normaliza a 0 (nunca habilita "al revés").
        Setting::set(Setting::VENTA_LIMITE_HORAS, '-3');
        $this->assertSame(0, Setting::ventaLimiteHoras());
    }

    // ====================== Días de servicio ======================

    public function test_la_pagina_carga_los_dias_de_servicio_actuales(): void
    {
        Carbon::setTestNow('2026-09-01');
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->assertSee('Días de servicio')
            ->assertSet('data.service_weekdays', ['1', '2', '3', '4', '5', '6'])
            ->assertSet('data.service_exceptions', fn (array $items): bool => array_values($items) === [
                ['date' => '2026-09-13', 'has_service' => '1', 'reason' => null],
            ]);
    }

    public function test_guardar_un_feriado_deja_la_fecha_sin_servicio(): void
    {
        Carbon::setTestNow('2026-09-01');
        $this->actingAs($this->admin);

        $this->assertTrue(\App\Support\ServiceDays::hasService('2026-12-25'));

        Livewire::test(TicketSettings::class)
            ->set('data.service_exceptions', [
                'a' => ['date' => '2026-09-13', 'has_service' => '1', 'reason' => null],
                'b' => ['date' => '2026-12-25', 'has_service' => '0', 'reason' => 'Navidad'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertFalse(\App\Support\ServiceDays::hasService('2026-12-25'));
        $this->assertSame('Navidad', \App\Support\ServiceDays::exceptionFor('2026-12-25')['reason']);
        $this->assertTrue(\App\Support\ServiceDays::hasService('2026-09-13'));
        $this->assertSame([1, 2, 3, 4, 5, 6], Setting::serviceWeekdays());
    }

    public function test_guardar_los_dias_de_la_semana(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.service_weekdays', ['1', '7'])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertSame([1, 7], Setting::serviceWeekdays());
    }

    public function test_una_fecha_repetida_se_rechaza_sin_cambiar_la_configuracion(): void
    {
        $this->actingAs($this->admin);

        $date = today()->addMonth()->format('Y-m-d');

        Livewire::test(TicketSettings::class)
            ->set('data.service_exceptions', [
                'a' => ['date' => $date, 'has_service' => '0', 'reason' => null],
                'b' => ['date' => $date, 'has_service' => '1', 'reason' => null],
            ])
            ->call('save')
            ->assertHasFormErrors(['service_exceptions.a.date' => 'Hay más de una excepción para la misma fecha.']);

        $this->assertDatabaseMissing('settings', ['key' => Setting::SERVICE_EXCEPTIONS]);
    }

    // ====================== Setting API ======================

    public function test_setting_get_bool_defaults_y_set(): void
    {
        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE, false));

        $this->assertTrue(Setting::getBool('clave.inexistente', true));

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');
        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '0');
        $this->assertFalse(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));

        // set() sobrescribe en lugar de duplicar.
        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');
        $this->assertSame(1, Setting::query()->where('key', Setting::REQUIRE_PASSWORD_TICKET_DELETE)->count());
    }

    // ====================== Líneas de contacto: lectura ======================

    public function test_sin_configuracion_no_hay_lineas_de_contacto(): void
    {
        $this->assertSame([], Setting::contactLines());
    }

    public function test_las_lineas_de_contacto_descartan_entradas_invalidas_y_recortan_al_maximo(): void
    {
        Setting::set(Setting::CONTACT_LINES, json_encode([
            ['name' => ' Orán ', 'phone' => ' 3878-804506 '],
            ['name' => 'Sin teléfono', 'phone' => '  '],
            'texto suelto',
            ['name' => '', 'phone' => '3873-309893'],
            ['name' => 'Salta', 'phone' => '387 4123456'],
            ['name' => 'Cuarta', 'phone' => '111111'],
        ]));

        $this->assertSame([
            ['name' => 'Orán', 'phone' => '3878-804506', 'dial' => '3878804506'],
            ['name' => null, 'phone' => '3873-309893', 'dial' => '3873309893'],
            ['name' => 'Salta', 'phone' => '387 4123456', 'dial' => '3874123456'],
        ], Setting::contactLines());
        $this->assertSame(3, Setting::CONTACT_LINES_MAX);
    }

    public function test_el_destino_del_enlace_conserva_solo_digitos_y_el_mas_inicial(): void
    {
        $this->assertSame('+5493878804506', Setting::dialablePhone('+54 9 (3878) 804-506'));
        $this->assertSame('3878804506', Setting::dialablePhone('(3878) 80-4506'));
    }

    // ====================== Líneas de contacto: página ======================

    public function test_guardar_lineas_de_contacto_conserva_el_orden_y_se_ven_al_recargar(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->assertSee('Líneas de contacto')
            ->assertSet('data.contact_lines', [])
            ->set('data.contact_lines', [
                'a' => ['name' => 'Orán', 'phone' => '3878-804506'],
                'b' => ['name' => 'Tartagal', 'phone' => ' 3873-309893 '],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertSame([
            ['name' => 'Orán', 'phone' => '3878-804506', 'dial' => '3878804506'],
            ['name' => 'Tartagal', 'phone' => '3873-309893', 'dial' => '3873309893'],
        ], Setting::contactLines());

        Livewire::test(TicketSettings::class)
            ->assertSet('data.contact_lines', fn (array $items): bool => array_values($items) === [
                ['name' => 'Orán', 'phone' => '3878-804506'],
                ['name' => 'Tartagal', 'phone' => '3873-309893'],
            ]);
    }

    public function test_una_linea_sin_nombre_se_guarda_con_nombre_nulo(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.contact_lines', ['a' => ['name' => '', 'phone' => '3878-804506']])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertNull(Setting::contactLines()[0]['name']);
    }

    public function test_un_telefono_con_letras_se_rechaza_sin_guardar(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.contact_lines', ['a' => ['name' => 'Orán', 'phone' => 'llamar al 3878']])
            ->call('save')
            ->assertHasFormErrors(['contact_lines.a.phone' => 'regex'])
            ->assertSee('Ingresá un teléfono válido (solo números, espacios, guiones, paréntesis y +).');

        $this->assertDatabaseMissing('settings', ['key' => Setting::CONTACT_LINES]);
    }

    public function test_un_telefono_con_formato_internacional_se_acepta(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.contact_lines', ['a' => ['name' => null, 'phone' => '+54 (3878) 804-506']])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertSame('+54 (3878) 804-506', Setting::contactLines()[0]['phone']);
        $this->assertSame('+543878804506', Setting::contactLines()[0]['dial']);
    }

    public function test_no_se_admiten_mas_lineas_que_el_maximo(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.contact_lines', [
                'a' => ['name' => 'Uno', 'phone' => '111111'],
                'b' => ['name' => 'Dos', 'phone' => '222222'],
                'c' => ['name' => 'Tres', 'phone' => '333333'],
                'd' => ['name' => 'Cuatro', 'phone' => '444444'],
            ])
            ->call('save')
            ->assertHasFormErrors(['contact_lines' => 'max']);

        $this->assertDatabaseMissing('settings', ['key' => Setting::CONTACT_LINES]);
    }

    // ====================== Pestañas y errores ======================

    /**
     * Badge de cada pestaña, por label, tal como lo calcula el formulario.
     *
     * @return array<string, mixed>
     */
    private function tabBadges(Testable $component): array
    {
        $tabs = collect($component->instance()->form->getFlatComponents())
            ->first(fn ($child): bool => $child instanceof Tabs);

        return collect($tabs->getChildSchema()->getComponents())
            ->mapWithKeys(fn (Tab $tab): array => [$tab->getLabel() => $tab->getBadge()])
            ->all();
    }

    public function test_las_pestanas_estan_en_orden_y_sin_errores_no_tienen_badge(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(TicketSettings::class);

        $this->assertSame(
            ['Venta' => null, 'Boleto' => null, 'Calendario' => null, 'Seguridad' => null],
            $this->tabBadges($component),
        );
    }

    public function test_un_telefono_invalido_marca_la_pestana_boleto(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(TicketSettings::class)
            ->set('data.contact_lines', ['a' => ['name' => 'Orán', 'phone' => 'llamar al 3878']])
            ->call('save')
            ->assertHasFormErrors(['contact_lines.a.phone']);

        $this->assertSame(
            ['Venta' => null, 'Boleto' => 1, 'Calendario' => null, 'Seguridad' => null],
            $this->tabBadges($component),
        );

        $component
            ->set('data.contact_lines', ['a' => ['name' => 'Orán', 'phone' => '3878-804506']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['Venta' => null, 'Boleto' => null, 'Calendario' => null, 'Seguridad' => null],
            $this->tabBadges($component),
        );
    }

    public function test_el_guardado_fallido_avisa_las_pestanas_con_errores(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(TicketSettings::class)
            ->set('data.contact_lines', ['a' => ['name' => 'Orán', 'phone' => 'llamar al 3878']])
            ->set('data.service_weekdays', [])
            ->call('save')
            ->assertNotified(
                \Filament\Notifications\Notification::make()
                    ->title('No se guardaron los cambios')
                    ->body('Revisá los campos marcados en Boleto y Calendario.')
                    ->danger(),
            );

        $this->assertSame(
            ['Venta' => null, 'Boleto' => 1, 'Calendario' => 1, 'Seguridad' => null],
            $this->tabBadges($component),
        );
    }

    public function test_el_guardado_es_todo_o_nada(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 8)
            ->set('data.contact_lines', ['a' => ['name' => 'Orán', 'phone' => 'llamar al 3878']])
            ->call('save')
            ->assertHasFormErrors(['contact_lines.a.phone']);

        Setting::flushCache();

        $this->assertSame(Setting::VENTA_LIMITE_HORAS_DEFAULT, Setting::ventaLimiteHoras());
        $this->assertDatabaseMissing('settings', ['key' => Setting::VENTA_LIMITE_HORAS]);
    }

    // ====================== Textos ======================

    public function test_la_explicacion_de_contrasena_aparece_una_sola_vez_y_los_permisos_van_en_tooltip(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(TicketSettings::class)
            ->assertSeeText('Se pide la contraseña de quien hace la acción. Si no coincide, la acción no se ejecuta.')
            ->assertDontSeeText('Vender en días sin servicio');

        $this->assertSame(1, substr_count($component->html(), 'Si no coincide'));

        $calendarHint = collect($component->instance()->form->getFlatComponents())
            ->first(fn ($child): bool => $child instanceof \Filament\Forms\Components\CheckboxList)
            ->getHintIconTooltip();

        $this->assertStringContainsString('Vender en días sin servicio', $calendarHint);
    }

    // ====================== Validaciones ======================

    public function test_las_horas_de_venta_con_decimales_se_rechazan(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 2.5)
            ->call('save')
            ->assertHasFormErrors(['venta_limite_horas' => 'integer'])
            ->assertSee('La cantidad de horas tiene que ser un número entero.');

        $this->assertDatabaseMissing('settings', ['key' => Setting::VENTA_LIMITE_HORAS]);
    }

    public function test_las_horas_de_venta_mayores_al_maximo_se_rechazan_en_espanol(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 800)
            ->call('save')
            ->assertHasFormErrors(['venta_limite_horas' => 'max'])
            ->assertSee('La cantidad de horas no puede ser mayor a 720.');
    }

    public function test_el_mensaje_de_whatsapp_sin_link_se_rechaza(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.descarga_automatica_pdf', false)
            ->set('data.whatsapp_mensaje', 'Gracias por viajar con nosotros')
            ->call('save')
            ->assertHasFormErrors(['whatsapp_mensaje'])
            ->assertSee('El mensaje tiene que incluir {link}');

        $this->assertDatabaseMissing('settings', ['key' => Setting::WHATSAPP_MENSAJE]);
    }

    public function test_el_mensaje_de_whatsapp_demasiado_largo_se_rechaza(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.descarga_automatica_pdf', false)
            ->set('data.whatsapp_mensaje', str_repeat('a', 300).' {link}')
            ->call('save')
            ->assertHasFormErrors(['whatsapp_mensaje' => 'max']);
    }

    public function test_el_mensaje_de_whatsapp_vacio_usa_el_texto_por_defecto(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::WHATSAPP_MENSAJE, 'Tu pasaje: {link}');

        Livewire::test(TicketSettings::class)
            ->set('data.descarga_automatica_pdf', false)
            ->set('data.whatsapp_mensaje', '')
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertSame(Setting::WHATSAPP_MENSAJE_DEFAULT, Setting::whatsappMensaje());
    }

    public function test_el_mensaje_de_whatsapp_oculto_no_se_pierde_al_guardar(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        Setting::set(Setting::WHATSAPP_MENSAJE, 'Tu pasaje: {link}');

        Livewire::test(TicketSettings::class)
            ->set('data.descarga_automatica_pdf', true)
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertTrue(Setting::descargaAutomaticaPdf());
        $this->assertSame('Tu pasaje: {link}', Setting::whatsappMensaje());

        Livewire::test(TicketSettings::class)
            ->set('data.descarga_automatica_pdf', false)
            ->assertSet('data.whatsapp_mensaje', 'Tu pasaje: {link}');
    }

    public function test_sin_dias_de_servicio_no_se_guarda(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.service_weekdays', [])
            ->call('save')
            ->assertHasFormErrors(['service_weekdays' => 'required'])
            ->assertSee('Elegí al menos un día con servicio.');

        Setting::flushCache();

        $this->assertSame(Setting::SERVICE_WEEKDAYS_DEFAULT, Setting::serviceWeekdays());
        $this->assertDatabaseMissing('settings', ['key' => Setting::SERVICE_WEEKDAYS]);
    }

    public function test_una_excepcion_con_fecha_pasada_se_rechaza(): void
    {
        Carbon::setTestNow('2026-11-04');
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.service_exceptions', [
                'a' => ['date' => '2026-11-03', 'has_service' => '0', 'reason' => null],
            ])
            ->call('save')
            ->assertHasFormErrors(['service_exceptions.a.date' => 'after_or_equal'])
            ->assertSee('La fecha no puede ser anterior a hoy.');

        $this->assertDatabaseMissing('settings', ['key' => Setting::SERVICE_EXCEPTIONS]);
    }

    public function test_una_excepcion_para_hoy_se_acepta(): void
    {
        Carbon::setTestNow('2026-11-04');
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.service_exceptions', [
                'a' => ['date' => '2026-11-04', 'has_service' => '0', 'reason' => null],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertFalse(ServiceDays::hasService('2026-11-04'));
    }

    // ====================== Excepciones vencidas ======================

    public function test_las_excepciones_vencidas_se_quitan_al_guardar_y_se_avisa(): void
    {
        Carbon::setTestNow('2026-11-04');
        $this->actingAs($this->admin);

        Setting::set(Setting::SERVICE_EXCEPTIONS, json_encode([
            ['date' => '2026-09-13', 'has_service' => true, 'reason' => null],
            ['date' => '2026-12-25', 'has_service' => false, 'reason' => 'Navidad'],
        ]));

        Livewire::test(TicketSettings::class)
            ->assertSet('data.service_exceptions', fn (array $items): bool => array_values($items) === [
                ['date' => '2026-12-25', 'has_service' => '0', 'reason' => 'Navidad'],
            ])
            ->set('data.venta_limite_horas', 6)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(
                \Filament\Notifications\Notification::make()
                    ->title('Configuración guardada')
                    ->body('Los cambios se aplicaron correctamente. Se quitó 1 excepción de una fecha que ya pasó.')
                    ->success(),
            );

        Setting::flushCache();

        $this->assertSame([
            ['date' => '2026-12-25', 'has_service' => false, 'reason' => 'Navidad'],
        ], Setting::serviceExceptions());
    }

    // ====================== Borrar boleto ======================

    private function createOneWayTicket(string $date = '2026-10-01', array $userOverrides = []): Ticket
    {
        $oran = Location::firstOrCreate(['name' => 'Orán S'], ['is_active' => true]);
        $salta = Location::firstOrCreate(['name' => 'Salta S'], ['is_active' => true]);

        $bus = Bus::firstOrCreate(
            ['plate' => 'SSS111'],
            ['name' => 'Linea S', 'seat_count' => 4, 'floors' => 1],
        );
        foreach ([1, 2, 3, 4] as $n) {
            Seat::firstOrCreate(
                ['bus_id' => $bus->id, 'seat_number' => (string) $n],
                ['is_active' => true, 'floor' => '1'],
            );
        }

        $route = Route::firstOrCreate(
            ['name' => 'Orán - Salta S'],
            ['bus_id' => $bus->id, 'is_active' => true],
        );
        RouteStop::firstOrCreate(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::firstOrCreate(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::firstOrCreate(
            ['route_id' => $route->id, 'name' => 'Mañana S'],
            ['departure_time' => '08:00', 'arrival_time' => '12:00', 'is_active' => true],
        );

        $trip = Trip::firstOrCreate(
            ['route_id' => $route->id, 'schedule_id' => $schedule->id, 'trip_date' => $date],
            ['bus_id' => $bus->id],
        );

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'sale_date' => now(),
            'total_amount' => 100,
        ]);

        $passenger = Passenger::firstOrCreate(
            ['dni' => '77766655'],
            ['first_name' => 'Juana', 'last_name' => 'Sosa', 'passenger_type' => 'adult'],
        );

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'seat_id' => Seat::where('bus_id', $bus->id)->where('seat_number', '1')->first()->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
        ]);
    }

    public function test_borrar_boleto_sin_setting_no_pide_password(): void
    {
        $this->actingAs($this->admin);

        $ticket = $this->createOneWayTicket();

        $this->get(TicketResource::getUrl('view', ['record' => $ticket->id]))
            ->assertOk();

        $ticket->delete();

        $this->assertSoftDeleted($ticket);
    }

    public function test_borrar_boleto_con_password_incorrecta_no_elimina(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');

        $ticket = $this->createOneWayTicket();

        Livewire::test(\App\Filament\Resources\Tickets\Pages\ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', ['password' => 'clave-equivocada'])
            ->assertHasActionErrors(['password']);

        // El boleto sigue vivo.
        $this->assertNotSoftDeleted($ticket);

        // Y también sin password.
        Livewire::test(\App\Filament\Resources\Tickets\Pages\ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', [])
            ->assertHasActionErrors(['password']);

        $this->assertNotSoftDeleted($ticket);
    }

    public function test_borrar_boleto_con_password_correcta_permite_eliminar(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');

        $ticket = $this->createOneWayTicket();

        Livewire::test(\App\Filament\Resources\Tickets\Pages\ViewTicket::class, ['record' => $ticket->id])
            ->callAction('delete', ['password' => 'secreto123', 'motivo' => 'Prueba de borrado'])
            ->assertHasNoActionErrors();

        $this->assertSoftDeleted($ticket->fresh());
        $this->assertSame($this->admin->id, $ticket->fresh()->deleted_by);
        $this->assertSame('Prueba de borrado', $ticket->fresh()->deletion_reason);
    }

    // ====================== Reprogramar boleto ======================

    public function test_reprogramar_con_setting_activo_exige_password(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE, '1');

        $ticket = $this->createOneWayTicket('2026-10-01');
        $originalTripId = $ticket->trip_id;

        // Via el servicio directamente (defensa server-side).
        $service = app(\App\Services\TicketRescheduleService::class);

        try {
            $service->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $ticket->trip->schedule_id,
                'seat_id' => $ticket->seat_id,
                'confirm_password' => 'clave-mala',
            ]);
            $this->fail('Se esperaba ValidationException por contraseña incorrecta.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('confirm_password', $e->errors());
        }

        $ticket->refresh();
        $this->assertSame($originalTripId, $ticket->trip_id);
        $this->assertSame(0, $ticket->dateChanges()->count());

        // Sin password tampoco pasa.
        try {
            $service->reschedule($ticket, [
                'scope' => 'outbound',
                'date' => '2026-10-05',
                'schedule_id' => $ticket->trip->schedule_id,
                'seat_id' => $ticket->seat_id,
            ]);
            $this->fail('Se esperaba ValidationException sin contraseña.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('confirm_password', $e->errors());
        }

        $ticket->refresh();
        $this->assertSame($originalTripId, $ticket->trip_id);
    }

    public function test_reprogramar_con_password_correcta_funciona(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE, '1');

        $ticket = $this->createOneWayTicket('2026-10-01');

        $service = app(\App\Services\TicketRescheduleService::class);

        $result = $service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $ticket->trip->schedule_id,
            'seat_id' => $ticket->seat_id,
            'confirm_password' => 'secreto123',
        ]);

        $ticket->refresh();

        $newTrip = Trip::query()->whereDate('trip_date', '2026-10-05')->where('schedule_id', $ticket->trip->schedule_id)->first();

        $this->assertNotNull($newTrip);
        $this->assertSame((int) $newTrip->id, (int) $ticket->trip_id);
        $this->assertSame(1, $ticket->dateChanges()->count());
    }

    public function test_reprogramar_sin_setting_no_pide_password(): void
    {
        $this->actingAs($this->admin);

        // Sin set(): el setting está apagado (default false).

        $ticket = $this->createOneWayTicket('2026-10-01');

        $service = app(\App\Services\TicketRescheduleService::class);

        $service->reschedule($ticket, [
            'scope' => 'outbound',
            'date' => '2026-10-05',
            'schedule_id' => $ticket->trip->schedule_id,
            'seat_id' => $ticket->seat_id,
            // Sin confirm_password: no debe importar.
        ]);

        $ticket->refresh();
        $this->assertSame(1, $ticket->dateChanges()->count());
    }

    public function test_el_formulario_de_reprogramacion_incluye_el_campo_cuando_el_setting_esta_activo(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE, '1');

        $ticket = $this->createOneWayTicket('2026-10-01');

        $component = Livewire::test(RescheduleTicket::class, ['record' => $ticket->id]);

        $html = $component->html();

        $this->assertStringContainsString('Tu contraseña', $html, 'El form debe pedir la contraseña con el setting activo.');

        // Apagar el setting: el campo desaparece.
        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE, '0');

        $component2 = Livewire::test(RescheduleTicket::class, ['record' => $ticket->id]);
        $this->assertStringNotContainsString('Tu contraseña', $component2->html());
    }
}
