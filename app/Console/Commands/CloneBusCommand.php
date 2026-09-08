<?php

namespace App\Console\Commands;

use App\Models\Bus;
use App\Models\BusLayoutArea;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Schedule;
use App\Models\Seat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloneBusCommand extends Command
{
    protected $signature = 'app:clone-bus
        {source : ID o nombre del colectivo a copiar}
        {--name= : Nombre del nuevo colectivo (si se omite, queda "{origen} (copia)")}
        {--plate= : Patente del nuevo colectivo (si se omite, copia la original)}';

    protected $description = 'Clona un colectivo con layout (asientos y áreas), rutas, paradas y horarios. No copia viajes ni boletos.';

    public function handle(): int
    {
        $source = $this->resolveSourceBus((string) $this->argument('source'));

        if (! $source) {
            return self::FAILURE;
        }

        $newName = $this->option('name') ?: $source->name.' (copia)';
        $newPlate = $this->option('plate') !== null && $this->option('plate') !== ''
            ? $this->option('plate')
            : $source->plate;

        $source->load([
            'seats',
            'layoutAreas',
            'routes.stops',
            'routes.schedules',
        ]);

        $clone = DB::transaction(function () use ($source, $newName, $newPlate) {
            $clone = Bus::create([
                'name' => $newName,
                'plate' => $newPlate,
                'seat_count' => $source->seat_count,
                'floors' => $source->floors,
            ]);

            foreach ($source->seats as $seat) {
                Seat::create([
                    'bus_id' => $clone->id,
                    'seat_number' => $seat->seat_number,
                    'floor' => $seat->floor,
                    'row' => $seat->row,
                    'column' => $seat->column,
                    'position' => $seat->position,
                    'seat_type' => $seat->seat_type,
                    'is_active' => $seat->is_active,
                ]);
            }

            foreach ($source->layoutAreas as $area) {
                BusLayoutArea::create([
                    'bus_id' => $clone->id,
                    'floor' => $area->floor,
                    'area_type' => $area->area_type,
                    'label' => $area->label,
                    'row_start' => $area->row_start,
                    'row_end' => $area->row_end,
                    'column_start' => $area->column_start,
                    'column_end' => $area->column_end,
                    'span_rows' => $area->span_rows,
                    'span_columns' => $area->span_columns,
                ]);
            }

            foreach ($source->routes as $route) {
                $newRoute = Route::create([
                    'bus_id' => $clone->id,
                    'name' => $route->name,
                    'is_active' => $route->is_active,
                ]);

                foreach ($route->stops as $stop) {
                    RouteStop::create([
                        'route_id' => $newRoute->id,
                        'location_id' => $stop->location_id,
                        'stop_order' => $stop->stop_order,
                        'departure_offset_minutes' => $stop->departure_offset_minutes,
                        'arrival_offset_minutes' => $stop->arrival_offset_minutes,
                    ]);
                }

                foreach ($route->schedules as $schedule) {
                    Schedule::create([
                        'route_id' => $newRoute->id,
                        'name' => $schedule->name,
                        'departure_time' => $schedule->departure_time,
                        'arrival_time' => $schedule->arrival_time,
                        'is_active' => $schedule->is_active,
                    ]);
                }
            }

            return $clone;
        });

        $this->info("Colectivo clonado: {$clone->name} (ID {$clone->id})");
        $this->line("  Origen: {$source->name} (ID {$source->id})");
        $this->line("  Asientos: {$source->seats->count()}");
        $this->line("  Áreas de layout: {$source->layoutAreas->count()}");
        $this->line("  Rutas: {$source->routes->count()}");
        $this->line('  Paradas: '.$source->routes->sum(fn (Route $route) => $route->stops->count()));
        $this->line('  Horarios: '.$source->routes->sum(fn (Route $route) => $route->schedules->count()));
        $this->comment('No se copiaron viajes ni boletos.');

        return self::SUCCESS;
    }

    private function resolveSourceBus(string $source): ?Bus
    {
        if (ctype_digit($source)) {
            $bus = Bus::find((int) $source);
            if ($bus) {
                return $bus;
            }
        }

        $matches = Bus::query()->where('name', $source)->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            $this->error("Hay varios colectivos llamados \"{$source}\". Usá el ID:");
            foreach ($matches as $bus) {
                $this->line("  ID {$bus->id} — {$bus->name}".($bus->plate ? " ({$bus->plate})" : ''));
            }

            return null;
        }

        $this->error("No se encontró el colectivo \"{$source}\".");
        $this->line('Colectivos disponibles:');
        foreach (Bus::query()->orderBy('id')->get(['id', 'name', 'plate']) as $bus) {
            $this->line("  ID {$bus->id} — {$bus->name}".($bus->plate ? " ({$bus->plate})" : ''));
        }

        return null;
    }
}
