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
     * Primer piso: 55 plazas = 9 asientos (Unidad 2). 60 plazas = 12 asientos 49-60 (Unidad 1).
     */
    private function assignFloor1Layout(Bus $bus): void
    {
        if ($bus->seat_count == 60 && $bus->floors >= 2) {
            $this->assignFloor1Layout60($bus);
            return;
        }
        if ($bus->seat_count == 55 && $bus->floors >= 2) {
            $this->assignFloor1Layout55($bus);
            return;
        }
        $this->assignStandardLayout($bus, '1');
    }

    /**
     * Layout piso 1 para colectivo 60 asientos: 49 50 P 51, 52 53 P 54, 55 56 P 57, 58 59 P 60
     */
    private function assignFloor1Layout60(Bus $bus): void
    {
        $floor1Layout = [
            49 => ['row' => 1, 'column' => 0],
            50 => ['row' => 1, 'column' => 1],
            51 => ['row' => 1, 'column' => 3],
            52 => ['row' => 2, 'column' => 0],
            53 => ['row' => 2, 'column' => 1],
            54 => ['row' => 2, 'column' => 3],
            55 => ['row' => 3, 'column' => 0],
            56 => ['row' => 3, 'column' => 1],
            57 => ['row' => 3, 'column' => 3],
            58 => ['row' => 4, 'column' => 0],
            59 => ['row' => 4, 'column' => 1],
            60 => ['row' => 4, 'column' => 3],
        ];

        $seatsOnFloor = Seat::where('bus_id', $bus->id)
            ->where('floor', '1')
            ->where('is_active', true)
            ->get()
            ->keyBy('seat_number');

        foreach ($floor1Layout as $seatNumber => $layout) {
            if (isset($seatsOnFloor[$seatNumber])) {
                $seat = $seatsOnFloor[$seatNumber];
                $seat->update([
                    'row' => $layout['row'],
                    'column' => $layout['column'],
                    'position' => $layout['column'] < 2 ? 'left' : 'right',
                    'seat_type' => 'normal',
                ]);
            }
        }
        $this->command->info("  - Piso 1 (Bus {$bus->id}): 12 asientos según layout 60 plazas");
    }

    /**
     * Layout piso 1 para colectivo 55 asientos: B B P E, 1 2 P 3, 4 5 P 6, 7 8 P 9
     */
    private function assignFloor1Layout55(Bus $bus): void
    {

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

        $this->command->info("  - Piso 1 (Bus {$bus->id}): " . count($floor1Layout) . " asientos según layout 55 plazas");
    }

    /**
     * Segundo piso: 60 plazas = asientos 1-48 (Unidad 1). 55 plazas = asientos 10-55 (Unidad 2).
     */
    private function assignFloor2Layout(Bus $bus): void
    {
        if ($bus->seat_count == 60 && $bus->floors >= 2) {
            $this->assignFloor2Layout60($bus);
            return;
        }
        if ($bus->seat_count != 55 || $bus->floors < 2) {
            $this->assignStandardLayout($bus, '2');
            return;
        }
        $this->assignFloor2Layout55($bus);
    }

    /**
     * Layout piso 2 para colectivo 60 asientos (ARRIBA): 1-48
     * 1 2 P 3 4 | 6 5 P E E | 8 7 P E E | 10 9 P 12 11 ... | 45 46 P 47 48
     */
    private function assignFloor2Layout60(Bus $bus): void
    {
        $floor2Layout = [
            // Fila 1: 1 2 P 3 4
            1 => ['row' => 1, 'column' => 0],
            2 => ['row' => 1, 'column' => 1],
            3 => ['row' => 1, 'column' => 3],
            4 => ['row' => 1, 'column' => 4],
            // Fila 2: 6 5 P E E
            6 => ['row' => 2, 'column' => 0],
            5 => ['row' => 2, 'column' => 1],
            // Fila 3: 8 7 P E E
            8 => ['row' => 3, 'column' => 0],
            7 => ['row' => 3, 'column' => 1],
            // Fila 4: 10 9 P 12 11
            10 => ['row' => 4, 'column' => 0],
            9 => ['row' => 4, 'column' => 1],
            12 => ['row' => 4, 'column' => 3],
            11 => ['row' => 4, 'column' => 4],
            // Fila 5: 13 14 P 16 15
            13 => ['row' => 5, 'column' => 0],
            14 => ['row' => 5, 'column' => 1],
            16 => ['row' => 5, 'column' => 3],
            15 => ['row' => 5, 'column' => 4],
            // Fila 6: 17 18 P 20 19
            17 => ['row' => 6, 'column' => 0],
            18 => ['row' => 6, 'column' => 1],
            20 => ['row' => 6, 'column' => 3],
            19 => ['row' => 6, 'column' => 4],
            // Fila 7: 21 22 P 24 23
            21 => ['row' => 7, 'column' => 0],
            22 => ['row' => 7, 'column' => 1],
            24 => ['row' => 7, 'column' => 3],
            23 => ['row' => 7, 'column' => 4],
            // Fila 8: 25 26 P 28 27
            25 => ['row' => 8, 'column' => 0],
            26 => ['row' => 8, 'column' => 1],
            28 => ['row' => 8, 'column' => 3],
            27 => ['row' => 8, 'column' => 4],
            // Fila 9: 29 30 P 32 31
            29 => ['row' => 9, 'column' => 0],
            30 => ['row' => 9, 'column' => 1],
            32 => ['row' => 9, 'column' => 3],
            31 => ['row' => 9, 'column' => 4],
            // Fila 10: 33 34 P 36 35
            33 => ['row' => 10, 'column' => 0],
            34 => ['row' => 10, 'column' => 1],
            36 => ['row' => 10, 'column' => 3],
            35 => ['row' => 10, 'column' => 4],
            // Fila 11: 37 38 P 40 39
            37 => ['row' => 11, 'column' => 0],
            38 => ['row' => 11, 'column' => 1],
            40 => ['row' => 11, 'column' => 3],
            39 => ['row' => 11, 'column' => 4],
            // Fila 12: 41 42 P 44 43
            41 => ['row' => 12, 'column' => 0],
            42 => ['row' => 12, 'column' => 1],
            44 => ['row' => 12, 'column' => 3],
            43 => ['row' => 12, 'column' => 4],
            // Fila 13: 45 46 P 47 48
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
                $seat->update([
                    'row' => $layout['row'],
                    'column' => $layout['column'],
                    'position' => $layout['column'] < 2 ? 'left' : 'right',
                    'seat_type' => 'normal',
                ]);
            }
        }
        $this->command->info("  - Piso 2 (Bus {$bus->id}): 48 asientos según layout 60 plazas");
    }

    /**
     * Layout piso 2 para colectivo 55 asientos (asientos 10-55)
     */
    private function assignFloor2Layout55(Bus $bus): void
    {
        // 5 columnas (0-4), columna 2 es pasillo (P)
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
                $seat->update([
                    'row' => $layout['row'],
                    'column' => $layout['column'],
                    'position' => $layout['column'] < 2 ? 'left' : 'right',
                    'seat_type' => 'normal',
                ]);
            }
        }
        $this->command->info("  - Piso 2 (Bus {$bus->id}): " . count($floor2Layout) . " asientos según layout 55 plazas");
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

        $is55SeatsTwoFloors = ($bus->seat_count == 55 && (int) $bus->floors >= 2);
        $is60SeatsTwoFloors = ($bus->seat_count == 60 && (int) $bus->floors >= 2);

        // Colectivo 60 asientos (Unidad 1): pasillo y espacios E
        if ($is60SeatsTwoFloors && $floors >= 1) {
            // Piso 1 (abajo): pasillo columna 2, filas 1-4
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
            $this->command->info("  - Pasillo piso 1 creado (colectivo 60 plazas)");
        }
        if ($is60SeatsTwoFloors && $floors >= 2) {
            // Piso 2 (arriba): pasillo columna 2, filas 1-13
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
            // Espacios E en filas 2-3, columnas 3-4 (6 5 P E E / 8 7 P E E)
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
            $this->command->info("  - Pasillo y espacios E en piso 2 (colectivo 60 plazas)");
        }

        // Colectivo 55 asientos (Unidad 2)
        if ($is55SeatsTwoFloors && $floors >= 1) {
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

            $this->command->info("  - BAÑO, pasillo y espacio vacío creados en piso 1 (colectivo 55 plazas)");
        } elseif ($floors >= 1 && !$is60SeatsTwoFloors) {
            // Otros colectivos (ni 55 ni 60), layout genérico con CAFETERA
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

        // Segundo piso: áreas especiales para colectivos de 55 asientos y 2 pisos
        if ($floors >= 2 && $is55SeatsTwoFloors) {
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

            $this->command->info("  - Pasillo central, espacios vacíos y cafetería en piso 2 (colectivo 55 plazas)");
        } elseif ($floors >= 2 && !$is55SeatsTwoFloors && !$is60SeatsTwoFloors) {
            // BAÑO en el segundo piso para otros colectivos (ni 55 ni 60)
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
