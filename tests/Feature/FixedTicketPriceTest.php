<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\FixedTicketPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedTicketPriceTest extends TestCase
{
    use RefreshDatabase;

    private function configure(bool $active, int $base, int $insurance): void
    {
        Setting::set(Setting::PRECIO_FIJO_ACTIVO, $active ? '1' : '0');
        Setting::set(Setting::PRECIO_FIJO_BOLETO, (string) $base);
        Setting::set(Setting::PRECIO_FIJO_SEGURO_MENOR, (string) $insurance);
    }

    public function test_sin_configurar_el_precio_fijo_esta_apagado_y_sin_montos(): void
    {
        $this->assertFalse(Setting::precioFijoActivo());
        $this->assertSame(0, Setting::precioFijoBoleto());
        $this->assertSame(0, Setting::precioFijoSeguroMenor());
        $this->assertFalse(FixedTicketPrice::isActive());
    }

    public function test_lee_los_valores_guardados(): void
    {
        $this->configure(true, 5000, 800);

        $this->assertTrue(Setting::precioFijoActivo());
        $this->assertSame(5000, Setting::precioFijoBoleto());
        $this->assertSame(800, Setting::precioFijoSeguroMenor());
    }

    public function test_un_monto_corrupto_vale_cero(): void
    {
        Setting::set(Setting::PRECIO_FIJO_BOLETO, 'abc');
        Setting::set(Setting::PRECIO_FIJO_SEGURO_MENOR, '-50');

        $this->assertSame(0, Setting::precioFijoBoleto());
        $this->assertSame(0, Setting::precioFijoSeguroMenor());
    }

    public function test_calcula_el_precio_de_cada_tipo_de_venta(): void
    {
        $this->configure(true, 5000, 800);

        $this->assertSame(5000, FixedTicketPrice::forPassenger(withChild: false, isRoundTrip: false));
        $this->assertSame(5800, FixedTicketPrice::forPassenger(withChild: true, isRoundTrip: false));
        $this->assertSame(10000, FixedTicketPrice::forPassenger(withChild: false, isRoundTrip: true));
        $this->assertSame(11600, FixedTicketPrice::forPassenger(withChild: true, isRoundTrip: true));
    }

    public function test_con_seguro_cero_el_menor_no_suma(): void
    {
        $this->configure(true, 5000, 0);

        $this->assertSame(5000, FixedTicketPrice::forPassenger(withChild: true, isRoundTrip: false));
        $this->assertSame(0, FixedTicketPrice::insuranceDelta(hadChild: false, hasChild: true));
    }

    public function test_el_ajuste_del_seguro_suma_resta_o_no_cambia(): void
    {
        $this->configure(true, 5000, 800);

        $this->assertSame(800, FixedTicketPrice::insuranceDelta(hadChild: false, hasChild: true));
        $this->assertSame(-800, FixedTicketPrice::insuranceDelta(hadChild: true, hasChild: false));
        $this->assertSame(0, FixedTicketPrice::insuranceDelta(hadChild: true, hasChild: true));
        $this->assertSame(0, FixedTicketPrice::insuranceDelta(hadChild: false, hasChild: false));
    }

    public function test_la_fotografia_cambia_si_cambia_la_configuracion(): void
    {
        $this->configure(true, 5000, 800);
        $before = FixedTicketPrice::snapshot();

        $this->assertSame('1|5000|800', $before);

        Setting::set(Setting::PRECIO_FIJO_BOLETO, '6000');
        $this->assertNotSame($before, FixedTicketPrice::snapshot());

        Setting::set(Setting::PRECIO_FIJO_ACTIVO, '0');
        $this->assertSame('0|6000|800', FixedTicketPrice::snapshot());
    }
}
