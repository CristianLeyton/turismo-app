<?php

namespace App\Console\Commands;

use App\Models\Bus;
use App\Models\BusLayoutArea;
use App\Models\Location;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\Seat;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SetupEmbarcacion extends Command
{
    /**
     * Nombre y firma del comando.
     * Solo agrega datos para la Línea 2 (Embarcación). No modifica ni elimina datos existentes.
     */
    protected $signature = 'app:setup-embarcacion
                            {--force : Crear aunque ya existan (recrear paradas/horarios/layout del bus Embarcación)}';

    protected $description = 'Agrega en producción la Línea 2 (Embarcación): ubicación, bus, rutas, paradas, horarios, asientos y layout. No toca datos existentes.';

    public function handle(): int
    {
        $this->info('Setup Línea 2 (Embarcación) - Solo inserciones, sin modificar datos existentes.');
        $this->newLine();

        $force = $this->option('force');

        // 1. Ubicación Embarcación
        $embarcacion = Location::firstOrCreate(
            ['name' => 'Embarcación'],
            ['name' => 'Embarcación', 'is_active' => true]
        );
        if ($embarcacion->wasRecentlyCreated) {
            $this->info("  ✓ Ubicación 'Embarcación' creada (ID: {$embarcacion->id})");
        } else {
            $this->line("  - Ubicación 'Embarcación' ya existe (ID: {$embarcacion->id})");
        }

        // 2. Bus Linea 2 (Embarcacion)
        $bus = Bus::firstOrCreate(
            ['name' => 'Linea 2 (Embarcacion)'],
            [
                'name' => 'Linea 2 (Embarcacion)',
                'plate' => 'EMB002',
                'seat_count' => 55,
                'floors' => 2,
            ]
        );
        if ($bus->wasRecentlyCreated) {
            $this->info("  ✓ Bus 'Linea 2 (Embarcacion)' creado (ID: {$bus->id})");
        } else {
            $this->line("  - Bus 'Linea 2 (Embarcacion)' ya existe (ID: {$bus->id})");
        }

        // 3. Rutas Embarcación - Salta y Salta - Embarcación
        $routeIda = Route::firstOrCreate(
            ['name' => 'Embarcación - Salta', 'bus_id' => $bus->id],
            ['name' => 'Embarcación - Salta', 'bus_id' => $bus->id]
        );
        $routeVuelta = Route::firstOrCreate(
            ['name' => 'Salta - Embarcación', 'bus_id' => $bus->id],
            ['name' => 'Salta - Embarcación', 'bus_id' => $bus->id]
        );
        if ($routeIda->wasRecentlyCreated) {
            $this->info("  ✓ Ruta 'Embarcación - Salta' creada (ID: {$routeIda->id})");
        } else {
            $this->line("  - Ruta 'Embarcación - Salta' ya existe (ID: {$routeIda->id})");
        }
        if ($routeVuelta->wasRecentlyCreated) {
            $this->info("  ✓ Ruta 'Salta - Embarcación' creada (ID: {$routeVuelta->id})");
        } else {
            $this->line("  - Ruta 'Salta - Embarcación' ya existe (ID: {$routeVuelta->id})");
        }

        // 4. Paradas de las rutas (solo si no existen o --force)
        $this->createRouteStops($routeIda, $routeVuelta, $force);

        // 5. Horarios (solo si no existen o --force)
        $this->createSchedules($routeIda, $routeVuelta, $force);

        // 6. Asientos del bus (solo si el bus no tiene asientos)
        $seatCount = Seat::where('bus_id', $bus->id)->count();
        if ($seatCount === 0) {
            $this->createSeats($bus);
        } else {
            $this->line("  - Bus ya tiene {$seatCount} asientos, no se crean nuevos.");
        }

        // 7. Layout de asientos y áreas (55 plazas)
        if ($seatCount === 0 || $force) {
            $this->applySeatLayout55($bus);
            $this->createLayoutAreas55($bus);
        }

        $this->newLine();
        $this->info('Setup Embarcación finalizado. Revisá en el panel que las rutas y horarios estén correctos.');

        return self::SUCCESS;
    }

    private function createRouteStops(Route $routeIda, Route $routeVuelta, bool $force): void
    {
        $nombresIda = [
            'Embarcación', 'Orán', 'Yrigoyen', 'Pichanal', 'Colonia Santa Rosa',
            'Urundel', 'Gral. Güemes', 'Salta',
        ];
        $nombresVuelta = [
            'Salta', 'Gral. Güemes', 'Urundel', 'Colonia Santa Rosa', 'Pichanal',
            'Yrigoyen', 'Orán', 'Embarcación',
        ];

        if ($force) {
            RouteStop::where('route_id', $routeIda->id)->forceDelete();
            RouteStop::where('route_id', $routeVuelta->id)->forceDelete();
        }

        if (RouteStop::where('route_id', $routeIda->id)->exists() && !$force) {
            $this->line('  - Paradas de ida ya existen.');
            return;
        }

        foreach ($nombresIda as $i => $name) {
            $loc = Location::where('name', $name)->first();
            if (!$loc) {
                $this->warn("  Ubicación '{$name}' no existe. Creála desde Locaciones o ejecutá los seeders de ubicaciones.");
                continue;
            }
            RouteStop::firstOrCreate(
                ['route_id' => $routeIda->id, 'location_id' => $loc->id, 'stop_order' => $i + 1],
                ['route_id' => $routeIda->id, 'location_id' => $loc->id, 'stop_order' => $i + 1]
            );
        }
        $this->info('  ✓ Paradas ruta Embarcación → Salta creadas.');

        foreach ($nombresVuelta as $i => $name) {
            $loc = Location::where('name', $name)->first();
            if (!$loc) {
                $this->warn("  Ubicación '{$name}' no existe.");
                continue;
            }
            RouteStop::firstOrCreate(
                ['route_id' => $routeVuelta->id, 'location_id' => $loc->id, 'stop_order' => $i + 1],
                ['route_id' => $routeVuelta->id, 'location_id' => $loc->id, 'stop_order' => $i + 1]
            );
        }
        $this->info('  ✓ Paradas ruta Salta → Embarcación creadas.');
    }

    private function createSchedules(Route $routeIda, Route $routeVuelta, bool $force): void
    {
        if ($force) {
            Schedule::where('route_id', $routeIda->id)->forceDelete();
            Schedule::where('route_id', $routeVuelta->id)->forceDelete();
        }

        if (!Schedule::where('route_id', $routeIda->id)->exists() || $force) {
            Schedule::create([
                'route_id' => $routeIda->id,
                'name' => 'Madrugada',
                'departure_time' => Carbon::createFromTime(1, 30, 0),
                'arrival_time' => Carbon::createFromTime(6, 30, 0),
                'is_active' => true,
            ]);
            $this->info('  ✓ Horario ida Madrugada (01:30 - 06:30) creado.');
        }

        if (!Schedule::where('route_id', $routeVuelta->id)->exists() || $force) {
            Schedule::create([
                'route_id' => $routeVuelta->id,
                'name' => 'Tarde',
                'departure_time' => Carbon::createFromTime(15, 0, 0),
                'arrival_time' => Carbon::createFromTime(20, 0, 0),
                'is_active' => true,
            ]);
            $this->info('  ✓ Horario vuelta Tarde (15:00 - 20:00) creado.');
        }
    }

    private function createSeats(Bus $bus): void
    {
        for ($i = 1; $i <= 55; $i++) {
            $floor = $i <= 9 ? '1' : '2';
            Seat::firstOrCreate(
                ['bus_id' => $bus->id, 'seat_number' => $i],
                ['bus_id' => $bus->id, 'seat_number' => $i, 'is_active' => true, 'floor' => $floor]
            );
        }
        $this->info('  ✓ 55 asientos creados para el bus (piso 1: 1-9, piso 2: 10-55).');
    }

    /** Layout piso 1 y 2 para bus 55 asientos (mismo que SeatLayoutSeeder). */
    private function applySeatLayout55(Bus $bus): void
    {
        $floor1 = [
            1 => ['row' => 2, 'column' => 0], 2 => ['row' => 2, 'column' => 1], 3 => ['row' => 2, 'column' => 3],
            4 => ['row' => 3, 'column' => 0], 5 => ['row' => 3, 'column' => 1], 6 => ['row' => 3, 'column' => 3],
            7 => ['row' => 4, 'column' => 0], 8 => ['row' => 4, 'column' => 1], 9 => ['row' => 4, 'column' => 3],
        ];
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

        foreach ($floor1 as $num => $layout) {
            Seat::where('bus_id', $bus->id)->where('seat_number', $num)->update([
                'row' => $layout['row'], 'column' => $layout['column'],
                'position' => $layout['column'] < 2 ? 'left' : 'right', 'seat_type' => 'normal',
            ]);
        }
        foreach ($floor2 as $num => $layout) {
            Seat::where('bus_id', $bus->id)->where('seat_number', $num)->update([
                'row' => $layout[0], 'column' => $layout[1],
                'position' => $layout[1] < 2 ? 'left' : 'right', 'seat_type' => 'normal',
            ]);
        }
        $this->info('  ✓ Layout de asientos 55 plazas aplicado.');
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

        $this->info('  ✓ Áreas especiales (baño, pasillo, cafetería) creadas.');
    }
}
