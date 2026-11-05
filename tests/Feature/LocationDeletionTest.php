<?php

namespace Tests\Feature;

use App\Filament\Clusters\Locations\Resources\Locations\LocationResource;
use App\Filament\Clusters\Locations\Resources\Locations\Pages\ManageLocations;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocationDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Bus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->bus = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 40, 'floors' => 1]);
    }

    private function routeThrough(string $name, Location ...$locations): Route
    {
        $route = Route::create(['name' => $name, 'bus_id' => $this->bus->id, 'is_active' => true]);

        foreach ($locations as $index => $location) {
            RouteStop::create([
                'route_id' => $route->id,
                'location_id' => $location->id,
                'stop_order' => $index + 1,
                'departure_offset_minutes' => $index * 30,
            ]);
        }

        return $route;
    }

    private function location(string $name): Location
    {
        return Location::create(['name' => $name, 'is_active' => true]);
    }

    public function test_un_destino_usado_en_rutas_avisa_y_no_se_puede_eliminar(): void
    {
        $pichanal = $this->location('Pichanal');
        $this->routeThrough('Oran - Salta', $this->location('Orán'), $pichanal);
        $this->routeThrough('Tartagal - Salta', $this->location('Tartagal'), $pichanal);

        Livewire::test(ManageLocations::class)
            ->mountAction(TestAction::make(DeleteAction::class)->table($pichanal))
            ->assertMountedActionModalSee('No se puede eliminar el destino')
            ->assertMountedActionModalSee('Este destino está en el recorrido de 2 rutas: Oran - Salta y Tartagal - Salta.')
            ->callMountedAction();

        $this->assertNotSoftDeleted($pichanal);
    }

    public function test_un_destino_sin_rutas_se_puede_eliminar(): void
    {
        $tartagal = $this->location('Tartagal');

        Livewire::test(ManageLocations::class)
            ->mountAction(TestAction::make(DeleteAction::class)->table($tartagal))
            ->assertMountedActionModalDontSee('No se puede eliminar el destino')
            ->callMountedAction();

        $this->assertSoftDeleted($tartagal);
    }

    public function test_un_destino_solo_en_rutas_eliminadas_o_paradas_eliminadas_se_puede_eliminar(): void
    {
        $pichanal = $this->location('Pichanal');
        $this->routeThrough('Ruta vieja', $pichanal)->delete();
        $otra = $this->routeThrough('Oran - Salta', $this->location('Orán'), $pichanal);
        RouteStop::query()->where('route_id', $otra->id)->where('location_id', $pichanal->id)->first()->delete();

        $this->assertTrue(LocationResource::routesUsing($pichanal)->isEmpty());

        Livewire::test(ManageLocations::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($pichanal));

        $this->assertSoftDeleted($pichanal);
    }

    public function test_eliminar_definitivamente_un_destino_usado_no_borra_las_paradas(): void
    {
        $pichanal = $this->location('Pichanal');
        $route = $this->routeThrough('Oran - Salta', $this->location('Orán'), $pichanal);
        $pichanal->delete();

        Livewire::test(ManageLocations::class)
            ->filterTable('trashed', true)
            ->mountAction(TestAction::make(ForceDeleteAction::class)->table($pichanal))
            ->assertMountedActionModalSee('No se puede eliminar el destino')
            ->callMountedAction();

        $this->assertNotNull(Location::withTrashed()->find($pichanal->id));
        $this->assertSame(2, $route->stops()->count());
    }

    public function test_eliminar_en_lote_se_corta_si_algun_destino_esta_en_una_ruta(): void
    {
        $pichanal = $this->location('Pichanal');
        $libre = $this->location('Libre');
        $this->routeThrough('Oran - Salta', $this->location('Orán'), $pichanal);

        Livewire::test(ManageLocations::class)
            ->selectTableRecords([$pichanal->getKey(), $libre->getKey()])
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
            ->assertNotified('No se eliminó ningún destino');

        $this->assertNotSoftDeleted($pichanal);
        $this->assertNotSoftDeleted($libre);
    }

    public function test_editar_un_destino_usado_avisa_en_que_rutas_se_ve_el_cambio(): void
    {
        $pichanal = $this->location('Pichanal');
        $this->routeThrough('Oran - Salta', $this->location('Orán'), $pichanal);

        Livewire::test(ManageLocations::class)
            ->mountAction(TestAction::make(EditAction::class)->table($pichanal))
            ->assertMountedActionModalSee('Este destino se usa en la ruta Oran - Salta. Si cambiás el nombre, se va a ver así en esos recorridos y en los boletos que lo tienen.')
            ->fillForm(['name' => 'Pichanal (terminal)'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame('Pichanal (terminal)', $pichanal->fresh()->name);
    }

    public function test_editar_un_destino_sin_rutas_no_muestra_aviso(): void
    {
        $libre = $this->location('Libre');

        Livewire::test(ManageLocations::class)
            ->mountAction(TestAction::make(EditAction::class)->table($libre))
            ->assertMountedActionModalDontSee('Este destino se usa');
    }

    public function test_el_listado_muestra_en_cuantas_rutas_se_usa_cada_destino(): void
    {
        $pichanal = $this->location('Pichanal');
        $libre = $this->location('Libre');
        $this->routeThrough('Oran - Salta', $this->location('Orán'), $pichanal);
        $this->routeThrough('Tartagal - Salta', $this->location('Tartagal'), $pichanal);
        $this->routeThrough('Ruta vieja', $pichanal)->delete();

        Livewire::test(ManageLocations::class)
            ->assertTableColumnStateSet('routes_count', 2, $pichanal->getKey())
            ->assertTableColumnStateSet('routes_count', 0, $libre->getKey());
    }

    public function test_con_muchas_rutas_el_aviso_resume_los_nombres(): void
    {
        $routes = Route::query()->getModel()->newCollection(
            collect(range(1, 7))->map(fn (int $i): Route => new Route(['name' => "Ruta {$i}"]))->all(),
        );

        $this->assertSame('Ruta 1, Ruta 2, Ruta 3, Ruta 4, Ruta 5 y 2 más', LocationResource::routeNames($routes));
    }
}
