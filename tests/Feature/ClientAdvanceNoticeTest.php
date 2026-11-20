<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Route as BusRoute;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\Setting;
use App\Support\ClientAdvanceNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anticipación mínima del panel de clientes: parámetro con su valor por
 * defecto y la regla (salida del origen del servicio).
 */
class ClientAdvanceNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ahora: 10/11/2026 10:00.
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10 10:00:00'));
    }

    private function scheduleAt(string $departure, ?string $stopTime = null): Schedule
    {
        static $n = 0;
        $n++;

        $bus = Bus::create(['name' => "Coche {$n}", 'plate' => "AN{$n}00", 'seat_count' => 4, 'floors' => 1]);
        $route = BusRoute::create(['name' => "Ruta {$n}", 'bus_id' => $bus->id, 'is_active' => true]);

        foreach ([1 => 'Origen', 2 => 'Destino'] as $order => $name) {
            RouteStop::create([
                'route_id' => $route->id,
                'location_id' => Location::firstOrCreate(['name' => $name], ['is_active' => true])->id,
                'stop_order' => $order,
            ]);
        }

        return Schedule::create([
            'route_id' => $route->id,
            'name' => 'Servicio',
            'departure_time' => $departure,
            'arrival_time' => '23:00',
            'is_active' => true,
        ]);
    }

    private function setHours(?string $value): void
    {
        Setting::set(Setting::CLIENT_ADVANCE_NOTICE_HOURS, $value);
    }

    public function test_sin_configurar_son_24_horas(): void
    {
        $this->assertSame(24, Setting::clientAdvanceNoticeHours());
        $this->assertSame(24, ClientAdvanceNotice::hours());
        $this->assertTrue(ClientAdvanceNotice::enabled());
    }

    public function test_devuelve_el_valor_guardado_incluido_cero(): void
    {
        $this->setHours('48');
        $this->assertSame(48, Setting::clientAdvanceNoticeHours());

        $this->setHours('0');
        $this->assertSame(0, Setting::clientAdvanceNoticeHours());
        $this->assertFalse(ClientAdvanceNotice::enabled());
    }

    public function test_un_valor_guardado_invalido_vuelve_al_defecto(): void
    {
        foreach (['abc', '-5', '9999', '1.5', ''] as $invalid) {
            $this->setHours($invalid);

            $this->assertSame(24, Setting::clientAdvanceNoticeHours(), "Valor guardado: '{$invalid}'");
        }
    }

    public function test_el_maximo_es_valido(): void
    {
        $this->setHours('720');

        $this->assertSame(720, Setting::clientAdvanceNoticeHours());
    }

    public function test_un_horario_dentro_del_plazo_no_se_permite(): void
    {
        // 24hs desde el 10/11 10:00: el 11/11 a las 08:00 sale con menos de 24hs.
        $this->assertFalse(ClientAdvanceNotice::allows($this->scheduleAt('08:00'), '2026-11-11'));
    }

    public function test_un_horario_con_anticipacion_suficiente_se_permite(): void
    {
        $this->assertTrue(ClientAdvanceNotice::allows($this->scheduleAt('12:00'), '2026-11-11'));
        $this->assertTrue(ClientAdvanceNotice::allows($this->scheduleAt('08:00'), '2026-11-12'));
    }

    public function test_exactamente_en_el_limite_se_permite(): void
    {
        $this->assertTrue(ClientAdvanceNotice::allows($this->scheduleAt('10:00'), '2026-11-11'));
        $this->assertFalse(ClientAdvanceNotice::allows($this->scheduleAt('09:59'), '2026-11-11'));
    }

    public function test_cuenta_la_salida_del_origen_y_no_la_de_la_parada_del_pasajero(): void
    {
        // Sale del origen a las 08:00; el pasajero sube más tarde en su parada.
        $schedule = $this->scheduleAt('08:00');
        RouteStop::query()->where('route_id', $schedule->route_id)->where('stop_order', 1)->update(['departure_offset_minutes' => 600]);

        $this->assertFalse(ClientAdvanceNotice::allows($schedule, '2026-11-11'), 'Se cuenta desde las 08:00, no desde la parada.');
    }

    public function test_con_cero_horas_no_se_excluye_ningun_horario(): void
    {
        $this->setHours('0');

        $this->assertTrue(ClientAdvanceNotice::allows($this->scheduleAt('08:00'), '2026-11-10'));
        $this->assertTrue(ClientAdvanceNotice::allows($this->scheduleAt('09:00'), '2026-11-10'));
    }

    public function test_cambiar_las_horas_cambia_el_resultado(): void
    {
        $schedule = $this->scheduleAt('12:00');

        $this->assertTrue(ClientAdvanceNotice::allows($schedule, '2026-11-11'));

        $this->setHours('48');

        $this->assertFalse(ClientAdvanceNotice::allows($schedule, '2026-11-11'));
    }

    public function test_los_mensajes_dicen_las_horas_configuradas(): void
    {
        $this->setHours('48');

        $this->assertSame('Elegí un horario con al menos 48hs de anticipación.', ClientAdvanceNotice::message());
        $this->assertStringContainsString('48hs', ClientAdvanceNotice::emptyOptionsMessage());
    }
}
