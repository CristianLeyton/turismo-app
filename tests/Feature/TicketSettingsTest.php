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
use App\Support\Permissions;
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

        // El admin de estos tests configura Calendario y Seguridad; los casos
        // sin esos permisos usan adminWithoutTabPermissions().
        Permissions::syncToDatabase();
        $this->admin->givePermissionTo([TicketSettings::CALENDAR_PERMISSION, TicketSettings::SECURITY_PERMISSION]);
    }

    /**
     * Administrador con los permisos por defecto (sin calendario ni seguridad) más los indicados.
     */
    private function adminWithoutTabPermissions(string ...$permissions): User
    {
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo($permissions);

        return $admin->refresh();
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

    public function test_el_color_principal_por_defecto_es_fucsia_y_un_valor_corrupto_no_rompe(): void
    {
        $this->assertSame('#c026d3', Setting::primaryColor());

        Setting::set(Setting::PRIMARY_COLOR, '#10288C');
        $this->assertSame('#10288c', Setting::primaryColor());

        Setting::set(Setting::PRIMARY_COLOR, 'azul');
        $this->assertSame('#c026d3', Setting::primaryColor());

        Setting::set(Setting::PRIMARY_COLOR, '#12345');
        $this->assertSame('#c026d3', Setting::primaryColor());
    }

    public function test_los_selectores_detallados_estan_apagados_por_defecto(): void
    {
        $this->assertFalse(Setting::selectoresDetallados());

        Setting::set(Setting::SELECTORES_DETALLADOS, '1');
        $this->assertTrue(Setting::selectoresDetallados());
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
    // ====================== Panel de clientes ======================

    public function test_con_el_permiso_se_ve_la_pestana_clientes_desactivada_por_defecto(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $component = Livewire::test(TicketSettings::class)
            ->assertSet('data.panel_clientes_activo', false)
            ->assertSee('Panel de clientes activo');

        $this->assertSame(
            ['Venta', 'Boleto', 'Calendario', 'Seguridad', 'Clientes', 'Apariencia'],
            array_keys($this->tabBadges($component)),
        );
    }

    public function test_sin_el_permiso_no_se_ve_la_pestana_clientes(): void
    {
        $this->actingAs($this->admin);

        $this->assertFalse($this->admin->can('settings.client_panel'));

        $component = Livewire::test(TicketSettings::class)
            ->assertDontSee('Panel de clientes activo');

        $this->assertSame(
            ['Venta', 'Boleto', 'Calendario', 'Seguridad'],
            array_keys($this->tabBadges($component)),
        );
    }

    public function test_con_el_permiso_se_activa_el_panel_de_clientes(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(TicketSettings::class)
            ->set('data.panel_clientes_activo', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::panelClientesActivo());
        $this->get('/clientes/login')->assertOk();

        Livewire::test(TicketSettings::class)
            ->assertSet('data.panel_clientes_activo', true)
            ->set('data.panel_clientes_activo', false)
            ->call('save');

        $this->assertFalse(Setting::panelClientesActivo());
    }

    public function test_sin_el_permiso_un_pedido_manipulado_no_cambia_el_panel(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.panel_clientes_activo', true)
            ->set('data.require_password_ticket_delete', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertFalse(Setting::panelClientesActivo());
        $this->assertDatabaseMissing('settings', ['key' => Setting::PANEL_CLIENTES_ACTIVO]);
    }

    // ====================== Apariencia ======================

    private function adminWithPermissions(string ...$permissions): User
    {
        Permissions::syncToDatabase();

        $this->admin->givePermissionTo($permissions);

        return $this->admin->refresh();
    }

    public function test_con_el_permiso_de_apariencia_se_ve_su_pestana_al_final(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::APPEARANCE_PERMISSION));

        $component = Livewire::test(TicketSettings::class)
            ->assertSee('Color principal');

        $this->assertSame(
            ['Venta', 'Boleto', 'Calendario', 'Seguridad', 'Apariencia'],
            array_keys($this->tabBadges($component)),
        );
    }

    public function test_con_los_dos_permisos_se_ven_clientes_y_apariencia_en_orden(): void
    {
        $this->actingAs($this->adminWithPermissions(
            TicketSettings::CLIENT_PANEL_PERMISSION,
            TicketSettings::APPEARANCE_PERMISSION,
        ));

        $this->assertSame(
            ['Venta', 'Boleto', 'Calendario', 'Seguridad', 'Clientes', 'Apariencia'],
            array_keys($this->tabBadges(Livewire::test(TicketSettings::class))),
        );
    }

    public function test_sin_el_permiso_de_apariencia_no_se_ve_su_pestana(): void
    {
        $this->actingAs($this->admin);

        $this->assertFalse($this->admin->can(TicketSettings::APPEARANCE_PERMISSION));

        $component = Livewire::test(TicketSettings::class)
            ->assertDontSee('Color principal');

        $this->assertArrayNotHasKey('Apariencia', $this->tabBadges($component));
    }

    public function test_sin_configurar_el_color_principal_es_fucsia_y_la_vista_previa_lo_muestra(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::APPEARANCE_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->assertSet('data.primary_color', '#c026d3')
            ->assertSeeHtml('background-color: #c026d3');
    }

    public function test_la_vista_previa_muestra_el_color_elegido_sin_guardarlo(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::APPEARANCE_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->set('data.primary_color', '#10288c')
            ->assertSeeHtml('background-color: #10288c');

        $this->assertSame('#c026d3', Setting::primaryColor());
        $this->assertDatabaseMissing('settings', ['key' => Setting::PRIMARY_COLOR]);
    }

    public function test_con_el_permiso_se_guarda_el_color_principal(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::APPEARANCE_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->set('data.primary_color', '#10288C')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('#10288c', Setting::primaryColor());

        Livewire::test(TicketSettings::class)
            ->assertSet('data.primary_color', '#10288c');
    }

    public function test_un_color_invalido_se_rechaza_en_espanol_sin_guardar_nada(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::APPEARANCE_PERMISSION));

        foreach (['azul', '#12345'] as $invalidColor) {
            Livewire::test(TicketSettings::class)
                ->set('data.venta_limite_horas', 8)
                ->set('data.primary_color', $invalidColor)
                ->call('save')
                ->assertHasFormErrors(['primary_color' => 'regex'])
                ->assertSee('Elegí un color válido');

            $this->assertSame(Setting::VENTA_LIMITE_HORAS_DEFAULT, Setting::ventaLimiteHoras());
            $this->assertDatabaseMissing('settings', ['key' => Setting::PRIMARY_COLOR]);
        }
    }

    public function test_sin_el_permiso_un_pedido_manipulado_no_cambia_el_color(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.primary_color', '#10288c')
            ->set('data.require_password_ticket_delete', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertSame('#c026d3', Setting::primaryColor());
        $this->assertDatabaseMissing('settings', ['key' => Setting::PRIMARY_COLOR]);
    }

    // ====================== Selectores de origen y destino ======================

    public function test_con_el_permiso_se_ven_los_selectores_apagados_por_defecto_en_venta(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::SALE_SELECTORS_PERMISSION));

        $component = Livewire::test(TicketSettings::class)
            ->assertSet('data.selectores_detallados', false)
            ->assertSee('Selectores de origen y destino detallados')
            ->assertSee('Venta con acompañante')
            ->assertSee('Venta fuera de término');

        // Es una sección de Venta, no una pestaña nueva.
        $this->assertSame(
            ['Venta', 'Boleto', 'Calendario', 'Seguridad'],
            array_keys($this->tabBadges($component)),
        );
    }

    public function test_sin_el_permiso_no_se_ve_la_seccion_de_selectores(): void
    {
        $this->actingAs($this->admin);

        $this->assertFalse($this->admin->can(TicketSettings::SALE_SELECTORS_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->assertDontSee('Selectores de origen y destino detallados');
    }

    public function test_con_el_permiso_se_prenden_y_apagan_los_selectores_detallados(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::SALE_SELECTORS_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->set('data.selectores_detallados', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::selectoresDetallados());

        Livewire::test(TicketSettings::class)
            ->assertSet('data.selectores_detallados', true)
            ->set('data.selectores_detallados', false)
            ->call('save');

        $this->assertFalse(Setting::selectoresDetallados());
    }

    public function test_sin_el_permiso_un_pedido_manipulado_no_cambia_los_selectores(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.selectores_detallados', true)
            ->set('data.require_password_ticket_delete', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertFalse(Setting::selectoresDetallados());
        $this->assertDatabaseMissing('settings', ['key' => Setting::SELECTORES_DETALLADOS]);
    }

    // ====================== Observaciones de la venta ======================

    public function test_las_observaciones_estan_apagadas_por_defecto(): void
    {
        $this->assertFalse(Setting::observacionesActivas());

        Setting::set(Setting::OBSERVACIONES_VENTA, '1');
        $this->assertTrue(Setting::observacionesActivas());
    }

    public function test_con_el_permiso_se_ve_la_seccion_de_observaciones_apagada_en_venta(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::SALE_OBSERVATIONS_PERMISSION));

        $component = Livewire::test(TicketSettings::class)
            ->assertSet('data.observaciones_activas', false)
            ->assertSee('Observaciones en la venta')
            ->assertSee('Venta con acompañante');

        // Es una sección de Venta, no una pestaña nueva.
        $this->assertSame(
            ['Venta', 'Boleto', 'Calendario', 'Seguridad'],
            array_keys($this->tabBadges($component)),
        );
        $this->assertContains('observaciones_activas', TicketSettings::TAB_FIELDS['Venta']);
    }

    public function test_sin_el_permiso_no_se_ve_la_seccion_de_observaciones(): void
    {
        $this->actingAs($this->admin);

        $this->assertFalse($this->admin->can(TicketSettings::SALE_OBSERVATIONS_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->assertDontSee('Observaciones en la venta');
    }

    public function test_con_el_permiso_se_prenden_y_apagan_las_observaciones(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::SALE_OBSERVATIONS_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->set('data.observaciones_activas', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::observacionesActivas());

        Livewire::test(TicketSettings::class)
            ->assertSet('data.observaciones_activas', true)
            ->set('data.observaciones_activas', false)
            ->call('save');

        $this->assertFalse(Setting::observacionesActivas());
    }

    public function test_sin_el_permiso_un_pedido_manipulado_no_cambia_las_observaciones(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.observaciones_activas', true)
            ->set('data.require_password_ticket_delete', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertFalse(Setting::observacionesActivas());
        $this->assertDatabaseMissing('settings', ['key' => Setting::OBSERVACIONES_VENTA]);
    }

    // ====================== Precio fijo de los boletos ======================

    private function fixedPriceAdmin(): User
    {
        return $this->adminWithPermissions(TicketSettings::FIXED_PRICE_PERMISSION);
    }

    public function test_con_el_permiso_se_ve_la_seccion_de_precio_fijo_apagada_y_sin_montos(): void
    {
        $this->actingAs($this->fixedPriceAdmin());

        $component = Livewire::test(TicketSettings::class)
            ->assertSet('data.precio_fijo_activo', false)
            ->assertSee('Precio de los boletos')
            ->assertSee('Precio fijo activo')
            ->assertDontSee('Seguro para menores');

        $this->assertSame(
            ['Venta', 'Boleto', 'Calendario', 'Seguridad'],
            array_keys($this->tabBadges($component)),
        );
        $this->assertContains('precio_fijo_activo', TicketSettings::TAB_FIELDS['Venta']);
        $this->assertContains('precio_fijo_boleto', TicketSettings::TAB_FIELDS['Venta']);
        $this->assertContains('precio_fijo_seguro_menor', TicketSettings::TAB_FIELDS['Venta']);
    }

    public function test_sin_el_permiso_no_se_ve_la_seccion_de_precio_fijo(): void
    {
        $this->actingAs($this->admin);

        $this->assertFalse($this->admin->can(TicketSettings::FIXED_PRICE_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->assertDontSee('Precio de los boletos')
            ->assertDontSee('Precio fijo activo');
    }

    public function test_con_el_permiso_se_prende_el_precio_fijo_con_sus_montos(): void
    {
        $this->actingAs($this->fixedPriceAdmin());

        Livewire::test(TicketSettings::class)
            ->set('data.precio_fijo_activo', true)
            ->assertSee('Seguro para menores')
            ->assertSet('data.precio_fijo_boleto', null)
            ->set('data.precio_fijo_boleto', '5000')
            ->set('data.precio_fijo_seguro_menor', '800')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::precioFijoActivo());
        $this->assertSame(5000, Setting::precioFijoBoleto());
        $this->assertSame(800, Setting::precioFijoSeguroMenor());
    }

    public function test_apagar_el_precio_fijo_conserva_los_montos(): void
    {
        Setting::set(Setting::PRECIO_FIJO_ACTIVO, '1');
        Setting::set(Setting::PRECIO_FIJO_BOLETO, '5000');
        Setting::set(Setting::PRECIO_FIJO_SEGURO_MENOR, '800');
        $this->actingAs($this->fixedPriceAdmin());

        Livewire::test(TicketSettings::class)
            ->set('data.precio_fijo_activo', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(Setting::precioFijoActivo());

        Livewire::test(TicketSettings::class)
            ->set('data.precio_fijo_activo', true)
            ->assertSet('data.precio_fijo_boleto', 5000)
            ->assertSet('data.precio_fijo_seguro_menor', 800);
    }

    public function test_prender_el_precio_fijo_sin_montos_no_guarda_nada(): void
    {
        $this->actingAs($this->fixedPriceAdmin());

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 8)
            ->set('data.precio_fijo_activo', true)
            ->call('save')
            ->assertHasErrors([
                'data.precio_fijo_boleto' => 'Ingresá el precio del boleto.',
                'data.precio_fijo_seguro_menor' => 'Ingresá el seguro para menores.',
            ]);

        $this->assertFalse(Setting::precioFijoActivo());
        $this->assertSame(Setting::VENTA_LIMITE_HORAS_DEFAULT, Setting::ventaLimiteHoras());
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function invalidFixedPriceAmounts(): array
    {
        return [
            'precio en cero' => ['precio_fijo_boleto', '0', '800', 'El monto tiene que ser de al menos $1.'],
            'precio con centavos' => ['precio_fijo_boleto', '5000.50', '800', 'El monto tiene que ser un número entero, sin centavos.'],
            'seguro fuera de rango' => ['precio_fijo_seguro_menor', '5000', '1000000', 'El monto no puede ser mayor a $999.999.'],
            'seguro negativo' => ['precio_fijo_seguro_menor', '5000', '-1', 'El monto no puede ser negativo.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFixedPriceAmounts')]
    public function test_los_montos_invalidos_del_precio_fijo_no_se_guardan(string $field, string $price, string $insurance, string $message): void
    {
        $this->actingAs($this->fixedPriceAdmin());

        Livewire::test(TicketSettings::class)
            ->set('data.precio_fijo_activo', true)
            ->set('data.precio_fijo_boleto', $price)
            ->set('data.precio_fijo_seguro_menor', $insurance)
            ->call('save')
            ->assertHasErrors(["data.{$field}" => $message]);

        $this->assertFalse(Setting::precioFijoActivo());
        $this->assertDatabaseMissing('settings', ['key' => Setting::PRECIO_FIJO_BOLETO]);
    }

    public function test_el_seguro_en_cero_se_guarda(): void
    {
        $this->actingAs($this->fixedPriceAdmin());

        Livewire::test(TicketSettings::class)
            ->set('data.precio_fijo_activo', true)
            ->set('data.precio_fijo_boleto', '5000')
            ->set('data.precio_fijo_seguro_menor', '0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::precioFijoActivo());
        $this->assertSame(0, Setting::precioFijoSeguroMenor());
    }

    public function test_sin_el_permiso_guardar_conserva_el_precio_fijo(): void
    {
        Setting::set(Setting::PRECIO_FIJO_ACTIVO, '1');
        Setting::set(Setting::PRECIO_FIJO_BOLETO, '5000');
        Setting::set(Setting::PRECIO_FIJO_SEGURO_MENOR, '800');
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 8)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(8, Setting::ventaLimiteHoras());
        $this->assertTrue(Setting::precioFijoActivo());
        $this->assertSame(5000, Setting::precioFijoBoleto());
        $this->assertSame(800, Setting::precioFijoSeguroMenor());
    }

    public function test_sin_el_permiso_un_pedido_manipulado_no_cambia_el_precio_fijo(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TicketSettings::class)
            ->set('data.precio_fijo_activo', true)
            ->set('data.precio_fijo_boleto', '1')
            ->set('data.require_password_ticket_delete', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertFalse(Setting::precioFijoActivo());
        $this->assertDatabaseMissing('settings', ['key' => Setting::PRECIO_FIJO_ACTIVO]);
        $this->assertDatabaseMissing('settings', ['key' => Setting::PRECIO_FIJO_BOLETO]);
    }

    // ====================== Emisión al finalizar la venta ======================

    public function test_con_el_permiso_de_emision_la_pestana_boleto_muestra_emision_y_contacto(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::TICKET_EMISSION_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->assertSee('Emisión al finalizar la venta')
            ->assertSee('Descargar PDF automáticamente al vender')
            ->assertSee('Líneas de contacto');
    }

    public function test_sin_el_permiso_de_emision_la_pestana_boleto_muestra_solo_contacto(): void
    {
        $this->actingAs($this->admin);

        $this->assertFalse($this->admin->can(TicketSettings::TICKET_EMISSION_PERMISSION));

        $component = Livewire::test(TicketSettings::class)
            ->assertDontSee('Emisión al finalizar la venta')
            ->assertDontSee('Descargar PDF automáticamente al vender')
            ->assertDontSee('Texto del mensaje de WhatsApp')
            ->assertSee('Líneas de contacto');

        $this->assertArrayHasKey('Boleto', $this->tabBadges($component));
    }

    public function test_con_el_permiso_de_emision_se_guardan_la_descarga_y_el_mensaje(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::TICKET_EMISSION_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->set('data.descarga_automatica_pdf', false)
            ->set('data.whatsapp_mensaje', 'Tu pasaje: {link}')
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertFalse(Setting::descargaAutomaticaPdf());
        $this->assertSame('Tu pasaje: {link}', Setting::whatsappMensaje());
    }

    public function test_sin_el_permiso_de_emision_guardar_conserva_la_emision_por_link(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        Setting::set(Setting::WHATSAPP_MENSAJE, 'Tu pasaje: {link}');

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 8)
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertSame(8, Setting::ventaLimiteHoras());
        $this->assertFalse(Setting::descargaAutomaticaPdf());
        $this->assertSame('Tu pasaje: {link}', Setting::whatsappMensaje());
    }

    public function test_sin_el_permiso_de_emision_un_pedido_manipulado_no_cambia_la_emision(): void
    {
        $this->actingAs($this->admin);

        Setting::set(Setting::DESCARGA_AUTOMATICA_PDF, '0');
        Setting::set(Setting::WHATSAPP_MENSAJE, 'Tu pasaje: {link}');

        Livewire::test(TicketSettings::class)
            ->set('data.descarga_automatica_pdf', true)
            ->set('data.whatsapp_mensaje', 'Otro: {link}')
            ->set('data.require_password_ticket_delete', true)
            ->call('save')
            ->assertHasNoErrors();

        Setting::flushCache();

        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertFalse(Setting::descargaAutomaticaPdf());
        $this->assertSame('Tu pasaje: {link}', Setting::whatsappMensaje());
    }

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
        $this->actingAs($this->adminWithPermissions(TicketSettings::TICKET_EMISSION_PERMISSION));

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
        $this->actingAs($this->adminWithPermissions(TicketSettings::TICKET_EMISSION_PERMISSION));

        Livewire::test(TicketSettings::class)
            ->set('data.descarga_automatica_pdf', false)
            ->set('data.whatsapp_mensaje', str_repeat('a', 300).' {link}')
            ->call('save')
            ->assertHasFormErrors(['whatsapp_mensaje' => 'max']);
    }

    public function test_el_mensaje_de_whatsapp_vacio_usa_el_texto_por_defecto(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::TICKET_EMISSION_PERMISSION));

        Setting::set(Setting::WHATSAPP_MENSAJE, 'Tu pasaje: {link}');

        Livewire::test(TicketSettings::class)
            ->set('data.descarga_automatica_pdf', false)
            ->set('data.whatsapp_mensaje', '')
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertSame(Setting::WHATSAPP_MENSAJE_DEFAULT, Setting::whatsappMensaje());
        $this->assertSame('Podés acceder a tu(s) boleto(s) desde aquí: {link}', Setting::whatsappMensaje());
    }

    public function test_el_mensaje_de_whatsapp_oculto_no_se_pierde_al_guardar(): void
    {
        $this->actingAs($this->adminWithPermissions(TicketSettings::TICKET_EMISSION_PERMISSION));

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

    // ====================== Permisos de Calendario y Seguridad ======================

    public function test_sin_los_permisos_de_pestanas_solo_se_ven_venta_y_boleto(): void
    {
        $this->actingAs($this->adminWithoutTabPermissions());

        $component = Livewire::test(TicketSettings::class)
            ->assertDontSee('Confirmación de contraseña')
            ->assertDontSee('Días de la semana con servicio');

        $this->assertSame(['Venta', 'Boleto'], array_keys($this->tabBadges($component)));
    }

    public function test_con_un_solo_permiso_de_pestana_se_ve_solo_esa(): void
    {
        $this->actingAs($this->adminWithoutTabPermissions(TicketSettings::SECURITY_PERMISSION));

        $component = Livewire::test(TicketSettings::class)
            ->assertSee('Confirmación de contraseña')
            ->assertDontSee('Días de la semana con servicio');

        $this->assertSame(['Venta', 'Boleto', 'Seguridad'], array_keys($this->tabBadges($component)));
    }

    public function test_sin_calendario_ni_seguridad_clientes_y_apariencia_siguen_en_orden(): void
    {
        $this->actingAs($this->adminWithoutTabPermissions(TicketSettings::APPEARANCE_PERMISSION));

        $this->assertSame(
            ['Venta', 'Boleto', 'Apariencia'],
            array_keys($this->tabBadges(Livewire::test(TicketSettings::class))),
        );

        $this->actingAs($this->adminWithoutTabPermissions(
            TicketSettings::CLIENT_PANEL_PERMISSION,
            TicketSettings::APPEARANCE_PERMISSION,
        ));

        $this->assertSame(
            ['Venta', 'Boleto', 'Clientes', 'Apariencia'],
            array_keys($this->tabBadges(Livewire::test(TicketSettings::class))),
        );
    }

    public function test_con_todos_los_permisos_se_ven_todas_las_pestanas_en_orden(): void
    {
        $this->actingAs($this->adminWithoutTabPermissions(
            TicketSettings::CALENDAR_PERMISSION,
            TicketSettings::SECURITY_PERMISSION,
            TicketSettings::CLIENT_PANEL_PERMISSION,
            TicketSettings::APPEARANCE_PERMISSION,
        ));

        $this->assertSame(
            ['Venta', 'Boleto', 'Calendario', 'Seguridad', 'Clientes', 'Apariencia'],
            array_keys($this->tabBadges(Livewire::test(TicketSettings::class))),
        );
    }

    public function test_sin_el_permiso_de_calendario_guardar_conserva_el_calendario(): void
    {
        Carbon::setTestNow('2026-11-04');
        Setting::set(Setting::SERVICE_WEEKDAYS, json_encode([1, 2, 3, 4, 5]));
        Setting::set(Setting::SERVICE_EXCEPTIONS, json_encode([
            ['date' => '2026-12-25', 'has_service' => false, 'reason' => 'Navidad'],
        ]));

        $this->actingAs($this->adminWithoutTabPermissions());

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 8)
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertSame(8, Setting::getInt(Setting::VENTA_LIMITE_HORAS));
        $this->assertSame([1, 2, 3, 4, 5], Setting::serviceWeekdays());
        $this->assertSame([
            ['date' => '2026-12-25', 'has_service' => false, 'reason' => 'Navidad'],
        ], Setting::serviceExceptions());
    }

    public function test_sin_el_permiso_de_calendario_un_pedido_manipulado_no_cambia_el_calendario(): void
    {
        Carbon::setTestNow('2026-11-04');
        Setting::set(Setting::SERVICE_WEEKDAYS, json_encode([1, 2, 3, 4, 5]));
        $storedExceptions = Setting::serviceExceptions();

        $this->actingAs($this->adminWithoutTabPermissions());

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 8)
            ->set('data.service_weekdays', ['7'])
            ->set('data.service_exceptions', [
                'a' => ['date' => '2026-12-25', 'has_service' => '0', 'reason' => 'Navidad'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertSame(8, Setting::getInt(Setting::VENTA_LIMITE_HORAS));
        $this->assertSame([1, 2, 3, 4, 5], Setting::serviceWeekdays());
        $this->assertSame($storedExceptions, Setting::serviceExceptions());
        $this->assertNull(\App\Support\ServiceDays::exceptionFor('2026-12-25'));
    }

    public function test_sin_el_permiso_de_calendario_las_excepciones_vencidas_no_se_quitan_ni_se_avisan(): void
    {
        Carbon::setTestNow('2026-11-04');
        Setting::set(Setting::SERVICE_EXCEPTIONS, json_encode([
            ['date' => '2026-09-13', 'has_service' => true, 'reason' => null],
        ]));

        $this->actingAs($this->adminWithoutTabPermissions());

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 6)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(
                \Filament\Notifications\Notification::make()
                    ->title('Configuración guardada')
                    ->body('Los cambios se aplicaron correctamente.')
                    ->success(),
            );

        Setting::flushCache();

        $this->assertSame([
            ['date' => '2026-09-13', 'has_service' => true, 'reason' => null],
        ], Setting::serviceExceptions());
    }

    public function test_sin_el_permiso_de_seguridad_guardar_conserva_la_confirmacion(): void
    {
        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');

        $this->actingAs($this->adminWithoutTabPermissions());

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 8)
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertSame(8, Setting::getInt(Setting::VENTA_LIMITE_HORAS));
        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
    }

    public function test_sin_el_permiso_de_seguridad_un_pedido_manipulado_no_cambia_la_confirmacion(): void
    {
        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_DELETE, '1');
        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE, '1');
        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_EDIT, '1');

        $this->actingAs($this->adminWithoutTabPermissions());

        Livewire::test(TicketSettings::class)
            ->set('data.venta_limite_horas', 8)
            ->set('data.require_password_ticket_delete', false)
            ->set('data.require_password_ticket_reschedule', false)
            ->set('data.require_password_ticket_edit', false)
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushCache();

        $this->assertSame(8, Setting::getInt(Setting::VENTA_LIMITE_HORAS));
        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE));
        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE));
        $this->assertTrue(Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_EDIT));
    }

    public function test_la_pestana_calendario_oculta_no_aporta_errores(): void
    {
        $this->actingAs($this->adminWithoutTabPermissions());

        $component = Livewire::test(TicketSettings::class)
            ->set('data.contact_lines', ['a' => ['name' => 'Orán', 'phone' => 'llamar al 3878']])
            ->set('data.service_weekdays', [])
            ->call('save')
            ->assertHasFormErrors(['contact_lines.a.phone'])
            ->assertHasNoFormErrors(['service_weekdays'])
            ->assertNotified(
                \Filament\Notifications\Notification::make()
                    ->title('No se guardaron los cambios')
                    ->body('Revisá los campos marcados en Boleto.')
                    ->danger(),
            );

        $this->assertSame(['Venta' => null, 'Boleto' => 1], $this->tabBadges($component));
    }
}
