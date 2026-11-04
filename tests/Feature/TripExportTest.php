<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\ManageTrips;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Exportaciones (PDF / Excel) del detalle y los pasajeros de un viaje: sólo
 * desde el panel, con sesión y permiso `trips.view`; nunca por una URL
 * pública (spec trip-export).
 */
class TripExportTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private Trip $emptyTrip;

    protected function setUp(): void
    {
        parent::setUp();

        $oran = Location::create(['name' => 'Orán X', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta X', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Coche 9', 'plate' => 'XXX999', 'seat_count' => 2, 'floors' => 1]);
        $seat = Seat::create(['bus_id' => $bus->id, 'seat_number' => '1', 'is_active' => true, 'floor' => '1']);

        $route = Route::create(['name' => 'Orán - Salta X', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana X',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $this->trip = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2030-10-01',
        ]);

        $this->emptyTrip = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2030-10-02',
        ]);

        Ticket::create([
            'sale_id' => Sale::create([
                'user_id' => User::factory()->vendedor()->create()->id,
                'sale_date' => now(),
                'total_amount' => 15000,
            ])->id,
            'trip_id' => $this->trip->id,
            'seat_id' => $seat->id,
            'bus_id' => $bus->id,
            'passenger_id' => Passenger::create([
                'first_name' => 'Ana',
                'last_name' => 'Export',
                'dni' => '30222888',
                'passenger_type' => 'adult',
            ])->id,
            'is_round_trip' => false,
            'is_return_leg' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 15000,
        ]);
    }

    private function userWithoutPermissions(): User
    {
        $user = User::factory()->vendedor()->create();
        $user->syncRoles([]);

        return $user;
    }

    /**
     * La respuesta no entrega ningún archivo de exportación.
     */
    private function assertNoExportFile(TestResponse $response): void
    {
        $contentType = (string) $response->headers->get('Content-Type');
        $disposition = (string) $response->headers->get('Content-Disposition');

        $this->assertStringNotContainsString('application/pdf', $contentType);
        $this->assertStringNotContainsString('spreadsheetml', $contentType);
        $this->assertStringNotContainsString('attachment', $disposition);
        $this->assertStringNotContainsString('Export', (string) $response->getContent());
    }

    // ------------------------------------------------------------------
    // Acceso por URL
    // ------------------------------------------------------------------

    public function test_un_invitado_no_recibe_el_pdf_del_viaje_por_url(): void
    {
        $this->assertNoExportFile($this->get("/trips/{$this->trip->id}/pdf"));
    }

    public function test_un_invitado_no_recibe_el_excel_del_viaje_por_url(): void
    {
        $this->assertNoExportFile($this->get("/trips/{$this->trip->id}/excel"));
    }

    public function test_un_usuario_sin_permiso_de_viajes_no_recibe_las_exportaciones_por_url(): void
    {
        $this->actingAs($this->userWithoutPermissions());

        $this->assertNoExportFile($this->get("/trips/{$this->trip->id}/pdf"));
        $this->assertNoExportFile($this->get("/trips/{$this->trip->id}/excel"));
    }

    public function test_un_invitado_es_enviado_al_login_del_panel(): void
    {
        $response = $this->get("/trips/{$this->trip->id}/pdf");

        $response->assertRedirect();
        $this->assertStringContainsString('/admin/login', (string) $response->headers->get('Location'));
    }

    public function test_un_usuario_sin_permiso_de_viajes_recibe_403(): void
    {
        $this->actingAs($this->userWithoutPermissions());

        $this->get("/trips/{$this->trip->id}/pdf")->assertForbidden();
        $this->get("/trips/{$this->trip->id}/excel")->assertForbidden();
    }

    public function test_un_usuario_con_permiso_de_viajes_recibe_el_pdf_por_url(): void
    {
        $user = $this->userWithoutPermissions();
        $user->givePermissionTo('trips.view');
        $this->actingAs($user);

        $response = $this->get("/trips/{$this->trip->id}/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString("Viaje_N°{$this->trip->id}_Coche_9_01-10-2030.pdf", rawurldecode((string) $response->headers->get('Content-Disposition')));
    }

    public function test_un_usuario_con_permiso_de_viajes_recibe_el_excel_por_url(): void
    {
        Excel::fake();
        $user = $this->userWithoutPermissions();
        $user->givePermissionTo('trips.view');
        $this->actingAs($user);

        $this->get("/trips/{$this->trip->id}/excel");

        Excel::assertDownloaded("Viaje_N°{$this->trip->id}_Coche_9_01-10-2030.xlsx");
    }

    // ------------------------------------------------------------------
    // Acciones del panel
    // ------------------------------------------------------------------

    public function test_la_accion_pdf_del_panel_descarga_el_pdf_del_viaje(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageTrips::class)
            ->callTableAction('download_pdf', $this->trip)
            ->assertFileDownloaded("Viaje_N°{$this->trip->id}_Coche_9_01-10-2030.pdf");
    }

    public function test_la_accion_excel_del_panel_descarga_el_excel_del_viaje(): void
    {
        Excel::fake();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageTrips::class)
            ->callTableAction('download_excel', $this->trip);

        Excel::assertDownloaded("Viaje_N°{$this->trip->id}_Coche_9_01-10-2030.xlsx");
    }

    public function test_las_acciones_estan_deshabilitadas_en_un_viaje_sin_boletos(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageTrips::class)
            ->filterTable('include_empty_trips', ['include_empty' => true])
            ->assertTableActionDisabled('download_pdf', $this->emptyTrip)
            ->assertTableActionDisabled('download_excel', $this->emptyTrip);
    }
}
