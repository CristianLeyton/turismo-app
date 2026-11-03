<?php

namespace Tests\Unit;

use App\Models\Bus;
use App\Models\Linea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusLineaTest extends TestCase
{
    use RefreshDatabase;

    public function test_bus_without_line_returns_only_its_own_id(): void
    {
        $bus = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 4, 'floors' => 1]);

        $this->assertSame([$bus->id], $bus->busIdsInSameLine());
    }

    public function test_bus_in_line_returns_ids_of_every_bus_in_that_line(): void
    {
        $linea = Linea::create(['name' => 'Oran - Salta']);
        $regular = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 4, 'floors' => 1, 'linea_id' => $linea->id]);
        $refuerzo = Bus::create(['name' => 'Oran - Salta (Refuerzo)', 'plate' => 'BBB222', 'seat_count' => 4, 'floors' => 1, 'linea_id' => $linea->id, 'is_reinforcement' => true]);

        $this->assertEqualsCanonicalizing([$regular->id, $refuerzo->id], $regular->busIdsInSameLine());
        $this->assertEqualsCanonicalizing([$regular->id, $refuerzo->id], $refuerzo->busIdsInSameLine());
    }

    public function test_line_does_not_include_buses_of_other_lines_or_without_line(): void
    {
        $linea = Linea::create(['name' => 'Oran - Salta']);
        $otraLinea = Linea::create(['name' => 'Tartagal - Salta']);
        $regular = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 4, 'floors' => 1, 'linea_id' => $linea->id]);
        Bus::create(['name' => 'Tartagal - Salta', 'plate' => 'CCC333', 'seat_count' => 4, 'floors' => 1, 'linea_id' => $otraLinea->id]);
        Bus::create(['name' => 'Suelto', 'plate' => 'DDD444', 'seat_count' => 4, 'floors' => 1]);

        $this->assertSame([$regular->id], $regular->busIdsInSameLine());
    }

    public function test_soft_deleted_bus_is_excluded_from_its_line(): void
    {
        $linea = Linea::create(['name' => 'Oran - Salta']);
        $regular = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 4, 'floors' => 1, 'linea_id' => $linea->id]);
        $refuerzo = Bus::create(['name' => 'Oran - Salta (Refuerzo)', 'plate' => 'BBB222', 'seat_count' => 4, 'floors' => 1, 'linea_id' => $linea->id]);
        $refuerzo->delete();

        $this->assertSame([$regular->id], $regular->busIdsInSameLine());
    }

    public function test_deleting_a_line_leaves_its_buses_without_line(): void
    {
        $linea = Linea::create(['name' => 'Oran - Salta']);
        $bus = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 4, 'floors' => 1, 'linea_id' => $linea->id]);

        $linea->delete();

        $this->assertNull($bus->fresh()->linea_id);
        $this->assertSame([$bus->id], $bus->fresh()->busIdsInSameLine());
    }

    public function test_reinforcement_flag_defaults_to_false(): void
    {
        $bus = Bus::create(['name' => 'Oran - Salta', 'plate' => 'AAA111', 'seat_count' => 4, 'floors' => 1]);

        $this->assertFalse($bus->fresh()->is_reinforcement);
    }
}
