<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Passenger;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migración de datos del change marca-tramo-vuelta (hallazgo H2).
 */
class FixReturnLegMarksMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Location $oran;

    private Location $salta;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta', 'is_active' => true]);
        $this->seller = User::factory()->create();
    }

    public function test_marca_como_vuelta_los_boletos_dados_de_baja_con_la_convencion(): void
    {
        [$sale, $passenger] = $this->saleWithPassenger();
        $ida = $this->ticket($sale, $passenger, ['price' => 15000, 'return_trip_id' => null, 'is_return_leg' => false]);
        $vuelta = $this->ticket($sale, $passenger, ['price' => 0, 'is_return_leg' => false], inverted: true);
        DB::table('tickets')->whereIn('id', [$ida->id, $vuelta->id])->update(['deleted_at' => now()]);

        $this->runMigration();

        $this->assertTrue((bool) DB::table('tickets')->where('id', $vuelta->id)->value('is_return_leg'));
        $this->assertFalse((bool) DB::table('tickets')->where('id', $ida->id)->value('is_return_leg'));
    }

    public function test_un_grupo_con_dos_vueltas_deja_como_ida_el_de_menor_id(): void
    {
        [$sale, $passenger] = $this->saleWithPassenger();
        $ida = $this->ticket($sale, $passenger, ['price' => 0, 'is_return_leg' => true]);
        $vuelta = $this->ticket($sale, $passenger, ['price' => 0, 'is_return_leg' => true], inverted: true);

        $this->runMigration();

        $this->assertFalse((bool) $ida->fresh()->is_return_leg);
        $this->assertTrue((bool) $vuelta->fresh()->is_return_leg);
    }

    public function test_un_grupo_con_una_ida_existente_no_cambia(): void
    {
        [$sale, $passenger] = $this->saleWithPassenger();
        $ida = $this->ticket($sale, $passenger, ['price' => 0, 'is_return_leg' => false]);
        $vuelta = $this->ticket($sale, $passenger, ['price' => 0, 'is_return_leg' => true], inverted: true);

        $this->runMigration();

        $this->assertFalse((bool) $ida->fresh()->is_return_leg);
        $this->assertTrue((bool) $vuelta->fresh()->is_return_leg);
    }

    public function test_un_grupo_con_tres_boletos_no_cambia(): void
    {
        [$sale, $passenger] = $this->saleWithPassenger();
        $tickets = collect([
            $this->ticket($sale, $passenger, ['price' => 0, 'is_return_leg' => true]),
            $this->ticket($sale, $passenger, ['price' => 0, 'is_return_leg' => true], inverted: true),
            $this->ticket($sale, $passenger, ['price' => 0, 'is_return_leg' => true], inverted: true),
        ]);

        $this->runMigration();

        $tickets->each(fn (Ticket $ticket) => $this->assertTrue((bool) $ticket->fresh()->is_return_leg));
    }

    public function test_es_idempotente(): void
    {
        [$sale, $passenger] = $this->saleWithPassenger();
        $ida = $this->ticket($sale, $passenger, ['price' => 0, 'is_return_leg' => true]);
        $vuelta = $this->ticket($sale, $passenger, ['price' => 0, 'is_return_leg' => true], inverted: true);

        $this->runMigration();
        $this->runMigration();

        $this->assertFalse((bool) $ida->fresh()->is_return_leg);
        $this->assertTrue((bool) $vuelta->fresh()->is_return_leg);
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_11_03_015112_fix_return_leg_marks_on_tickets_table.php');
        $migration->up();
    }

    /**
     * @return array{0: Sale, 1: Passenger}
     */
    private function saleWithPassenger(): array
    {
        $passenger = Passenger::create([
            'first_name' => 'Pedro',
            'last_name' => 'Gomez',
            'dni' => '30000111',
            'phone_number' => '3875550001',
            'passenger_type' => 'adult',
        ]);

        return [Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]), $passenger];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ticket(Sale $sale, Passenger $passenger, array $attributes, bool $inverted = false): Ticket
    {
        return Ticket::create(array_merge([
            'sale_id' => $sale->id,
            'passenger_id' => $passenger->id,
            'is_round_trip' => true,
            'return_trip_id' => null,
            'origin_location_id' => $inverted ? $this->salta->id : $this->oran->id,
            'destination_location_id' => $inverted ? $this->oran->id : $this->salta->id,
        ], $attributes));
    }
}
