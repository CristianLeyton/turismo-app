<?php

namespace App\Console\Commands;

use App\Models\Bus;
use App\Models\BusLayoutArea;
use App\Models\Seat;
use Illuminate\Console\Command;

class SetupLayoutLinea1 extends Command
{
    protected $signature = 'app:setup-layout-linea1';

    protected $description = 'Ajusta el layout de Línea 1 (Orán) a 55 plazas: agrega asientos faltantes, posiciones y pasillos/áreas. No borra ningún asiento.';

    private const LINEA1_NAME = 'Linea 1 (Orán)';

    public function handle(): int
    {
        $bus = Bus::where('name', self::LINEA1_NAME)->first();
        if (!$bus) {
            $this->error('No se encontró el bus "' . self::LINEA1_NAME . '".');
            return self::FAILURE;
        }

        $this->info("Procesando: {$bus->name} (ID: {$bus->id}) — layout 55 plazas");
        $this->newLine();

        if ((int) $bus->seat_count !== 55 || (int) $bus->floors < 2) {
            $bus->update(['seat_count' => 55, 'floors' => 2]);
            $this->line('  - Bus actualizado a 55 asientos, 2 pisos.');
        }

        // 1. Agregar solo asientos faltantes (1-55), sin borrar ninguno
        $existing = Seat::where('bus_id', $bus->id)->pluck('seat_number')->flip();
        $added = 0;
        for ($i = 1; $i <= 55; $i++) {
            if (isset($existing[$i])) {
                continue;
            }
            $floor = $i <= 9 ? '1' : '2';
            Seat::create([
                'bus_id' => $bus->id,
                'seat_number' => $i,
                'is_active' => true,
                'floor' => $floor,
            ]);
            $added++;
        }
        if ($added > 0) {
            $this->info("  ✓ {$added} asiento(s) agregado(s) (total 55).");
        } else {
            $this->line('  - Ya existen 55 asientos.');
        }

        // 2. Aplicar layout 55 plazas (piso 1 y 2)
        $this->applyFloor1Layout55($bus);
        $this->applyFloor2Layout55($bus);

        // 3. Recrear pasillos y áreas (baño, pasillo, cafetería)
        $this->createLayoutAreas55($bus);

        $this->newLine();
        $this->info('Layout Línea 1 (55 plazas) actualizado. No se eliminó ningún asiento.');

        return self::SUCCESS;
    }

    /** Piso 1: B B P E, 1 2 P 3, 4 5 P 6, 7 8 P 9 */
    private function applyFloor1Layout55(Bus $bus): void
    {
        $floor1 = [
            1 => ['row' => 2, 'column' => 0], 2 => ['row' => 2, 'column' => 1], 3 => ['row' => 2, 'column' => 3],
            4 => ['row' => 3, 'column' => 0], 5 => ['row' => 3, 'column' => 1], 6 => ['row' => 3, 'column' => 3],
            7 => ['row' => 4, 'column' => 0], 8 => ['row' => 4, 'column' => 1], 9 => ['row' => 4, 'column' => 3],
        ];
        foreach ($floor1 as $num => $layout) {
            Seat::where('bus_id', $bus->id)->where('seat_number', $num)->update([
                'row' => $layout['row'],
                'column' => $layout['column'],
                'position' => $layout['column'] < 2 ? 'left' : 'right',
                'seat_type' => 'normal',
            ]);
        }
        $this->info('  ✓ Layout piso 1 (asientos 1-9) aplicado.');
    }

    /** Piso 2: 10-55 según layout 55 plazas del seeder */
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
                'row' => $layout[0],
                'column' => $layout[1],
                'position' => $layout[1] < 2 ? 'left' : 'right',
                'seat_type' => 'normal',
            ]);
        }
        $this->info('  ✓ Layout piso 2 (asientos 10-55) aplicado.');
    }

    /** Baño, pasillos y cafetería como en SeatLayoutSeeder (55 plazas). */
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

        $this->info('  ✓ Pasillos y áreas (baño, cafetería) creados.');
    }
}
