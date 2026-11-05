<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Schemas\TicketRescheduleForm;
use App\Models\Bus;
use App\Models\Linea;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\Ticket;
use App\Services\TicketRescheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class LineaRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private Location $oran;

    private Location $salta;

    private Bus $regular;

    private Bus $refuerzo;

    private Bus $suelto;

    private Schedule $regularSchedule;

    private Schedule $refuerzoSchedule;

    private Schedule $sueltoSchedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $linea = Linea::create(['name' => 'Oran - Salta']);

        $this->regular = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 10, 'floors' => 1, 'linea_id' => $linea->id]);
        $this->refuerzo = Bus::create(['name' => 'Oran - Salta (Refuerzo)', 'plate' => 'BBB222', 'seat_count' => 10, 'floors' => 1, 'linea_id' => $linea->id, 'is_reinforcement' => true]);
        $this->suelto = Bus::create(['name' => 'Suelto', 'plate' => 'CCC333', 'seat_count' => 10, 'floors' => 1]);

        $this->regularSchedule = $this->createSchedule($this->regular, 'Oran - Salta', '08:00');
        $this->refuerzoSchedule = $this->createSchedule($this->refuerzo, 'Oran - Salta (Refuerzo)', '15:00');
        $this->sueltoSchedule = $this->createSchedule($this->suelto, 'Oran - Salta', '20:00');
    }

    private function createSchedule(Bus $bus, string $routeName, string $departure): Schedule
    {
        $route = Route::create(['bus_id' => $bus->id, 'name' => $routeName, 'is_active' => true]);

        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);

        return Schedule::create([
            'route_id' => $route->id,
            'name' => $routeName,
            'departure_time' => $departure,
            'arrival_time' => '23:00',
            'is_active' => true,
        ]);
    }

    private function callPrivate(object|string $target, string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(is_object($target) ? $target : null, ...$arguments);
    }

    public function test_reprogramar_un_tramo_del_regular_ofrece_horarios_de_la_linea(): void
    {
        $ticket = new Ticket(['bus_id' => $this->regular->id]);

        $options = $this->callPrivate(TicketRescheduleForm::class, 'scheduleOptions', $this->oran->id, $this->salta->id, $ticket);

        $this->assertArrayHasKey($this->regularSchedule->id, $options);
        $this->assertArrayHasKey($this->refuerzoSchedule->id, $options);
        $this->assertStringContainsString('· Oran - Salta (Refuerzo)', $options[$this->refuerzoSchedule->id]);
    }

    public function test_reprogramar_un_tramo_de_colectivo_sin_linea_ofrece_solo_sus_horarios(): void
    {
        $ticket = new Ticket(['bus_id' => $this->suelto->id]);

        $options = $this->callPrivate(TicketRescheduleForm::class, 'scheduleOptions', $this->oran->id, $this->salta->id, $ticket);

        $this->assertSame([$this->sueltoSchedule->id], array_keys($options));
    }

    public function test_boleto_viejo_sin_colectivo_no_se_restringe(): void
    {
        $options = $this->callPrivate(TicketRescheduleForm::class, 'scheduleOptions', $this->oran->id, $this->salta->id, new Ticket);

        $this->assertCount(3, $options);
    }

    public function test_el_servicio_acepta_un_horario_de_refuerzo_para_un_boleto_del_regular(): void
    {
        $ticket = new Ticket(['bus_id' => $this->regular->id]);

        $this->callPrivate(new TicketRescheduleService, 'validateScheduleForSegment', $this->refuerzoSchedule->id, $this->oran->id, $this->salta->id, 'schedule_id', now()->addDays(2)->format('Y-m-d'), $ticket);

        $this->assertTrue(true);
    }

    public function test_el_servicio_rechaza_un_horario_de_fuera_de_la_linea(): void
    {
        $ticket = new Ticket(['bus_id' => $this->regular->id]);

        $this->expectException(ValidationException::class);

        $this->callPrivate(new TicketRescheduleService, 'validateScheduleForSegment', $this->sueltoSchedule->id, $this->oran->id, $this->salta->id, 'schedule_id', now()->addDays(2)->format('Y-m-d'), $ticket);
    }

    public function test_el_servicio_no_restringe_boletos_sin_colectivo(): void
    {
        $this->callPrivate(new TicketRescheduleService, 'validateScheduleForSegment', $this->sueltoSchedule->id, $this->oran->id, $this->salta->id, 'schedule_id', now()->addDays(2)->format('Y-m-d'), new Ticket);

        $this->assertTrue(true);
    }
}
