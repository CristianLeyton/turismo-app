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

class SetupDonEsteban extends Command
{
    /**
     * Nombre y firma del comando.
     * Solo agrega datos para el colectivo "Don Esteban". No modifica ni elimina datos existentes.
     */
    protected $signature = 'app:setup-don-esteban
                            {--force : Recrear paradas/horarios/layout del bus Don Esteban aunque ya existan}
                            {--plate= : Patente del colectivo (opcional; editable desde el panel)}';

    protected $description = 'Agrega el colectivo "Don Esteban": bus, rutas ida (La Plata → El Charco) y vuelta (El Charco → La Plata) con sus paradas, horarios, asientos y layout. No toca datos existentes.';

    public function handle(): int
    {
        $this->info('Setup colectivo "Don Esteban" - Solo inserciones, sin modificar datos existentes.');
        $this->newLine();

        $force = $this->option('force');

        // 1. Colectivo Don Esteban — doble piso, 60 asientos (mismo layout/plantilla
        //    de 60 plazas que la Línea 2). La patente se completa a mano si no se
        //    pasa por --plate (es editable desde el panel de Colectivos).
        $bus = Bus::firstOrCreate(
            ['name' => 'Don Esteban'],
            [
                'name' => 'Don Esteban',
                'plate' => $this->option('plate'),
                'seat_count' => 60,
                'floors' => 2,
            ]
        );
        if ($bus->wasRecentlyCreated) {
            $this->info("  ✓ Bus 'Don Esteban' creado (ID: {$bus->id})");
        } else {
            $this->line("  - Bus 'Don Esteban' ya existe (ID: {$bus->id})");
        }

        // 2. Ubicaciones: todas las localidades de los flyers (se comparten
        //    entre ida y vuelta; se crean las que falten).
        $this->createLocations();

        // 3. Rutas (una de ida y una de vuelta, como la Línea 1 y 2)
        $routeIda = Route::firstOrCreate(
            ['name' => 'La Plata - El Charco', 'bus_id' => $bus->id],
            ['name' => 'La Plata - El Charco', 'bus_id' => $bus->id, 'is_active' => true]
        );
        $routeVuelta = Route::firstOrCreate(
            ['name' => 'El Charco - La Plata', 'bus_id' => $bus->id],
            ['name' => 'El Charco - La Plata', 'bus_id' => $bus->id, 'is_active' => true]
        );
        if ($routeIda->wasRecentlyCreated) {
            $this->info("  ✓ Ruta 'La Plata - El Charco' creada (ID: {$routeIda->id})");
        } else {
            $this->line("  - Ruta 'La Plata - El Charco' ya existe (ID: {$routeIda->id})");
        }
        if ($routeVuelta->wasRecentlyCreated) {
            $this->info("  ✓ Ruta 'El Charco - La Plata' creada (ID: {$routeVuelta->id})");
        } else {
            $this->line("  - Ruta 'El Charco - La Plata' ya existe (ID: {$routeVuelta->id})");
        }

        // 4. Paradas de las rutas (solo si no existen o --force).
        //    Los offsets son minutos desde la salida: la ida sale de La Plata
        //    13:00 y llega a El Charco 12:00 (+1 día) — cruzan la medianoche,
        //    así que los offsets de la tarde-noche/madrugada superan 1440/2.
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
        $this->info('Setup Don Esteban finalizado. Revisá en el panel que las rutas y horarios estén correctos.');

        return self::SUCCESS;
    }

    /** @return array<string, int> Localidad => minutos desde la salida (13:00 en La Plata). */
    private function stopsIda(): array
    {
        return [
            'La Plata' => 0,
            'F. Varela' => 60,             // 14:00
            'Burzaco' => 90,               // 14:30
            'Cruce Lomas' => 120,          // 15:00
            'San Justo' => 180,            // 16:00
            'Morón' => 200,                // 16:20
            'Ituzaingó' => 220,            // 16:40
            'Merlo' => 240,                // 17:00
            'Moreno' => 270,               // 17:30
            'Cruce Castelar' => 290,       // 17:50
            'Lemos' => 320,                // 18:20
            'José C. Paz' => 340,          // 18:40
            '197 Pacheco' => 360,          // 19:00
            'Escobar' => 390,              // 19:30
            'Campana' => 420,              // 20:00
            'San Pedro' => 480,            // 21:00
            'San Nicolás' => 520,          // 21:40
            'Rosario' => 570,              // 22:30
            'Rafaela' => 750,              // 01:30 (+1 día)
            'Ceres' => 870,                // 03:30 (+1 día)
            'Selva' => 890,                // 03:50
            'Pinto' => 940,                // 04:40
            'Real Sayana' => 960,          // 05:00
            'Icaño' => 975,                // 05:15
            'Colonia Dora' => 990,         // 05:30
            'Añatuya' => 1020,             // 06:00
            'Herrera' => 1050,             // 06:30
            'Colonia Mailín' => 1065,      // 06:45
            'Lugones' => 1080,             // 07:00
            'Garza' => 1110,               // 07:30
            'Taboada' => 1155,             // 08:15
            'Fernández' => 1180,           // 08:40
            'Forres' => 1200,              // 09:00
            'Beltrán' => 1215,             // 09:15
            'La Banda' => 1245,            // 09:45
            'Santiago del Estero' => 1260, // 10:00
            'Termas de Río Hondo' => 1320, // 11:00
            'Vinará' => 1340,              // 11:20
            'Pozuelo' => 1360,             // 11:40
            'El Charco' => 1380,           // 12:00 (+1 día)
        ];
    }

    /** @return array<string, int> Localidad => minutos desde la salida (11:00 en El Charco). */
    private function stopsVuelta(): array
    {
        return [
            'El Charco' => 0,              // 11:00
            'Pozuelo' => 15,               // 11:15
            'Vinará' => 30,                // 11:30
            'Termas de Río Hondo' => 60,   // 12:00
            'Santiago del Estero' => 120,  // 13:00
            'La Banda' => 150,             // 13:30
            'Beltrán' => 180,              // 14:00
            'Forres' => 195,               // 14:15
            'Fernández' => 210,            // 14:30
            'Taboada' => 230,              // 14:50
            'Garza' => 255,                // 15:15
            'Lugones' => 280,              // 15:40
            'Colonia Mailín' => 300,       // 16:00
            'Herrera' => 320,              // 16:20
            'Añatuya' => 360,              // 17:00
            'Colonia Dora' => 380,         // 17:20
            'Icaño' => 390,                // 17:30
            'Real Sayana' => 405,          // 17:45
            'Pinto' => 450,                // 18:30
            'Selva' => 510,                // 19:30
            'Ceres' => 540,                // 20:00
            'Rafaela' => 690,              // 22:30
            'Rosario' => 870,              // 01:30 (+1 día)
            'San Nicolás' => 920,          // 02:20
            'San Pedro' => 960,            // 03:00
            'Campana' => 1020,             // 04:00
            'Escobar' => 1035,             // 04:15
            '197 Pacheco' => 1050,         // 04:30
            'José C. Paz' => 1070,         // 04:50
            'Lemos' => 1090,               // 05:10
            'Cruce Castelar' => 1110,      // 05:30
            'Moreno' => 1130,              // 05:50
            'Merlo' => 1155,               // 06:15
            'Ituzaingó' => 1170,           // 06:30
            'Morón' => 1185,               // 06:45
            'San Justo' => 1215,           // 07:15
            'Cruce Lomas' => 1260,         // 08:00
            'Burzaco' => 1280,             // 08:20
            'F. Varela' => 1305,           // 08:45
            'La Plata' => 1350,            // 09:30 (+1 día)
        ];
    }

    private function createLocations(): void
    {
        $nombres = array_unique(array_merge(
            array_keys($this->stopsIda()),
            array_keys($this->stopsVuelta()),
        ));

        $creadas = 0;

        foreach ($nombres as $name) {
            $location = Location::firstOrCreate(
                ['name' => $name],
                ['name' => $name, 'is_active' => true]
            );

            if ($location->wasRecentlyCreated) {
                $creadas++;
            }
        }

        $this->info($creadas > 0
            ? "  ✓ Ubicaciones: {$creadas} creadas, ".(count($nombres) - $creadas).' ya existían.'
            : '  ✓ Ubicaciones: ya existían todas.');
    }

    private function createRouteStops(Route $routeIda, Route $routeVuelta, bool $force): void
    {
        if ($force) {
            RouteStop::where('route_id', $routeIda->id)->forceDelete();
            RouteStop::where('route_id', $routeVuelta->id)->forceDelete();
        }

        if (RouteStop::where('route_id', $routeIda->id)->exists() && ! $force) {
            $this->line('  - Paradas de ida y vuelta ya existen.');

            return;
        }

        $this->createStopsForRoute($routeIda, $this->stopsIda(), 'La Plata → El Charco');
        $this->createStopsForRoute($routeVuelta, $this->stopsVuelta(), 'El Charco → La Plata');
    }

    /** @param array<string, int> $stops Localidad => offset en minutos */
    private function createStopsForRoute(Route $route, array $stops, string $etiqueta): void
    {
        $order = 0;

        foreach ($stops as $name => $offset) {
            $location = Location::where('name', $name)->first();

            if (! $location) {
                $this->warn("  Ubicación '{$name}' no existe: parada omitida. Corré los seeders o creala desde Ubicaciones.");

                continue;
            }

            // Los flyers ponen un único horario por localidad; se usa como
            // hora de pasaje (fin de parada), igual que los seeders existentes.
            RouteStop::firstOrCreate(
                ['route_id' => $route->id, 'location_id' => $location->id, 'stop_order' => $order + 1],
                [
                    'route_id' => $route->id,
                    'location_id' => $location->id,
                    'stop_order' => $order + 1,
                    'departure_offset_minutes' => $offset,
                    'arrival_offset_minutes' => $offset,
                ]
            );

            $order++;
        }

        $this->info("  ✓ Paradas ruta {$etiqueta} creadas.");
    }

    private function createSchedules(Route $routeIda, Route $routeVuelta, bool $force): void
    {
        if ($force) {
            Schedule::where('route_id', $routeIda->id)->forceDelete();
            Schedule::where('route_id', $routeVuelta->id)->forceDelete();
        }

        if (! Schedule::where('route_id', $routeIda->id)->exists() || $force) {
            Schedule::create([
                'route_id' => $routeIda->id,
                'name' => 'Don Esteban (Ida)',
                'departure_time' => Carbon::createFromTime(13, 0, 0),  // Sale de La Plata 13:00
                'arrival_time' => Carbon::createFromTime(12, 0, 0),    // El Charco 12:00 (+1 día; el cast muestra H:i)
                'is_active' => true,
            ]);
            $this->info('  ✓ Horario ida Don Esteban (13:00 - 12:00) creado.');
        }

        if (! Schedule::where('route_id', $routeVuelta->id)->exists() || $force) {
            Schedule::create([
                'route_id' => $routeVuelta->id,
                'name' => 'Don Esteban (Vuelta)',
                'departure_time' => Carbon::createFromTime(11, 0, 0),  // Sale de El Charco 11:00
                'arrival_time' => Carbon::createFromTime(9, 30, 0),    // La Plata 09:30 (+1 día; el cast muestra H:i)
                'is_active' => true,
            ]);
            $this->info('  ✓ Horario vuelta Don Esteban (11:00 - 09:30) creado.');
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
