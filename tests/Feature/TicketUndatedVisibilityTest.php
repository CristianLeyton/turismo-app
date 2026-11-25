<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\TicketResource;
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
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Boletos sin fecha en el panel del personal: todos los ven, solo quien
 * reprograma les asigna fecha, y el switch para filtrarlos exige "Vender sin
 * fecha/horario" y "Reprogramar" con la función activada.
 */
class TicketUndatedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Ticket $datedTicket;

    private Ticket $undatedTicket;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->forgetSellWithoutDateFlag();
        $this->createTickets();
    }

    // ====================== Boletos sin fecha sin los permisos ======================

    public function test_sin_ninguno_de_los_dos_permisos_ve_los_boletos_sin_fecha_en_el_listado(): void
    {
        $this->actingAs($this->userWith([]));

        Livewire::test(ListTickets::class)
            ->assertCanSeeTableRecords([$this->datedTicket, $this->undatedTicket]);
    }

    public function test_sin_ninguno_de_los_dos_permisos_encuentra_el_boleto_sin_fecha_por_numero(): void
    {
        $this->actingAs($this->userWith([]));

        Livewire::test(ListTickets::class)
            ->filterTable('ticket_id', ['id' => $this->undatedTicket->id])
            ->assertCanSeeTableRecords([$this->undatedTicket])
            ->assertCountTableRecords(1);
    }

    public function test_sin_ninguno_de_los_dos_permisos_abre_el_detalle_del_boleto_sin_fecha(): void
    {
        $this->actingAs($this->userWith([]));

        $this->get(TicketResource::getUrl('view', ['record' => $this->undatedTicket->id]))
            ->assertOk();
    }

    public function test_sin_reprogramar_no_puede_asignarle_fecha_al_boleto_sin_fecha(): void
    {
        $this->actingAs($this->userWith(['tickets.vender_sin_fecha']));

        $this->get(TicketResource::getUrl('reschedule', ['record' => $this->undatedTicket->id]))
            ->assertForbidden();
    }

    // ====================== Switch ======================

    public function test_con_los_dos_permisos_y_la_funcion_activada_ve_el_switch(): void
    {
        $this->activateSellWithoutDate();
        $this->actingAs($this->userWith(['tickets.vender_sin_fecha', 'tickets.reschedule']));

        Livewire::test(ListTickets::class)
            ->assertTableFilterVisible('trip_id')
            ->filterTable('trip_id')
            ->assertCanSeeTableRecords([$this->undatedTicket])
            ->assertCanNotSeeTableRecords([$this->datedTicket]);
    }

    public function test_sin_ninguno_de_los_dos_permisos_no_ve_el_switch(): void
    {
        $this->activateSellWithoutDate();
        $this->actingAs($this->userWith([]));

        Livewire::test(ListTickets::class)->assertTableFilterHidden('trip_id');
    }

    public function test_solo_con_vender_sin_fecha_no_ve_el_switch(): void
    {
        $this->activateSellWithoutDate();
        $this->actingAs($this->userWith(['tickets.vender_sin_fecha']));

        Livewire::test(ListTickets::class)->assertTableFilterHidden('trip_id');
    }

    public function test_solo_con_reprogramar_no_ve_el_switch(): void
    {
        $this->activateSellWithoutDate();
        $this->actingAs($this->userWith(['tickets.reschedule']));

        Livewire::test(ListTickets::class)->assertTableFilterHidden('trip_id');
    }

    public function test_el_super_no_ve_el_switch_con_la_funcion_desactivada(): void
    {
        $this->assertFalse(Permissions::permissionGrantedToAnyRole('tickets.vender_sin_fecha'));

        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(ListTickets::class)->assertTableFilterHidden('trip_id');
    }

    public function test_el_switch_guardado_en_la_sesion_no_se_aplica_si_queda_oculto(): void
    {
        $this->activateSellWithoutDate();
        $user = $this->userWith(['tickets.vender_sin_fecha', 'tickets.reschedule']);
        $this->actingAs($user);

        Livewire::test(ListTickets::class)
            ->filterTable('trip_id')
            ->assertCanNotSeeTableRecords([$this->datedTicket]);

        $user->revokePermissionTo('tickets.reschedule');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user->fresh());

        Livewire::test(ListTickets::class)
            ->assertTableFilterHidden('trip_id')
            ->assertCanSeeTableRecords([$this->datedTicket, $this->undatedTicket]);
    }

    // ====================== Helpers ======================

    /**
     * Vendedor (ve y vende boletos) con permisos extra asignados directamente.
     *
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->vendedor()->create();

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user->fresh();
    }

    /**
     * Activa la venta sin fecha: algún rol que no es SUPER tiene el permiso.
     */
    private function activateSellWithoutDate(): void
    {
        Role::findByName(Permissions::ROLE_ADMIN)->givePermissionTo('tickets.vender_sin_fecha');
        $this->forgetSellWithoutDateFlag();
    }

    private function forgetSellWithoutDateFlag(): void
    {
        Cache::forget('permissions.granted.tickets.vender_sin_fecha');
    }

    /**
     * Un boleto con viaje y asiento, y otro de la misma venta sin fecha.
     */
    private function createTickets(): void
    {
        $oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $salta = Location::create(['name' => 'Salta', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Linea V', 'plate' => 'VVV111', 'seat_count' => 1, 'floors' => 1]);
        $seat = Seat::create([
            'bus_id' => $bus->id,
            'seat_number' => '1',
            'is_active' => true,
            'floor' => '1',
        ]);

        $route = Route::create(['name' => 'Orán - Salta V', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $salta->id, 'stop_order' => 2]);

        $schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana V',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $trip = Trip::create([
            'route_id' => $route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => now()->addDays(3)->toDateString(),
        ]);

        $sale = Sale::create([
            'user_id' => User::factory()->superAdmin()->create()->id,
            'sale_date' => now(),
            'total_amount' => 200,
        ]);

        $ticketData = fn (string $dni): array => [
            'sale_id' => $sale->id,
            'passenger_id' => Passenger::create([
                'first_name' => 'Juana',
                'last_name' => 'Visible',
                'dni' => $dni,
                'passenger_type' => 'adult',
            ])->id,
            'is_round_trip' => false,
            'origin_location_id' => $oran->id,
            'destination_location_id' => $salta->id,
            'price' => 100,
        ];

        $this->datedTicket = Ticket::create($ticketData('30111222') + [
            'trip_id' => $trip->id,
            'seat_id' => $seat->id,
        ]);

        $this->undatedTicket = Ticket::create($ticketData('30333444') + [
            'trip_id' => null,
            'seat_id' => null,
        ]);
    }
}
