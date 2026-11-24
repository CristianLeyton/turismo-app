<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\FixedTicketPrice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Migración de datos del change precios-por-periodo: los montos del precio
 * fijo pasan a ser un único período desde el día del deploy.
 */
class ConvertFixedPriceToPeriodsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_21_011940_convert_fixed_price_to_periods.php';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();
        $this->travelTo(Carbon::parse('2026-11-21 10:00'));
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_el_precio_fijo_prendido_pasa_a_un_periodo_desde_hoy_y_cobra_lo_mismo(): void
    {
        Setting::set(Setting::PRECIO_FIJO_ACTIVO, '1');
        Setting::set('sales.precio_fijo_boleto', '5000');
        Setting::set('sales.precio_fijo_seguro_menor', '800');

        $this->migration()->up();

        $this->assertTrue(Setting::precioFijoActivo());
        $this->assertSame([['desde' => '2026-11-21', 'boleto' => 5000, 'seguro' => 800]], Setting::precioFijoPeriodos());
        $this->assertSame(5800, FixedTicketPrice::forPassenger(withChild: true, isRoundTrip: false));
        $this->assertSame(10000, FixedTicketPrice::forPassenger(withChild: false, isRoundTrip: true));

        // Las claves viejas se conservan para poder volver atrás.
        $this->assertDatabaseHas('settings', ['key' => 'sales.precio_fijo_boleto', 'value' => '5000']);
        $this->assertDatabaseHas('settings', ['key' => 'sales.precio_fijo_seguro_menor', 'value' => '800']);
    }

    public function test_sin_seguro_cargado_el_periodo_queda_con_seguro_cero(): void
    {
        Setting::set('sales.precio_fijo_boleto', '5000');

        $this->migration()->up();

        $this->assertSame([['desde' => '2026-11-21', 'boleto' => 5000, 'seguro' => 0]], Setting::precioFijoPeriodos());
    }

    public function test_el_precio_fijo_nunca_configurado_queda_apagado_y_sin_periodos(): void
    {
        $this->migration()->up();

        $this->assertFalse(Setting::precioFijoActivo());
        $this->assertSame([], Setting::precioFijoPeriodos());
        $this->assertDatabaseMissing('settings', ['key' => Setting::PRECIO_FIJO_PERIODOS]);
    }

    public function test_un_precio_en_cero_o_corrupto_no_crea_periodos(): void
    {
        Setting::set('sales.precio_fijo_boleto', 'abc');
        $this->migration()->up();
        $this->assertDatabaseMissing('settings', ['key' => Setting::PRECIO_FIJO_PERIODOS]);

        Setting::set('sales.precio_fijo_boleto', '0');
        $this->migration()->up();
        $this->assertDatabaseMissing('settings', ['key' => Setting::PRECIO_FIJO_PERIODOS]);
    }

    public function test_no_pisa_periodos_ya_existentes(): void
    {
        Setting::set('sales.precio_fijo_boleto', '5000');
        Setting::set(Setting::PRECIO_FIJO_PERIODOS, json_encode([['desde' => '2026-11-01', 'boleto' => 9000, 'seguro' => 100]]));

        $this->migration()->up();

        $this->assertSame([['desde' => '2026-11-01', 'boleto' => 9000, 'seguro' => 100]], Setting::precioFijoPeriodos());
    }

    public function test_down_borra_los_periodos_y_conserva_las_claves_viejas(): void
    {
        Setting::set('sales.precio_fijo_boleto', '5000');
        $this->migration()->up();

        $this->migration()->down();

        $this->assertDatabaseMissing('settings', ['key' => Setting::PRECIO_FIJO_PERIODOS]);
        $this->assertSame([], Setting::precioFijoPeriodos());
        $this->assertDatabaseHas('settings', ['key' => 'sales.precio_fijo_boleto', 'value' => '5000']);
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }
}
