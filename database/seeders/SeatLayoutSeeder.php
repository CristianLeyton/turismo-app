<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\Seat;
use App\Models\BusLayoutArea;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SeatLayoutSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Este seeder asigna posiciones (row, column, position) a los asientos existentes
     * y crea áreas especiales como cafetera y baño.
     * 
     * Layout estándar:
     * - 4 columnas por fila (2 izquierda, 2 derecha)
     * - Columnas 0,1 = izquierda | Columnas 2,3 = derecha
     * - Pasillo implícito entre columnas 1 y 2
     * 
     * Áreas especiales:
     * - CAFETERA: primer piso, filas 5-7, columnas 1-2
     * - BAÑO: segundo piso (si existe), filas 1-2, columnas 0-1
     */
    public function run(): void
    {
        $buses = Bus::all();

        if ($buses->isEmpty()) {
            $this->command->warn('No hay buses en la base de datos. Ejecute BusSeeder primero.');
            return;
        }

        foreach ($buses as $bus) {
            $this->command->line("Procesando bus: {$bus->name} (ID: {$bus->id})");
            $this->assignSeatLayouts($bus);
            $this->createSpecialAreas($bus);
            $this->command->line('');
        }

        $this->command->info('✓ Layout de asientos y áreas especiales creados exitosamente.');
        $this->command->info('  Total asientos actualizados: ' . Seat::whereNotNull('row')->count());
        $this->command->info('  Total áreas especiales: ' . BusLayoutArea::count());
    }

    /**
     * Asignar layout (row, column, position) a los asientos de un bus
     * Basado en el layout específico de la imagen proporcionada
     */
    private function assignSeatLayouts(Bus $bus): void
    {
        // Procesar por piso
        $floors = Seat::where('bus_id', $bus->id)
            ->distinct()
            ->pluck('floor')
            ->filter()
            ->map(fn($f) => (string)$f)
            ->sort()
            ->values();

        foreach ($floors as $floor) {
            if ($floor == '1') {
                $this->assignFloor1Layout($bus);
            } elseif ($floor == '2') {
                $this->assignFloor2Layout($bus);
            } else {
                // Para otros pisos, usar layout estándar
                $this->assignStandardLayout($bus, $floor);
            }
        }

        $this->command->info("Layout asignado para bus ID {$bus->id} ({$bus->name})");
    }

    /**
     * Layout específico para primer piso del primer colectivo
     * Según especificación: B B P E, 1 2 P 3, 4 5 P 6, 7 8 P 9
     */
    private function assignFloor1Layout(Bus $bus): void
    {
        // Solo aplicar este layout especial al primer colectivo (bus_id = 1)
        if ($bus->id != 1) {
            // Para otros colectivos, usar layout estándar
            $this->assignStandardLayout($bus, '1');
            return;
        }

        // Layout según especificación textual:
        // Fila 1: B B P E (BAÑO en columnas 0-1, Pasillo en 2, Espacio vacío en 3)
        // Fila 2: 1 2 P 3
        // Fila 3: 4 5 P 6
        // Fila 4: 7 8 P 9

        $floor1Layout = [
            // Fila 2: 1 2 P 3
            1 => ['row' => 2, 'column' => 0],
            2 => ['row' => 2, 'column' => 1],
            3 => ['row' => 2, 'column' => 3],

            // Fila 3: 4 5 P 6
            4 => ['row' => 3, 'column' => 0],
            5 => ['row' => 3, 'column' => 1],
            6 => ['row' => 3, 'column' => 3],

            // Fila 4: 7 8 P 9
            7 => ['row' => 4, 'column' => 0],
            8 => ['row' => 4, 'column' => 1],
            9 => ['row' => 4, 'column' => 3],
        ];

        $seatsOnFloor = Seat::where('bus_id', $bus->id)
            ->where('floor', '1')
            ->where('is_active', true)
            ->get()
            ->keyBy('seat_number');

        foreach ($floor1Layout as $seatNumber => $layout) {
            if (isset($seatsOnFloor[$seatNumber])) {
                $seat = $seatsOnFloor[$seatNumber];
                $position = $layout['column'] < 2 ? 'left' : 'right';

                $seat->update([
                    'row' => $layout['row'],
                    'column' => $layout['column'],
                    'position' => $position,
                    'seat_type' => 'normal',
                ]);
            }
        }

        $this->command->info("  - Piso 1 (Bus {$bus->id}): " . count($floor1Layout) . " asientos organizados según layout específico");
    }

    /**
     * Layout específico para segundo piso del primer colectivo
     * Según especificación: 10 11 P 12 13, 14 15 P E E, etc.
     */
    private function assignFloor2Layout(Bus $bus): void
    {
        // Solo aplicar este layout especial al primer colectivo (bus_id = 1)
        if ($bus->id != 1) {
            // Para otros colectivos, usar layout estándar
            $this->assignStandardLayout($bus, '2');
            return;
        }

        // Layout según especificación textual:
        // 5 columnas (0-4), columna 2 es pasillo (P)
        // P = Pasillo, E = Espacio en blanco, C = Cafetería

        $floor2Layout = [
            // Fila 1: 10 11 P 12 13
            10 => ['row' => 1, 'column' => 0],
            11 => ['row' => 1, 'column' => 1],
            12 => ['row' => 1, 'column' => 3],
            13 => ['row' => 1, 'column' => 4],

            // Fila 2: 14 15 P E E
            14 => ['row' => 2, 'column' => 0],
            15 => ['row' => 2, 'column' => 1],

            // Fila 3: 16 17 P E E
            16 => ['row' => 3, 'column' => 0],
            17 => ['row' => 3, 'column' => 1],

            // Fila 4: 18 19 P C C
            18 => ['row' => 4, 'column' => 0],
            19 => ['row' => 4, 'column' => 1],

            // Fila 5: 20 21 P 24 25
            20 => ['row' => 5, 'column' => 0],
            21 => ['row' => 5, 'column' => 1],
            24 => ['row' => 5, 'column' => 3],
            25 => ['row' => 5, 'column' => 4],

            // Fila 6: 22 23 P 28 29
            22 => ['row' => 6, 'column' => 0],
            23 => ['row' => 6, 'column' => 1],
            28 => ['row' => 6, 'column' => 3],
            29 => ['row' => 6, 'column' => 4],

            // Fila 7: 26 27 P 32 33
            26 => ['row' => 7, 'column' => 0],
            27 => ['row' => 7, 'column' => 1],
            32 => ['row' => 7, 'column' => 3],
            33 => ['row' => 7, 'column' => 4],

            // Fila 8: 30 31 P 36 37
            30 => ['row' => 8, 'column' => 0],
            31 => ['row' => 8, 'column' => 1],
            36 => ['row' => 8, 'column' => 3],
            37 => ['row' => 8, 'column' => 4],

            // Fila 9: 34 35 P 40 41
            34 => ['row' => 9, 'column' => 0],
            35 => ['row' => 9, 'column' => 1],
            40 => ['row' => 9, 'column' => 3],
            41 => ['row' => 9, 'column' => 4],

            // Fila 10: 38 39 P 44 45
            38 => ['row' => 10, 'column' => 0],
            39 => ['row' => 10, 'column' => 1],
            44 => ['row' => 10, 'column' => 3],
            45 => ['row' => 10, 'column' => 4],

            // Fila 11: 42 43 P 48 49
            42 => ['row' => 11, 'column' => 0],
            43 => ['row' => 11, 'column' => 1],
            48 => ['row' => 11, 'column' => 3],
            49 => ['row' => 11, 'column' => 4],

            // Fila 12: 46 47 P 52 53
            46 => ['row' => 12, 'column' => 0],
            47 => ['row' => 12, 'column' => 1],
            52 => ['row' => 12, 'column' => 3],
            53 => ['row' => 12, 'column' => 4],

            // Fila 13: 50 51 P 54 55
            50 => ['row' => 13, 'column' => 0],
            51 => ['row' => 13, 'column' => 1],
            54 => ['row' => 13, 'column' => 3],
            55 => ['row' => 13, 'column' => 4],
        ];

        $seatsOnFloor = Seat::where('bus_id', $bus->id)
            ->where('floor', '2')
            ->where('is_active', true)
            ->get()
            ->keyBy('seat_number');

        foreach ($floor2Layout as $seatNumber => $layout) {
            if (isset($seatsOnFloor[$seatNumber])) {
                $seat = $seatsOnFloor[$seatNumber];
                $position = $layout['column'] < 2 ? 'left' : 'right';

                $seat->update([
                    'row' => $layout['row'],
                    'column' => $layout['column'],
                    'position' => $position,
                    'seat_type' => 'normal',
                ]);
            }
        }

        $this->command->info("  - Piso 2 (Bus {$bus->id}): " . count($floor2Layout) . " asientos organizados según layout específico");
    }

    /**
     * Layout estándar para otros pisos
     */
    private function assignStandardLayout(Bus $bus, string $floor): void
    {
        $seatsOnFloor = Seat::where('bus_id', $bus->id)
            ->where('floor', $floor)
            ->where('is_active', true)
            ->orderBy('seat_number')
            ->get();

        $seatsPerRow = 4;
        $currentRow = 1;
        $seatIndex = 0;

        foreach ($seatsOnFloor as $seat) {
            $column = $seatIndex % $seatsPerRow;

            if ($column === 0 && $seatIndex > 0) {
                $currentRow++;
            }

            $position = $column < 2 ? 'left' : 'right';

            $seat->update([
                'row' => $currentRow,
                'column' => $column,
                'position' => $position,
                'seat_type' => 'normal',
            ]);

            $seatIndex++;
        }

        $this->command->info("  - Piso {$floor}: {$seatsOnFloor->count()} asientos organizados en layout estándar");
    }

    /**
     * Crear áreas especiales (cafetera, baño, pasillos, etc.) para un bus
     * Según especificación exacta del usuario
     */
    private function createSpecialAreas(Bus $bus): void
    {
        // Eliminar áreas existentes para este bus (en caso de re-ejecutar)
        BusLayoutArea::where('bus_id', $bus->id)->delete();

        $floors = (int) $bus->floors;

        // Layout especial solo para el primer colectivo (bus_id = 1)
        if ($bus->id == 1 && $floors >= 1) {
            // PRIMER PISO: B B P E
            // BAÑO en fila 1, columnas 0-1
            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '1',
                'area_type' => 'bathroom',
                'label' => 'BAÑO',
                'row_start' => 1,
                'row_end' => 1,
                'column_start' => 0,
                'column_end' => 1,
                'span_rows' => 1,
                'span_columns' => 2,
            ]);

            // Pasillo en columna 2 para todas las filas (1-4)
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

            // Espacio vacío en fila 1, columna 3 (E)
            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '1',
                'area_type' => 'vacio',
                'label' => '',
                'row_start' => 1,
                'row_end' => 1,
                'column_start' => 3,
                'column_end' => 3,
                'span_rows' => 1,
                'span_columns' => 1,
            ]);

            $this->command->info("  - BAÑO, pasillo y espacio vacío creados en piso 1 del primer colectivo");
        } elseif ($floors >= 1) {
            // Para otros colectivos, mantener el layout anterior con CAFETERA
            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '1',
                'area_type' => 'cafeteria',
                'label' => 'CAFETERA',
                'row_start' => 5,
                'row_end' => 11,
                'column_start' => 1,
                'column_end' => 2,
                'span_rows' => 7,
                'span_columns' => 2,
            ]);

            $this->command->info("  - CAFETERA creada en piso 1 (filas 5-11, columnas 1-2)");
        }

        // SEGUNDO PISO del primer colectivo
        if ($floors >= 2 && $bus->id == 1) {
            // Pasillo en columna 2 para todas las filas (1-13)
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

            // Espacios vacíos (E) en filas 2-3, columnas 3-4
            // Fila 2: 14 15 P E E
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

            // Fila 3: 16 17 P E E
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

            // Cafetería (C) en fila 4, columnas 3-4
            // Fila 4: 18 19 P C C
            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '2',
                'area_type' => 'cafeteria',
                'label' => 'CAFE',
                'row_start' => 4,
                'row_end' => 4,
                'column_start' => 3,
                'column_end' => 4,
                'span_rows' => 1,
                'span_columns' => 2,
            ]);

            $this->command->info("  - Pasillo central, espacios vacíos y cafetería creados en piso 2 del primer colectivo");
        } elseif ($floors >= 2 && $bus->id != 1) {
            // BAÑO en el segundo piso para otros colectivos
            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '2',
                'area_type' => 'bathroom',
                'label' => 'BAÑO',
                'row_start' => 0,
                'row_end' => 0,
                'column_start' => 0,
                'column_end' => 1,
                'span_rows' => 1,
                'span_columns' => 2,
            ]);

            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '2',
                'area_type' => 'bathroom',
                'label' => 'BAÑO',
                'row_start' => 0,
                'row_end' => 0,
                'column_start' => 2,
                'column_end' => 3,
                'span_rows' => 1,
                'span_columns' => 2,
            ]);

            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '2',
                'area_type' => 'bathroom',
                'label' => 'BAÑO',
                'row_start' => 1,
                'row_end' => 1,
                'column_start' => 0,
                'column_end' => 1,
                'span_rows' => 1,
                'span_columns' => 2,
            ]);

            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '2',
                'area_type' => 'bathroom',
                'label' => 'BAÑO',
                'row_start' => 1,
                'row_end' => 1,
                'column_start' => 2,
                'column_end' => 3,
                'span_rows' => 1,
                'span_columns' => 2,
            ]);

            $this->command->info("  - 4 bloques de BAÑO creados en piso 2");
        }
    }
}
