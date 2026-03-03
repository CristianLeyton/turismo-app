<?php

namespace App\Console\Commands;

use App\Models\Bus;
use App\Models\BusLayoutArea;
use App\Models\Seat;
use Illuminate\Console\Command;

/**
 * Intercambia los layouts entre Línea 1 y Línea 2:
 * - Línea 1 (Orán): pasa a layout 60 plazas (agrega asientos 56-60 si faltan)
 * - Línea 2 (Embarcación): pasa a layout 55 plazas (desactiva asientos 56-60, no los borra)
 *
 * No borra asientos ni modifica seat_id. Los boletos vendidos siguen funcionando.
 */
class SwapLayoutsLinea1Linea2 extends Command
{
    protected $signature = 'app:swap-layouts-linea1-linea2';

    protected $description = 'Intercambia layouts: Línea 1→60 plazas, Línea 2→55 plazas. Desactiva 56-60 en Línea 2. No afecta boletos vendidos.';

    private const LINEA1_NAME = 'Linea 1 (Orán)';
    private const LINEA2_NAME = 'Linea 2 (Embarcacion)';

    public function handle(): int
    {
        $this->info('Intercambiando layouts Línea 1 ↔ Línea 2');
        $this->newLine();

        $linea1 = Bus::where('name', self::LINEA1_NAME)->first();
        $linea2 = Bus::where('name', self::LINEA2_NAME)->first();

        if (!$linea1 || !$linea2) {
            $this->error('No se encontraron ambos buses. Verificá que existan "' . self::LINEA1_NAME . '" y "' . self::LINEA2_NAME . '".');
            return self::FAILURE;
        }

        // 1. Línea 1 → layout 60 plazas
        $this->applyLayout60ToLinea1($linea1);

        $this->newLine();

        // 2. Línea 2 → layout 55 plazas (desactivar 56-60)
        $this->applyLayout55ToLinea2($linea2);

        $this->newLine();
        $this->info('Layouts intercambiados. No se eliminó ningún asiento.');

        return self::SUCCESS;
    }

    private function applyLayout60ToLinea1(Bus $bus): void
    {
        $this->info("Línea 1 ({$bus->name}) → layout 60 plazas");

        $bus->update(['seat_count' => 60, 'floors' => 2]);

        $existing = Seat::where('bus_id', $bus->id)->pluck('seat_number')->flip();
        $added = 0;
        for ($i = 1; $i <= 60; $i++) {
            if (isset($existing[$i])) {
                continue;
            }
            $floor = $i <= 48 ? '2' : '1';
            Seat::create([
                'bus_id' => $bus->id,
                'seat_number' => $i,
                'is_active' => true,
                'floor' => $floor,
            ]);
            $added++;
        }
        if ($added > 0) {
            $this->line("  ✓ {$added} asiento(s) agregado(s).");
        }

        $this->applyFloor1Layout60($bus);
        $this->applyFloor2Layout60($bus);
        $this->createLayoutAreas60($bus);
    }

    private function applyLayout55ToLinea2(Bus $bus): void
    {
        $this->info("Línea 2 ({$bus->name}) → layout 55 plazas (desactivar 56-60)");

        $bus->update(['seat_count' => 55, 'floors' => 2]);

        Seat::where('bus_id', $bus->id)->whereBetween('seat_number', [56, 60])->update(['is_active' => false]);
        Seat::where('bus_id', $bus->id)->whereBetween('seat_number', [1, 9])->update(['is_active' => true, 'floor' => '1']);
        Seat::where('bus_id', $bus->id)->whereBetween('seat_number', [10, 55])->update(['is_active' => true, 'floor' => '2']);
        $this->line('  ✓ Asientos 56-60 desactivados.');

        $this->applyFloor1Layout55($bus);
        $this->applyFloor2Layout55($bus);
        $this->createLayoutAreas55($bus);
    }

    private function applyFloor1Layout60(Bus $bus): void
    {
        $floor1 = [
            49 => ['row' => 1, 'column' => 0], 50 => ['row' => 1, 'column' => 1], 51 => ['row' => 1, 'column' => 3],
            52 => ['row' => 2, 'column' => 0], 53 => ['row' => 2, 'column' => 1], 54 => ['row' => 2, 'column' => 3],
            55 => ['row' => 3, 'column' => 0], 56 => ['row' => 3, 'column' => 1], 57 => ['row' => 3, 'column' => 3],
            58 => ['row' => 4, 'column' => 0], 59 => ['row' => 4, 'column' => 1], 60 => ['row' => 4, 'column' => 3],
        ];
        foreach ($floor1 as $num => $layout) {
            Seat::where('bus_id', $bus->id)->where('seat_number', $num)->update([
                'row' => $layout['row'], 'column' => $layout['column'],
                'position' => $layout['column'] < 2 ? 'left' : 'right', 'seat_type' => 'normal',
            ]);
        }
        $this->line('  ✓ Layout piso 1 (49-60) aplicado.');
    }

    private function applyFloor2Layout60(Bus $bus): void
    {
        $floor2 = [
            1 => [1, 0], 2 => [1, 1], 3 => [1, 3], 4 => [1, 4],
            6 => [2, 0], 5 => [2, 1], 8 => [3, 0], 7 => [3, 1],
            10 => [4, 0], 9 => [4, 1], 12 => [4, 3], 11 => [4, 4],
            13 => [5, 0], 14 => [5, 1], 16 => [5, 3], 15 => [5, 4],
            17 => [6, 0], 18 => [6, 1], 20 => [6, 3], 19 => [6, 4],
            21 => [7, 0], 22 => [7, 1], 24 => [7, 3], 23 => [7, 4],
            25 => [8, 0], 26 => [8, 1], 28 => [8, 3], 27 => [8, 4],
            29 => [9, 0], 30 => [9, 1], 32 => [9, 3], 31 => [9, 4],
            33 => [10, 0], 34 => [10, 1], 36 => [10, 3], 35 => [10, 4],
            37 => [11, 0], 38 => [11, 1], 40 => [11, 3], 39 => [11, 4],
            41 => [12, 0], 42 => [12, 1], 44 => [12, 3], 43 => [12, 4],
            45 => [13, 0], 46 => [13, 1], 47 => [13, 3], 48 => [13, 4],
        ];
        foreach ($floor2 as $num => $layout) {
            Seat::where('bus_id', $bus->id)->where('seat_number', $num)->update([
                'row' => $layout[0], 'column' => $layout[1],
                'position' => $layout[1] < 2 ? 'left' : 'right', 'seat_type' => 'normal',
            ]);
        }
        $this->line('  ✓ Layout piso 2 (1-48) aplicado.');
    }

    private function applyFloor1Layout55(Bus $bus): void
    {
        $floor1 = [
            1 => ['row' => 2, 'column' => 0], 2 => ['row' => 2, 'column' => 1], 3 => ['row' => 2, 'column' => 3],
            4 => ['row' => 3, 'column' => 0], 5 => ['row' => 3, 'column' => 1], 6 => ['row' => 3, 'column' => 3],
            7 => ['row' => 4, 'column' => 0], 8 => ['row' => 4, 'column' => 1], 9 => ['row' => 4, 'column' => 3],
        ];
        foreach ($floor1 as $num => $layout) {
            Seat::where('bus_id', $bus->id)->where('seat_number', $num)->update([
                'row' => $layout['row'], 'column' => $layout['column'],
                'position' => $layout['column'] < 2 ? 'left' : 'right', 'seat_type' => 'normal',
            ]);
        }
        $this->line('  ✓ Layout piso 1 (1-9) aplicado.');
    }

    private function applyFloor2Layout55(Bus $bus): void
    {
        $floor2 = [
            10 => [1, 0], 11 => [1, 1], 12 => [1, 3], 13 => [1, 4],
            14 => [2, 0], 15 => [2, 1], 16 => [3, 0], 17 => [3, 1],
            18 => [4, 0], 19 => [4, 1], 20 => [5, 0], 21 => [5, 1], 24 => [5, 3], 25 => [5, 4],
            22 => [6, 0], 23 => [6, 1], 28 => [6, 3], 29 => [6, 4],
            26 => [7, 0], 27 => [7, 1], 32 => [7, 3], 33 => [7, 4],
            30 => [8, 0], 31 => [8, 1], 36 => [8, 3], 37 => [8, 4],
            34 => [9, 0], 35 => [9, 1], 40 => [9, 3], 41 => [9, 4],
            38 => [10, 0], 39 => [10, 1], 44 => [10, 3], 45 => [10, 4],
            42 => [11, 0], 43 => [11, 1], 48 => [11, 3], 49 => [11, 4],
            46 => [12, 0], 47 => [12, 1], 52 => [12, 3], 53 => [12, 4],
            50 => [13, 0], 51 => [13, 1], 54 => [13, 3], 55 => [13, 4],
        ];
        foreach ($floor2 as $num => $layout) {
            Seat::where('bus_id', $bus->id)->where('seat_number', $num)->update([
                'row' => $layout[0], 'column' => $layout[1],
                'position' => $layout[1] < 2 ? 'left' : 'right', 'seat_type' => 'normal',
            ]);
        }
        $this->line('  ✓ Layout piso 2 (10-55) aplicado.');
    }

    private function createLayoutAreas60(Bus $bus): void
    {
        BusLayoutArea::where('bus_id', $bus->id)->forceDelete();
        for ($row = 1; $row <= 4; $row++) {
            BusLayoutArea::create([
                'bus_id' => $bus->id, 'floor' => '1', 'area_type' => 'pasillo', 'label' => '',
                'row_start' => $row, 'row_end' => $row, 'column_start' => 2, 'column_end' => 2, 'span_rows' => 1, 'span_columns' => 1,
            ]);
        }
        for ($row = 1; $row <= 13; $row++) {
            BusLayoutArea::create([
                'bus_id' => $bus->id, 'floor' => '2', 'area_type' => 'pasillo', 'label' => '',
                'row_start' => $row, 'row_end' => $row, 'column_start' => 2, 'column_end' => 2, 'span_rows' => 1, 'span_columns' => 1,
            ]);
        }
        BusLayoutArea::create([
            'bus_id' => $bus->id, 'floor' => '2', 'area_type' => 'vacio', 'label' => '',
            'row_start' => 2, 'row_end' => 2, 'column_start' => 3, 'column_end' => 4, 'span_rows' => 1, 'span_columns' => 2,
        ]);
        BusLayoutArea::create([
            'bus_id' => $bus->id, 'floor' => '2', 'area_type' => 'vacio', 'label' => '',
            'row_start' => 3, 'row_end' => 3, 'column_start' => 3, 'column_end' => 4, 'span_rows' => 1, 'span_columns' => 2,
        ]);
        $this->line('  ✓ Pasillos y áreas (60 plazas) creados.');
    }

    private function createLayoutAreas55(Bus $bus): void
    {
        BusLayoutArea::where('bus_id', $bus->id)->forceDelete();
        BusLayoutArea::create([
            'bus_id' => $bus->id, 'floor' => '1', 'area_type' => 'bathroom', 'label' => 'BAÑO',
            'row_start' => 1, 'row_end' => 1, 'column_start' => 0, 'column_end' => 1, 'span_rows' => 1, 'span_columns' => 2,
        ]);
        for ($row = 1; $row <= 4; $row++) {
            BusLayoutArea::create([
                'bus_id' => $bus->id, 'floor' => '1', 'area_type' => 'pasillo', 'label' => '',
                'row_start' => $row, 'row_end' => $row, 'column_start' => 2, 'column_end' => 2, 'span_rows' => 1, 'span_columns' => 1,
            ]);
        }
        BusLayoutArea::create([
            'bus_id' => $bus->id, 'floor' => '1', 'area_type' => 'vacio', 'label' => '',
            'row_start' => 1, 'row_end' => 1, 'column_start' => 3, 'column_end' => 3, 'span_rows' => 1, 'span_columns' => 1,
        ]);
        for ($row = 1; $row <= 13; $row++) {
            BusLayoutArea::create([
                'bus_id' => $bus->id, 'floor' => '2', 'area_type' => 'pasillo', 'label' => '',
                'row_start' => $row, 'row_end' => $row, 'column_start' => 2, 'column_end' => 2, 'span_rows' => 1, 'span_columns' => 1,
            ]);
        }
        BusLayoutArea::create([
            'bus_id' => $bus->id, 'floor' => '2', 'area_type' => 'vacio', 'label' => '',
            'row_start' => 2, 'row_end' => 2, 'column_start' => 3, 'column_end' => 4, 'span_rows' => 1, 'span_columns' => 2,
        ]);
        BusLayoutArea::create([
            'bus_id' => $bus->id, 'floor' => '2', 'area_type' => 'vacio', 'label' => '',
            'row_start' => 3, 'row_end' => 3, 'column_start' => 3, 'column_end' => 4, 'span_rows' => 1, 'span_columns' => 2,
        ]);
        BusLayoutArea::create([
            'bus_id' => $bus->id, 'floor' => '2', 'area_type' => 'cafeteria', 'label' => 'CAFE',
            'row_start' => 4, 'row_end' => 4, 'column_start' => 3, 'column_end' => 4, 'span_rows' => 1, 'span_columns' => 2,
        ]);
        $this->line('  ✓ Pasillos y áreas (55 plazas) creados.');
    }
}
