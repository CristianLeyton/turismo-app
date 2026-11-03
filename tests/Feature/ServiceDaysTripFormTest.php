<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\ManageTrips;
use App\Filament\Resources\Trips\TripResource;
use App\Models\User;
use App\Support\ServiceDays;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Días de servicio en el formulario de crear viaje (TripResource).
 *
 * Hoy el formulario no es accesible desde la UI (CreateAction comentado en
 * ManageTrips), así que se prueba el componente directamente.
 */
class ServiceDaysTripFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_permiso_deshabilita_y_rechaza_el_dia_sin_servicio(): void
    {
        $this->actingAs(User::factory()->vendedor()->create());

        $field = $this->tripDateField();

        $this->assertContains('2026-10-04', $field->getDisabledDates());
        $this->assertTrue($this->validate($field, '2026-10-04')->fails());
        $this->assertFalse($this->validate($field, '2026-10-05')->fails());
    }

    public function test_con_permiso_permite_el_dia_sin_servicio(): void
    {
        $seller = User::factory()->vendedor()->create();
        $seller->givePermissionTo(ServiceDays::PERMISSION);
        $this->actingAs($seller);

        $field = $this->tripDateField();

        $this->assertSame([], $field->getDisabledDates());
        $this->assertFalse($this->validate($field, '2026-10-04')->fails());
    }

    public function test_el_filtro_por_fecha_del_listado_no_aplica_la_regla(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $bus = \App\Models\Bus::create(['name' => 'Linea F', 'plate' => 'FFF111', 'seat_count' => 4, 'floors' => 1]);
        $route = \App\Models\Route::create(['name' => 'Ruta F', 'bus_id' => $bus->id, 'is_active' => true]);
        $schedule = \App\Models\Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana F',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        // Domingo 04/10/2026: sin servicio con la configuración inicial.
        $sundayTrip = \App\Models\Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-04',
        ]);
        $mondayTrip = \App\Models\Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2026-10-05',
        ]);

        Livewire::test(ManageTrips::class)
            ->filterTable('trip_date', ['date' => '2026-10-04'])
            ->assertCanSeeTableRecords([$sundayTrip])
            ->assertCanNotSeeTableRecords([$mondayTrip]);
    }

    private function tripDateField(): DatePicker
    {
        $livewire = Livewire::test(ManageTrips::class)->instance();
        $schema = TripResource::form(Schema::make($livewire)->operation('create'));

        return collect($schema->getComponents())
            ->first(fn ($component) => $component instanceof DatePicker && $component->getName() === 'trip_date');
    }

    private function validate(DatePicker $field, string $date): \Illuminate\Validation\Validator
    {
        $customRules = array_filter(
            $field->getValidationRules(),
            fn ($rule) => $rule instanceof \Closure,
        );

        return Validator::make(['trip_date' => $date], ['trip_date' => array_values($customRules)]);
    }
}
