<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\ServiceDays;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regla de días de servicio (App\Support\ServiceDays) y su configuración
 * en Setting.
 */
class ServiceDaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    // ====================== Setting ======================

    public function test_sin_configuracion_guardada_rige_la_configuracion_inicial(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6], Setting::serviceWeekdays());
        $this->assertSame(
            [['date' => '2026-09-13', 'has_service' => true, 'reason' => null]],
            Setting::serviceExceptions(),
        );
    }

    public function test_con_configuracion_guardada_devuelve_lo_guardado(): void
    {
        Setting::set(Setting::SERVICE_WEEKDAYS, json_encode([7, 1, 1, 9]));
        Setting::set(Setting::SERVICE_EXCEPTIONS, json_encode([
            ['date' => '2026-12-25', 'has_service' => false, 'reason' => 'Navidad'],
        ]));

        $this->assertSame([1, 7], Setting::serviceWeekdays());
        $this->assertSame(
            [['date' => '2026-12-25', 'has_service' => false, 'reason' => 'Navidad']],
            Setting::serviceExceptions(),
        );
    }

    public function test_listas_vacias_guardadas_no_vuelven_al_default(): void
    {
        Setting::set(Setting::SERVICE_WEEKDAYS, '[]');
        Setting::set(Setting::SERVICE_EXCEPTIONS, '[]');

        $this->assertSame([], Setting::serviceWeekdays());
        $this->assertSame([], Setting::serviceExceptions());
    }

    // ====================== Regla ======================

    public function test_dia_de_la_semana_con_servicio_sin_excepcion(): void
    {
        $this->assertTrue(ServiceDays::hasService('2026-11-16'));
    }

    public function test_configuracion_inicial_domingos_sin_servicio_salvo_el_13_de_septiembre(): void
    {
        $this->assertFalse(ServiceDays::hasService('2026-09-06'));
        $this->assertTrue(ServiceDays::hasService('2026-09-13'));
        $this->assertFalse(ServiceDays::hasService('2026-09-20'));
        $this->assertTrue(ServiceDays::hasService('2026-09-19'));
    }

    public function test_excepcion_con_servicio_habilita_un_dia_sin_servicio(): void
    {
        $this->saveExceptions([['date' => '2026-11-22', 'has_service' => true, 'reason' => null]]);

        $this->assertTrue(ServiceDays::hasService('2026-11-22'));
    }

    public function test_excepcion_sin_servicio_bloquea_un_dia_con_servicio(): void
    {
        $this->saveExceptions([['date' => '2026-12-25', 'has_service' => false, 'reason' => 'Navidad']]);

        $this->assertFalse(ServiceDays::hasService('2026-12-25'));
        $this->assertSame('Navidad', ServiceDays::exceptionFor('2026-12-25')['reason']);
    }

    public function test_excepcion_repetida_gana_la_ultima(): void
    {
        $this->saveExceptions([
            ['date' => '2026-12-25', 'has_service' => false, 'reason' => null],
            ['date' => '2026-12-25', 'has_service' => true, 'reason' => null],
        ]);

        $this->assertTrue(ServiceDays::hasService('2026-12-25'));
    }

    public function test_fechas_deshabilitadas_en_un_rango(): void
    {
        $this->saveExceptions([['date' => '2026-12-25', 'has_service' => false, 'reason' => null]]);

        $disabled = ServiceDays::disabledDatesBetween(Carbon::parse('2026-12-20'), Carbon::parse('2026-12-27'));

        $this->assertSame(['2026-12-20', '2026-12-25', '2026-12-27'], $disabled);
    }

    public function test_motivo_de_bloqueo_menciona_fecha_y_motivo(): void
    {
        $this->saveExceptions([['date' => '2026-12-25', 'has_service' => false, 'reason' => 'Navidad']]);

        $this->assertNull(ServiceDays::blockReason('2026-12-24'));
        $this->assertNull(ServiceDays::blockReason(null));
        $this->assertStringContainsString('25/12/2026 es un día sin servicio (Navidad)', ServiceDays::blockReason('2026-12-25'));
    }

    /**
     * @param  list<array{date: string, has_service: bool, reason: ?string}>  $exceptions
     */
    private function saveExceptions(array $exceptions): void
    {
        Setting::set(Setting::SERVICE_EXCEPTIONS, json_encode($exceptions));
    }
}
