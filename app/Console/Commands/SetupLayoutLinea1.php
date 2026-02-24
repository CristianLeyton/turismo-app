<?php

namespace App\Console\Commands;

use App\Models\Bus;
use App\Models\BusLayoutArea;
use App\Models\Seat;
use Illuminate\Console\Command;

class SetupLayoutLinea1 extends Command
{
    protected $signature = 'app:setup-layout-linea1';

    protected $description = 'Ajusta el layout de Línea 1 (Orán): agrega asientos faltantes (hasta 60), posiciones y pasillos/áreas. No borra ningún asiento.';

    private const LINEA1_NAME = 'Linea 1 (Orán)';

    public function handle(): int
    {
        $bus = Bus::where('name', self::LINEA1_NAME)->first();
        if (!$bus) {
            $this->error('No se encontró el bus "' . self::LINEA1_NAME . '".');
            return self::FAILURE;
        }

        $this->info("Procesando: {$bus->name} (ID: {$bus->id})");
        $this->newLine();

        // Asegurar seat_count y floors
        if ((int) $bus->seat_count !== 60 || (int) $bus->floors < 2) {
            $bus->update(['seat_count' => 60, 'floors' => 2]);
            $this->line('  - Bus actualizado a 60 asientos, 2 pisos.');
        }

        // 1. Agregar solo los asientos que falten (1-60), sin borrar ninguno
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
            $this->info("  ✓ {$added} asiento(s) agregado(s) (total 60).");
        } else {
            $this->line('  - Ya existen 60 asientos.');
        }

        // 2. Aplicar layout piso 1 (49-60) y piso 2 (1-48) como en SeatLayoutSeeder
        $this->applyFloor1Layout60($bus);
        $this->applyFloor2Layout60($bus);

        // 3. Recrear pasillos y áreas (layout completo 60 plazas)
        $this->createLayoutAreas60($bus);

        $this->newLine();
        $this->info('Layout Línea 1 actualizado. No se eliminó ningún asiento.');

        return self::SUCCESS;
    }

    /** Piso 1: 49 50 P 51, 52 53 P 54, 55 56 P 57, 58 59 P 60 */
    private function applyFloor1Layout60(Bus $bus): void
    {
        $floor1Layout = [
            49 => ['row' => 1, 'column' => 0], 50 => ['row' => 1, 'column' => 1], 51 => ['row' => 1, 'column' => 3],
            52 => ['row' => 2, 'column' => 0], 53 => ['row' => 2, 'column' => 1], 54 => ['row' => 2, 'column' => 3],
            55 => ['row' => 3, 'column' => 0], 56 => ['row' => 3, 'column' => 1], 57 => ['row' => 3, 'column' => 3],
            58 => ['row' => 4, 'column' => 0], 59 => ['row' => 4, 'column' => 1], 60 => ['row' => 4, 'column' => 3],
        ];
        foreach ($floor1Layout as $seatNumber => $layout) {
            Seat::where('bus_id', $bus->id)->where('seat_number', $seatNumber)->update([
                'row' => $layout['row'],
                'column' => $layout['column'],
                'position' => $layout['column'] < 2 ? 'left' : 'right',
                'seat_type' => 'normal',
            ]);
        }
        $this->info('  ✓ Layout piso 1 (asientos 49-60) aplicado.');
    }

    /** Piso 2: 1-48 según layout del seeder */
    private function applyFloor2Layout60(Bus $bus): void
    {
        $floor2Layout = [
            1 => [1, 0], 2 => [1, 1], 3 => [1, 3], 4 => [1, 4],
            6 => [2, 0], 5 => [2, 1],
            8 => [3, 0], 7 => [3, 1],
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
        foreach ($floor2Layout as $seatNumber => $layout) {
            Seat::where('bus_id', $bus->id)->where('seat_number', $seatNumber)->update([
                'row' => $layout[0],
                'column' => $layout[1],
                'position' => $layout[1] < 2 ? 'left' : 'right',
                'seat_type' => 'normal',
            ]);
        }
        $this->info('  ✓ Layout piso 2 (asientos 1-48) aplicado.');
    }

    /** Pasillos y espacios vacíos como en SeatLayoutSeeder (60 plazas, 2 pisos). */
    private function createLayoutAreas60(Bus $bus): void
    {
        BusLayoutArea::where('bus_id', $bus->id)->forceDelete();

        // Piso 1: pasillo columna 2, filas 1-4
        for ($row = 1; $row <= 4; $row++) {
            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '1',
                'area_type' => 'pasillo',
                'label' => '',
                'row_start' => $row,
                'row_end' => $row,
                'column_start' => 2,
                'column_end' => 2,
                'span_rows' => 1,
                'span_columns' => 1,
            ]);
        }

        // Piso 2: pasillo columna 2, filas 1-13
        for ($row = 1; $row <= 13; $row++) {
            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '2',
                'area_type' => 'pasillo',
                'label' => '',
                'row_start' => $row,
                'row_end' => $row,
                'column_start' => 2,
                'column_end' => 2,
                'span_rows' => 1,
                'span_columns' => 1,
            ]);
        }
        // Espacios E filas 2-3, columnas 3-4
        BusLayoutArea::create([
            'bus_id' => $bus->id,
            'floor' => '2',
            'area_type' => 'vacio',
            'label' => '',
            'row_start' => 2,
            'row_end' => 2,
            'column_start' => 3,
            'column_end' => 4,
            'span_rows' => 1,
            'span_columns' => 2,
        ]);
        BusLayoutArea::create([
            'bus_id' => $bus->id,
            'floor' => '2',
            'area_type' => 'vacio',
            'label' => '',
            'row_start' => 3,
            'row_end' => 3,
            'column_start' => 3,
            'column_end' => 4,
            'span_rows' => 1,
            'span_columns' => 2,
        ]);

        $this->info('  ✓ Pasillos y áreas (piso 1 y 2) creados.');
    }
}
