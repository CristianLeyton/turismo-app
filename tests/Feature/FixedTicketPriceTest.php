<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Ticket;
use App\Support\FixedTicketPrice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedTicketPriceTest extends TestCase
{
    use ConfiguresFixedPrice;
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

    /** Períodos del ejemplo de temporada: 70.000 desde el 20/11 y 160.000 desde el 02/12. */
    private function seasonPeriods(): void
    {
        Setting::set(Setting::PRECIO_FIJO_ACTIVO, '1');
        $this->setFixedPricePeriods([
            ['desde' => '2026-12-02', 'boleto' => 160000, 'seguro' => 8000],
            ['desde' => '2026-11-20', 'boleto' => 70000, 'seguro' => 5000],
        ]);
    }

    // ====================== Lectura de los períodos ======================

    public function test_sin_configurar_el_precio_fijo_esta_apagado_y_sin_periodos(): void
    {
        $this->assertFalse(Setting::precioFijoActivo());
        $this->assertSame([], Setting::precioFijoPeriodos());
        $this->assertFalse(FixedTicketPrice::isActive());
        $this->assertFalse(FixedTicketPrice::hasCurrentPeriod());
        $this->assertSame(0, FixedTicketPrice::basePrice());
        $this->assertSame(0, FixedTicketPrice::childInsurance());
    }

    public function test_los_periodos_se_leen_ordenados_por_desde(): void
    {
        $this->seasonPeriods();

        $this->assertSame([
            ['desde' => '2026-11-20', 'boleto' => 70000, 'seguro' => 5000],
            ['desde' => '2026-12-02', 'boleto' => 160000, 'seguro' => 8000],
        ], Setting::precioFijoPeriodos());
    }

    public function test_las_filas_invalidas_se_descartan(): void
    {
        Setting::set(Setting::PRECIO_FIJO_PERIODOS, json_encode([
            ['desde' => '2026-11-20', 'boleto' => 70000, 'seguro' => 5000],
            ['desde' => 'mañana', 'boleto' => 1, 'seguro' => 1],
            ['desde' => '2026-02-30', 'boleto' => 1, 'seguro' => 1],
            ['desde' => '2026-12-01', 'boleto' => 'abc', 'seguro' => 1],
            ['desde' => '2026-12-02', 'boleto' => 1, 'seguro' => -50],
            ['boleto' => 1, 'seguro' => 1],
            'basura',
        ]));

        $this->assertSame([
            ['desde' => '2026-11-20', 'boleto' => 70000, 'seguro' => 5000],
        ], Setting::precioFijoPeriodos());
    }

    public function test_un_json_corrupto_vale_lista_vacia(): void
    {
        Setting::set(Setting::PRECIO_FIJO_PERIODOS, '{no es json');

        $this->assertSame([], Setting::precioFijoPeriodos());
    }

    // ====================== Período vigente ======================

    public function test_dentro_de_un_periodo_rigen_sus_montos(): void
    {
        $this->seasonPeriods();
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));

        $this->assertTrue(FixedTicketPrice::hasCurrentPeriod());
        $this->assertSame(70000, FixedTicketPrice::basePrice());
        $this->assertSame(5000, FixedTicketPrice::childInsurance());
    }

    public function test_el_primer_dia_del_periodo_siguiente_rige_el_nuevo(): void
    {
        $this->seasonPeriods();
        $this->travelTo(Carbon::parse('2026-12-02 00:00'));

        $this->assertSame(168000, FixedTicketPrice::forPassenger(withChild: true, isRoundTrip: false));
    }

    public function test_el_ultimo_dia_de_un_periodo_sigue_su_monto(): void
    {
        $this->seasonPeriods();
        $this->travelTo(Carbon::parse('2026-12-01 23:59'));

        $this->assertSame(70000, FixedTicketPrice::basePrice());
    }

    public function test_despues_del_ultimo_periodo_sigue_rigiendo_su_monto(): void
    {
        $this->seasonPeriods();
        $this->travelTo(Carbon::parse('2027-03-15 10:00'));

        $this->assertSame(160000, FixedTicketPrice::basePrice());
    }

    public function test_sin_periodo_vigente_no_hay_montos(): void
    {
        $this->seasonPeriods();
        $this->travelTo(Carbon::parse('2026-11-19 10:00'));

        $this->assertFalse(FixedTicketPrice::hasCurrentPeriod());
        $this->assertNull(FixedTicketPrice::periodFor(today()));
        $this->assertSame(0, FixedTicketPrice::basePrice());
    }

    public function test_period_for_resuelve_cualquier_fecha(): void
    {
        $this->seasonPeriods();

        $this->assertSame(5000, FixedTicketPrice::periodFor(Carbon::parse('2026-11-20'))['seguro']);
        $this->assertSame(8000, FixedTicketPrice::periodFor(Carbon::parse('2026-12-02'))['seguro']);
        $this->assertNull(FixedTicketPrice::periodFor(Carbon::parse('2026-11-19')));
    }

    // ====================== Cálculo ======================

    public function test_calcula_el_precio_de_cada_tipo_de_venta(): void
    {
        $this->configureFixedPrice(true, 5000, 800);

        $this->assertSame(5000, FixedTicketPrice::forPassenger(withChild: false, isRoundTrip: false));
        $this->assertSame(5800, FixedTicketPrice::forPassenger(withChild: true, isRoundTrip: false));
        $this->assertSame(10000, FixedTicketPrice::forPassenger(withChild: false, isRoundTrip: true));
        $this->assertSame(11600, FixedTicketPrice::forPassenger(withChild: true, isRoundTrip: true));
    }

    public function test_con_seguro_cero_el_menor_no_suma(): void
    {
        $this->configureFixedPrice(true, 5000, 0);

        $this->assertSame(5000, FixedTicketPrice::forPassenger(withChild: true, isRoundTrip: false));
        $this->assertSame(0, FixedTicketPrice::insuranceDelta(hadChild: false, hasChild: true, saleDate: now()));
    }

    // ====================== Ajuste del seguro ======================

    public function test_el_ajuste_del_seguro_suma_resta_o_no_cambia(): void
    {
        $this->configureFixedPrice(true, 5000, 800);

        $this->assertSame(800, FixedTicketPrice::insuranceDelta(hadChild: false, hasChild: true, saleDate: now()));
        $this->assertSame(-800, FixedTicketPrice::insuranceDelta(hadChild: true, hasChild: false, saleDate: now()));
        $this->assertSame(0, FixedTicketPrice::insuranceDelta(hadChild: true, hasChild: true, saleDate: now()));
        $this->assertSame(0, FixedTicketPrice::insuranceDelta(hadChild: false, hasChild: false, saleDate: now()));
    }

    public function test_el_ajuste_usa_el_seguro_del_periodo_de_la_venta(): void
    {
        $this->seasonPeriods();
        $this->travelTo(Carbon::parse('2026-12-05 10:00'));

        $this->assertSame(-5000, FixedTicketPrice::insuranceDelta(hadChild: true, hasChild: false, saleDate: Carbon::parse('2026-11-25 10:00')));
        $this->assertSame(8000, FixedTicketPrice::insuranceDelta(hadChild: false, hasChild: true, saleDate: Carbon::parse('2026-12-03 10:00')));
    }

    public function test_una_venta_anterior_al_primer_periodo_usa_el_mas_antiguo(): void
    {
        $this->seasonPeriods();

        $this->assertSame(5000, FixedTicketPrice::insuranceDelta(hadChild: false, hasChild: true, saleDate: Carbon::parse('2026-10-10 10:00')));
    }

    public function test_el_dia_de_la_venta_se_toma_en_la_zona_horaria_de_la_app(): void
    {
        $this->seasonPeriods();

        // 02/12 02:00 UTC = 01/12 23:00 en Salta: todavía rige el período del 20/11.
        $saleDate = Carbon::parse('2026-12-02 02:00:00', 'UTC');

        $this->assertSame(5000, FixedTicketPrice::insuranceDelta(hadChild: false, hasChild: true, saleDate: $saleDate));
    }

    // ====================== Fotografía ======================

    public function test_la_fotografia_cambia_si_cambia_la_configuracion(): void
    {
        $this->configureFixedPrice(true, 5000, 800);
        $before = FixedTicketPrice::snapshot();

        $this->assertSame('1|5000|800', $before);

        $this->configureFixedPrice(true, 6000, 800);
        $this->assertNotSame($before, FixedTicketPrice::snapshot());

        Setting::set(Setting::PRECIO_FIJO_ACTIVO, '0');
        $this->assertSame('0|6000|800', FixedTicketPrice::snapshot());
    }

    public function test_la_fotografia_cambia_al_cruzar_la_medianoche_a_otro_periodo(): void
    {
        $this->seasonPeriods();

        $this->travelTo(Carbon::parse('2026-12-01 23:55'));
        $before = FixedTicketPrice::snapshot();

        $this->travelTo(Carbon::parse('2026-12-02 00:05'));

        $this->assertSame('1|70000|5000', $before);
        $this->assertSame('1|160000|8000', FixedTicketPrice::snapshot());
    }

    public function test_la_fotografia_no_cambia_al_cruzar_la_medianoche_en_el_mismo_periodo(): void
    {
        $this->seasonPeriods();

        $this->travelTo(Carbon::parse('2026-11-25 23:55'));
        $before = FixedTicketPrice::snapshot();

        $this->travelTo(Carbon::parse('2026-11-26 00:05'));

        $this->assertSame($before, FixedTicketPrice::snapshot());
    }

    // ====================== Datos de precio de la ida ======================

    public function test_con_precio_fijo_la_ida_registra_modo_fijo_y_el_seguro_de_hoy(): void
    {
        $this->configureFixedPrice(true, 5000, 800);

        $this->assertSame(
            ['price_mode' => Ticket::PRICE_MODE_FIXED, 'child_insurance' => 800],
            FixedTicketPrice::outboundPricingAttributes(),
        );
    }

    public function test_el_seguro_registrado_es_el_del_periodo_de_hoy(): void
    {
        $this->travelTo(Carbon::parse('2026-12-05 10:00'));
        $this->seasonPeriods();

        $this->assertSame(8000, FixedTicketPrice::outboundPricingAttributes()['child_insurance']);
    }

    public function test_con_precio_a_mano_la_ida_registra_modo_manual_sin_seguro(): void
    {
        $this->configureFixedPrice(false, 5000, 800);

        $this->assertSame(
            ['price_mode' => Ticket::PRICE_MODE_MANUAL, 'child_insurance' => null],
            FixedTicketPrice::outboundPricingAttributes(),
        );
    }
}
