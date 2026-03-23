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

        // 2. Bus Linea 2 (Embarcacion) — 60 asientos, layout 60 plazas
        $bus = Bus::firstOrCreate(
            ['name' => 'Linea 2 (Embarcacion)'],
            [
                'name' => 'Linea 2 (Embarcacion)',
                'plate' => 'EMB002',
                'seat_count' => 60,
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

        // 6. Asientos del bus (solo si el bus no tiene asientos) — 60 plazas
        $seatCount = Seat::where('bus_id', $bus->id)->count();
        if ($seatCount === 0) {
            $this->createSeats60($bus);
        } else {
            $this->line("  - Bus ya tiene {$seatCount} asientos, no se crean nuevos.");
        }

        // 7. Layout de asientos y áreas (60 plazas)
        if ($seatCount === 0 || $force) {
            $this->applySeatLayout60($bus);
            $this->createLayoutAreas60($bus);
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
                ['route_id' => $routeIda->id, 'location_id' => $loc->id, 'stop_order' => $i + 1, 'departure_offset_minutes' => $i * 15, 'arrival_offset_minutes' => $i * 15]
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
                ['route_id' => $routeVuelta->id, 'location_id' => $loc->id, 'stop_order' => $i + 1, 'departure_offset_minutes' => $i * 15, 'arrival_offset_minutes' => $i * 15]
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

    /** 60 asientos: piso 2 = 1-48, piso 1 = 49-60 */
    private function createSeats60(Bus $bus): void
    {
        for ($i = 1; $i <= 60; $i++) {
            $floor = $i <= 48 ? '2' : '1';
            Seat::firstOrCreate(
                ['bus_id' => $bus->id, 'seat_number' => $i],
                ['bus_id' => $bus->id, 'seat_number' => $i, 'is_active' => true, 'floor' => $floor]
            );
        }
        $this->info('  ✓ 60 asientos creados para el bus (piso 2: 1-48, piso 1: 49-60).');
    }

    /** Layout 60 plazas: piso 1 (49-60), piso 2 (1-48) — mismo que SeatLayoutSeeder. */
    private function applySeatLayout60(Bus $bus): void
    {
        $floor1 = [
            49 => ['row' => 1, 'column' => 0], 50 => ['row' => 1, 'column' => 1], 51 => ['row' => 1, 'column' => 3],
            52 => ['row' => 2, 'column' => 0], 53 => ['row' => 2, 'column' => 1], 54 => ['row' => 2, 'column' => 3],
            55 => ['row' => 3, 'column' => 0], 56 => ['row' => 3, 'column' => 1], 57 => ['row' => 3, 'column' => 3],
            58 => ['row' => 4, 'column' => 0], 59 => ['row' => 4, 'column' => 1], 60 => ['row' => 4, 'column' => 3],
        ];
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
        $this->info('  ✓ Layout de asientos 60 plazas aplicado.');
    }

    /** Pasillos y espacios vacíos para 60 plazas (2 pisos). */
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
        $this->info('  ✓ Pasillos y áreas (60 plazas) creados.');
    }
}
