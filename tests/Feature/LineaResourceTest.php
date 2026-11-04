<?php

namespace Tests\Feature;

use App\Filament\Resources\Buses\Pages\ManageBuses;
use App\Filament\Resources\Lineas\LineaResource;
use App\Filament\Resources\Lineas\Pages\ManageLineas;
use App\Models\Bus;
use App\Models\Linea;
use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LineaResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    public function test_puede_crear_una_linea(): void
    {
        Livewire::test(ManageLineas::class)
            ->callAction(CreateAction::class, data: ['name' => 'Oran - Salta'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('lineas', ['name' => 'Oran - Salta']);
    }

    public function test_no_permite_dos_lineas_con_el_mismo_nombre(): void
    {
        Linea::create(['name' => 'Oran - Salta']);

        Livewire::test(ManageLineas::class)
            ->callAction(CreateAction::class, data: ['name' => 'Oran - Salta'])
            ->assertHasActionErrors(['name' => 'unique']);

        $this->assertSame(1, Linea::query()->count());
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        Livewire::test(ManageLineas::class)
            ->callAction(CreateAction::class, data: ['name' => ''])
            ->assertHasActionErrors(['name' => 'required']);

        $this->assertSame(0, Linea::query()->count());
    }

    public function test_puede_editar_el_nombre_de_una_linea_sin_chocar_consigo_misma(): void
    {
        $linea = Linea::create(['name' => 'Oran - Salta']);

        Livewire::test(ManageLineas::class)
            ->callTableAction(EditAction::class, $linea, data: ['name' => 'Oran - Salta (Ida y vuelta)'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Oran - Salta (Ida y vuelta)', $linea->fresh()->name);
    }

    public function test_asignar_linea_y_refuerzo_desde_el_formulario_de_colectivo(): void
    {
        $linea = Linea::create(['name' => 'Oran - Salta']);
        $bus = Bus::create(['name' => 'Oran - Salta (Refuerzo)', 'plate' => 'BBB222', 'seat_count' => 10, 'floors' => 1]);

        Livewire::test(ManageBuses::class)
            ->callTableAction(EditAction::class, $bus, data: [
                'name' => $bus->name,
                'plate' => $bus->plate,
                'floors' => 1,
                'seat_count' => 10,
                'linea_id' => $linea->id,
                'is_reinforcement' => true,
            ])
            ->assertHasNoTableActionErrors();

        $bus->refresh();
        $this->assertSame($linea->id, $bus->linea_id);
        $this->assertTrue($bus->is_reinforcement);
    }

    public function test_vendedor_no_puede_acceder_a_las_lineas(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $this->assertFalse(LineaResource::canViewAny());
        $this->get(LineaResource::getUrl('index'))->assertForbidden();
    }

    public function test_administrador_no_accede_a_las_lineas_por_defecto(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertFalse(LineaResource::canViewAny());
        $this->get(LineaResource::getUrl('index'))->assertForbidden();
    }

    public function test_rol_con_solo_ver_listado_no_puede_crear_editar_ni_eliminar(): void
    {
        $linea = Linea::create(['name' => 'Oran - Salta']);
        $this->actingAs($this->userWithLineaPermissions(['lineas.view_any']));

        $this->get(LineaResource::getUrl('index'))->assertOk();

        Livewire::test(ManageLineas::class)
            ->assertCanSeeTableRecords([$linea])
            ->assertActionHidden(CreateAction::class)
            ->assertTableActionHidden(EditAction::class, $linea)
            ->assertTableActionHidden(DeleteAction::class, $linea);
    }

    public function test_rol_con_permisos_completos_puede_gestionar_lineas(): void
    {
        $linea = Linea::create(['name' => 'Oran - Salta']);
        $this->actingAs($this->userWithLineaPermissions([
            'lineas.view_any', 'lineas.view', 'lineas.create', 'lineas.update', 'lineas.delete',
        ]));

        Livewire::test(ManageLineas::class)
            ->callAction(CreateAction::class, data: ['name' => 'Salta - Jujuy'])
            ->assertHasNoActionErrors()
            ->callTableAction(EditAction::class, $linea, data: ['name' => 'Oran - Salta (Ida y vuelta)'])
            ->assertHasNoTableActionErrors()
            ->callTableAction(DeleteAction::class, $linea);

        $this->assertDatabaseHas('lineas', ['name' => 'Salta - Jujuy']);
        $this->assertDatabaseMissing('lineas', ['id' => $linea->id]);
    }

    public function test_colectivo_sin_linea_queda_sin_linea_al_editarlo(): void
    {
        $bus = Bus::create(['name' => 'Suelto', 'plate' => 'DDD444', 'seat_count' => 10, 'floors' => 1]);

        Livewire::test(ManageBuses::class)
            ->callTableAction(EditAction::class, $bus, data: [
                'name' => 'Suelto',
                'plate' => 'DDD444',
                'floors' => 1,
                'seat_count' => 10,
                'linea_id' => null,
                'is_reinforcement' => false,
            ])
            ->assertHasNoTableActionErrors();

        $bus->refresh();
        $this->assertNull($bus->linea_id);
        $this->assertFalse($bus->is_reinforcement);
    }

    public function test_super_administrador_accede_a_las_lineas_sin_asignacion_explicita(): void
    {
        $this->assertTrue(LineaResource::canViewAny());
        $this->get(LineaResource::getUrl('index'))->assertOk();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithLineaPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'Gestor de líneas', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
