<?php

namespace Tests\Feature;

use App\Filament\Resources\Buses\Pages\DesignBusLayout;
use App\Models\Bus;
use App\Models\BusLayoutArea;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tipos de zona del diseñador de colectivos: Escalera y Bar se marcan,
 * se guardan con su etiqueta por defecto y aparecen en el selector y la
 * leyenda.
 */
class BusLayoutAreaTypesTest extends TestCase
{
    use RefreshDatabase;

    private Bus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->bus = Bus::create(['name' => 'Doble piso', 'plate' => 'DDD111', 'seat_count' => 0, 'floors' => 2]);
    }

    public function test_el_diseñador_ofrece_escalera_y_bar(): void
    {
        Livewire::test(DesignBusLayout::class, ['record' => $this->bus->id])
            ->assertSeeHtml('<option value="stairs">Escalera</option>')
            ->assertSeeHtml('<option value="bar">Bar</option>')
            ->assertSee('Escalera')
            ->assertSee('Bar');
    }

    public function test_se_marca_y_guarda_una_escalera(): void
    {
        Livewire::test(DesignBusLayout::class, ['record' => $this->bus->id])
            ->set('pendingAreaType', 'stairs')
            ->call('completeRect', 3, 0, 4, 0)
            ->call('save')
            ->assertNotified('Layout guardado');

        $area = BusLayoutArea::query()->where('bus_id', $this->bus->id)->sole();

        $this->assertSame('stairs', $area->area_type);
        $this->assertSame('ESCALERA', $area->label);
        $this->assertSame(2, (int) $area->span_rows);
    }

    public function test_se_marca_y_guarda_un_bar_en_la_planta_alta(): void
    {
        Livewire::test(DesignBusLayout::class, ['record' => $this->bus->id])
            ->call('setFloor', 2)
            ->set('pendingAreaType', 'bar')
            ->call('completeRect', 0, 0, 0, 1)
            ->call('save')
            ->assertNotified('Layout guardado');

        $area = BusLayoutArea::query()->where('bus_id', $this->bus->id)->sole();

        $this->assertSame('bar', $area->area_type);
        $this->assertSame('BAR', $area->label);
        $this->assertSame(2, (int) $area->floor);
    }

    public function test_cambiar_una_zona_a_bar_usa_su_etiqueta(): void
    {
        Livewire::test(DesignBusLayout::class, ['record' => $this->bus->id])
            ->set('pendingAreaType', 'cafeteria')
            ->call('completeRect', 1, 1, 1, 1)
            ->call('updateArea', 1, 1, 'bar')
            ->assertSet('areas.0.area_type', 'bar')
            ->assertSet('areas.0.label', 'BAR');
    }
}
