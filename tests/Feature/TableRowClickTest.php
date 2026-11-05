<?php

namespace Tests\Feature;

use App\Filament\Clusters\Locations\Resources\Locations\Pages\ManageLocations;
use App\Filament\Clusters\Locations\Resources\Routes\Pages\ManageRoutes;
use App\Filament\Clusters\Locations\Resources\RouteStops\Pages\ManageRouteStops;
use App\Filament\Clusters\Locations\Resources\Schedules\Pages\ManageSchedules;
use App\Filament\Resources\Buses\Pages\ManageBuses;
use App\Filament\Resources\Buses\Pages\ManageBusLayoutAreas;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Filament\Resources\Lineas\Pages\ManageLineas;
use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Filament\Resources\Seats\Pages\ManageSeats;
use App\Filament\Resources\TicketAudit\Pages\ListTicketAudit;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Trips\Pages\ManageTrips;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Convención del panel: tocar una fila no abre nada (evita ediciones
 * accidentales al scrollear en el celular); las acciones se usan sólo
 * desde sus botones o el menú de tres puntitos.
 */
class TableRowClickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    /** @return array<string, array{class-string}> */
    public static function listPages(): array
    {
        return [
            'Líneas' => [ManageLineas::class],
            'Colectivos' => [ManageBuses::class],
            'Distribuciones de asientos' => [ManageBusLayoutAreas::class],
            'Asientos' => [ManageSeats::class],
            'Viajes' => [ManageTrips::class],
            'Rutas' => [ManageRoutes::class],
            'Paradas' => [ManageRouteStops::class],
            'Horarios' => [ManageSchedules::class],
            'Ubicaciones' => [ManageLocations::class],
            'Boletos' => [ListTickets::class],
            'Historial de boletos' => [ListTicketAudit::class],
            'Ventas' => [ManageSales::class],
            'Pagos' => [ManagePayments::class],
            'Métodos de pago' => [ManagePaymentMethods::class],
            'Clientes' => [ManageClients::class],
            'Usuarios' => [ManageUsers::class],
            'Roles' => [ManageRoles::class],
        ];
    }

    /** @param  class-string  $page */
    #[DataProvider('listPages')]
    public function test_tocar_una_fila_no_abre_nada(string $page): void
    {
        $table = Livewire::test($page)->instance()->getTable();
        $modelClass = $table->getModel();
        $record = new $modelClass;

        $this->assertNull($table->getRecordUrl($record), "{$page}: la fila no debe tener URL.");
        $this->assertNull($table->getRecordAction($record), "{$page}: la fila no debe disparar una acción.");
    }
}
