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
     * Basado exactamente en la imagen: asientos 49-60 con BAÑO y pasillos blancos
     */
    private function assignFloor1Layout(Bus $bus): void
    {
        // Solo aplicar este layout especial al primer colectivo (bus_id = 1)
        if ($bus->id != 1) {
            // Para otros colectivos, usar layout estándar
            $this->assignStandardLayout($bus, '1');
            return;
        }

        // Layout según la imagen:
        // Fila 1: BAÑO (columnas 0-1), pasillo (col 2), pasillo (col 3)
        // Fila 2: Asiento 49 (col 0), Asiento 50 (col 1), pasillo (col 2), Asiento 51 (col 3)
        // Fila 3: Asiento 52 (col 0), Asiento 53 (col 1), pasillo (col 2), Asiento 54 (col 3)
        // Fila 4: Asiento 55 (col 0), Asiento 56 (col 1), pasillo (col 2), Asiento 57 (col 3)
        // Fila 5: Asiento 58 (col 0), Asiento 59 (col 1), pasillo (col 2), Asiento 60 (col 3)

        $floor1Layout = [
            // Fila 2 (row 2 en el grid, pero row 1 es BAÑO)
            49 => ['row' => 2, 'column' => 0],
            50 => ['row' => 2, 'column' => 1],
            51 => ['row' => 2, 'column' => 3],

            // Fila 3
            52 => ['row' => 3, 'column' => 0],
            53 => ['row' => 3, 'column' => 1],
            54 => ['row' => 3, 'column' => 3],

            // Fila 4
            55 => ['row' => 4, 'column' => 0],
            56 => ['row' => 4, 'column' => 1],
            57 => ['row' => 4, 'column' => 3],

            // Fila 5
            58 => ['row' => 5, 'column' => 0],
            59 => ['row' => 5, 'column' => 1],
            60 => ['row' => 5, 'column' => 3],
        ];

        // Obtener asientos del primer piso (si los asientos 49-60 están en piso 1)
        // Si están en piso 2, necesitaremos ajustar el SeatSeeder primero
        $seatsOnFloor = Seat::where('bus_id', $bus->id)
            ->where('floor', '1')
            ->where('is_active', true)
            ->get()
            ->keyBy('seat_number');

        // Si los asientos 49-60 no están en el piso 1, buscarlos en el piso 2
        if (!$seatsOnFloor->has(49)) {
            $seatsOnFloor = Seat::where('bus_id', $bus->id)
                ->where('floor', '2')
                ->where('is_active', true)
                ->get()
                ->keyBy('seat_number');
        }

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

        $this->command->info("  - Piso 1 (Bus {$bus->id}): " . count($floor1Layout) . " asientos organizados según layout específico con pasillos");
    }

    /**
     * Layout específico para segundo piso del primer colectivo
     * Basado exactamente en la imagen: 48 asientos (1-48) con pasillo central y CAFETERA
     */
    private function assignFloor2Layout(Bus $bus): void
    {
        // Solo aplicar este layout especial al primer colectivo (bus_id = 1)
        if ($bus->id != 1) {
            // Para otros colectivos, usar layout estándar
            $this->assignStandardLayout($bus, '2');
            return;
        }

        // Layout según la imagen:
        // 13 filas, 5 columnas (columns 0-4)
        // Columna 2 siempre es pasillo blanco
        // CAFETERA en filas 2-3, columnas 3-4 (bloque 2x2)
        // Asientos 1-48 distribuidos en las filas

        $floor2Layout = [
            // Fila 1 (row 1)
            1 => ['row' => 1, 'column' => 0],
            2 => ['row' => 1, 'column' => 1],
            3 => ['row' => 1, 'column' => 3],
            4 => ['row' => 1, 'column' => 4],

            // Fila 2 (row 2) - CAFETERA ocupa columnas 3-4, solo asientos 5-6 en columnas 0-1
            5 => ['row' => 2, 'column' => 0],
            6 => ['row' => 2, 'column' => 1],

            // Fila 3 (row 3) - CAFETERA ocupa columnas 3-4, solo asientos 7-8 en columnas 0-1
            7 => ['row' => 3, 'column' => 0],
            8 => ['row' => 3, 'column' => 1],

            // Fila 4 (row 4) - Continúa después de CAFETERA
            9 => ['row' => 4, 'column' => 0],
            10 => ['row' => 4, 'column' => 1],
            11 => ['row' => 4, 'column' => 3],
            12 => ['row' => 4, 'column' => 4],

            // Fila 5 (row 5)
            13 => ['row' => 5, 'column' => 0],
            14 => ['row' => 5, 'column' => 1],
            15 => ['row' => 5, 'column' => 3],
            16 => ['row' => 5, 'column' => 4],

            // Fila 6 (row 6)
            17 => ['row' => 6, 'column' => 0],
            18 => ['row' => 6, 'column' => 1],
            19 => ['row' => 6, 'column' => 3],
            20 => ['row' => 6, 'column' => 4],

            // Fila 7 (row 7)
            21 => ['row' => 7, 'column' => 0],
            22 => ['row' => 7, 'column' => 1],
            23 => ['row' => 7, 'column' => 3],
            24 => ['row' => 7, 'column' => 4],

            // Fila 8 (row 8)
            25 => ['row' => 8, 'column' => 0],
            26 => ['row' => 8, 'column' => 1],
            27 => ['row' => 8, 'column' => 3],
            28 => ['row' => 8, 'column' => 4],

            // Fila 9 (row 9)
            29 => ['row' => 9, 'column' => 0],
            30 => ['row' => 9, 'column' => 1],
            31 => ['row' => 9, 'column' => 3],
            32 => ['row' => 9, 'column' => 4],

            // Fila 10 (row 10)
            33 => ['row' => 10, 'column' => 0],
            34 => ['row' => 10, 'column' => 1],
            35 => ['row' => 10, 'column' => 3],
            36 => ['row' => 10, 'column' => 4],

            // Fila 11 (row 11)
            37 => ['row' => 11, 'column' => 0],
            38 => ['row' => 11, 'column' => 1],
            39 => ['row' => 11, 'column' => 3],
            40 => ['row' => 11, 'column' => 4],

            // Fila 12 (row 12)
            41 => ['row' => 12, 'column' => 0],
            42 => ['row' => 12, 'column' => 1],
            43 => ['row' => 12, 'column' => 3],
            44 => ['row' => 12, 'column' => 4],

            // Fila 13 (row 13)
            45 => ['row' => 13, 'column' => 0],
            46 => ['row' => 13, 'column' => 1],
            47 => ['row' => 13, 'column' => 3],
            48 => ['row' => 13, 'column' => 4],
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

        $this->command->info("  - Piso 2 (Bus {$bus->id}): " . count($floor2Layout) . " asientos organizados según layout específico con pasillo y CAFETERA");
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
     * Basado exactamente en el layout de la imagen proporcionada
     */
    private function createSpecialAreas(Bus $bus): void
    {
        // Eliminar áreas existentes para este bus (en caso de re-ejecutar)
        BusLayoutArea::where('bus_id', $bus->id)->delete();

        $floors = (int) $bus->floors;

        // Layout especial solo para el primer colectivo (bus_id = 1)
        if ($bus->id == 1 && $floors >= 1) {
            // BAÑO en la fila 1, columnas 0-1 (ocupa 2 columnas)
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

            // Pasillos blancos en la columna 2 (fila 1, y filas 2-5)
            // Fila 1, columna 2: pasillo blanco
            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '1',
                'area_type' => 'pasillo',
                'label' => '',
                'row_start' => 1,
                'row_end' => 1,
                'column_start' => 2,
                'column_end' => 2,
                'span_rows' => 1,
                'span_columns' => 1,
            ]);

            // Fila 1, columna 3: pasillo blanco
            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '1',
                'area_type' => 'pasillo',
                'label' => '',
                'row_start' => 1,
                'row_end' => 1,
                'column_start' => 3,
                'column_end' => 3,
                'span_rows' => 1,
                'span_columns' => 1,
            ]);

            // Pasillos en columna 2 para filas 2-5
            for ($row = 2; $row <= 5; $row++) {
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

            $this->command->info("  - BAÑO y pasillos creados en piso 1 del primer colectivo");
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

        // Segundo piso del primer colectivo
        if ($floors >= 2 && $bus->id == 1) {
            // CAFETERA en filas 3, columnas 3-4 (bloque 1x2)
            BusLayoutArea::create([
                'bus_id' => $bus->id,
                'floor' => '2',
                'area_type' => 'cafeteria',
                'label' => 'CAFE',
                'row_start' => 3,
                'row_end' => 3,
                'column_start' => 3,
                'column_end' => 4,
                'span_rows' => 1,
                'span_columns' => 2,
            ]);

            // Pasillo blanco en columna 2 para todas las filas (1-13)
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

            $this->command->info("  - CAFETERA y pasillos creados en piso 2 del primer colectivo");
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
