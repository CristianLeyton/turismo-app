<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\BusLayoutArea;
use App\Models\BusLayoutTemplate;
use App\Models\Seat;
use Illuminate\Support\Facades\DB;

/**
 * Fuente única del layout de un colectivo: lectura para el editor y
 * grabación atómica de asientos + áreas.
 *
 * Coordenadas: row/column empiezan en 1 (0 = sin posición en Seat legacy);
 * las áreas usan row_start..row_end y column_start..column_end inclusivos.
 */
class BusLayoutService
{
    /**
     * Layout completo de un colectivo agrupado por piso, listo para el editor.
     *
     * @return array{bus: Bus, floors: array<int, array{seats: array<int, array<string, mixed>>, areas: array<int, array<string, mixed>>}}>}
     */
    public function layoutFor(Bus $bus): array
    {
        $seats = $bus->seats()->orderBy('floor')->orderBy('row')->orderBy('column')->orderBy('seat_number')->get();
        $areas = $bus->layoutAreas()->orderBy('floor')->orderBy('row_start')->orderBy('column_start')->get();

        $floors = [];
        foreach (range(1, max(1, (int) $bus->floors)) as $floor) {
            $floors[$floor] = ['seats' => [], 'areas' => []];
        }

        foreach ($seats as $seat) {
            $floors[(int) ($seat->floor ?: 1)]['seats'][] = $this->seatToArray($seat);
        }

        foreach ($areas as $area) {
            $floors[(int) ($area->floor ?: 1)]['areas'][] = $this->areaToArray($area);
        }

        return ['bus' => $bus, 'floors' => $floors];
    }

    /**
     * Grabación atómica del layout: reemplaza TODOS los asientos y áreas del
     * colectivo con lo enviado por el editor, en una sola transacción.
     *
     * Reglas:
     *  - Un asiento con boletos NO se elimina ni pierde su número.
     *  - Ningún asiento puede pisar un área ni ocupar la celda de otro asiento.
     *  - Dos áreas del mismo piso no se superponen.
     *  - seat_count queda recalculado = asientos activos.
     *
     * @param  array{seats: array<int, array<string, mixed>>, areas: array<int, array<string, mixed>>}  $payload
     */
    public function saveLayout(Bus $bus, array $payload): void
    {
        DB::transaction(function () use ($bus, $payload) {
            $areas = $this->normalizeAreas($payload['areas'] ?? []);
            $seats = $this->normalizeSeats($payload['seats'] ?? []);

            // Solapamiento entre áreas del mismo piso.
            foreach ($areas as $i => $a) {
                foreach (array_slice($areas, $i + 1) as $b) {
                    if ($a['floor'] === $b['floor']
                        && $a['row_start'] <= $b['row_end'] && $b['row_start'] <= $a['row_end']
                        && $a['col_start'] <= $b['col_end'] && $b['col_start'] <= $a['col_end']) {
                        throw new \InvalidArgumentException(
                            "Las áreas \"{$a['label']}\" y \"{$b['label']}\" del piso {$a['floor']} se superponen.",
                        );
                    }
                }
            }

            // Celdas ocupadas por áreas, indexadas por piso y fila.
            $areaCells = [];
            foreach ($areas as $area) {
                for ($r = $area['row_start']; $r <= $area['row_end']; $r++) {
                    for ($c = $area['col_start']; $c <= $area['col_end']; $c++) {
                        $areaCells[$area['floor']][$r][] = $c;
                    }
                }
            }

            // Asientos: sin pisar áreas, celdas únicas y detalles de(QStringLiteral)/celdas.
            $cellOwners = [];
            $seenNumbers = [];

            foreach ($seats as $s) {
                if (in_array($s['column'], $areaCells[$s['floor']][$s['row']] ?? [], true)) {
                    throw new \InvalidArgumentException(
                        "El asiento {$s['seat_number']} cae dentro del área \"{$this->areaLabelAt($areas, $s)}\" (piso {$s['floor']}, fila {$s['row']}, columna {$s['column']}). Mueva primero el asiento o el área.",
                    );
                }

                $cellKey = "{$s['floor']}:{$s['row']}:{$s['column']}";
                if (isset($cellOwners[$cellKey])) {
                    throw new \InvalidArgumentException(
                        "Los asientos {$cellOwners[$cellKey]} y {$s['seat_number']} ocupan la misma posición (piso {$s['floor']}, fila {$s['row']}, columna {$s['column']}).",
                    );
                }
                $cellOwners[$cellKey] = $s['seat_number'];

                if (isset($seenNumbers[$s['seat_number']])) {
                    throw new \InvalidArgumentException(
                        "El número de asiento {$s['seat_number']} está duplicado en el layout.",
                    );
                }
                $seenNumbers[$s['seat_number']] = true;
            }

            // ── Protección de boletos vendidos ──
            $existingSeats = $bus->seats()->withTrashed()->get()->keyBy('id');
            $payloadExistingIds = collect($seats)->pluck('existing_id')->filter()->all();

            foreach ($existingSeats as $existing) {
                if (in_array($existing->id, $payloadExistingIds, true)) {
                    continue; // El asiento sobrevive en el payload.
                }

                $soldCount = $existing->tickets()->count();
                if ($soldCount > 0) {
                    throw new \InvalidArgumentException(
                        "El asiento {$existing->seat_number} tiene {$soldCount} boleto(s) vendido(s) y no puede eliminarse del layout. Desactívelo para sacarlo de la venta.",
                    );
                }
            }

            // ── Grabar asientos ──
            $keepSeatIds = [];

            foreach ($seats as $s) {
                if ($existingId = $s['existing_id']) {
                    $seat = $existingSeats->get($existingId);
                    if (! $seat) {
                        throw new \InvalidArgumentException("El asiento {$s['seat_number']} no pertenece a este colectivo.");
                    }
                } else {
                    $seat = new Seat(['bus_id' => $bus->id]);
                }

                $seat->fill([
                    'seat_number' => $s['seat_number'],
                    'floor' => $s['floor'],
                    'row' => $s['row'],
                    'column' => $s['column'],
                    'position' => $s['position'],
                    'seat_type' => $s['seat_type'],
                    'is_active' => $s['is_active'],
                ]);
                $seat->save();

                $keepSeatIds[] = $seat->id;
            }

            // Borrar asientos que el payload ya no incluye (sólo si no tienen boletos: validado arriba).
            $existingSeats->each(function (Seat $seat) use ($keepSeatIds) {
                if (! in_array($seat->id, $keepSeatIds, true)) {
                    $seat->forceDelete();
                }
            });

            // ── Grabar áreas: reemplazo directo (no venden ni referencian boletos) ──
            $bus->layoutAreas()->forceDelete();
            foreach ($areas as $area) {
                BusLayoutArea::create(array_merge(['bus_id' => $bus->id], $area['attributes']));
            }

            // seat_count recalculado: única fuente de verdad son los asientos activos.
            $bus->update(['seat_count' => $bus->seats()->where('is_active', true)->count()]);
        });
    }

    /**
     * Copia el layout (asientos + áreas) de otro colectivo a este. Para
     * "copiar de otro colectivo" / plantillas. Falla si el destino tiene
     * asientos con boletos vendidos (no destruye ventas).
     */
    public function copyLayoutFrom(Bus $target, Bus $source): void
    {
        $soldSeats = $target->seats()->withTrashed()->whereHas('tickets')->count();

        if ($soldSeats > 0) {
            throw new \InvalidArgumentException(
                "El colectivo tiene asientos con boletos vendidos; no se puede reemplazar su layout de esta forma.",
            );
        }

        DB::transaction(function () use ($target, $source) {
            $source->load(['seats', 'layoutAreas']);

            $target->layoutAreas()->forceDelete();
            $target->seats()->withTrashed()->get()->each->forceDelete();

            foreach ($source->seats as $seat) {
                Seat::create([
                    'bus_id' => $target->id,
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
                    'bus_id' => $target->id,
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

            $target->update([
                'seat_count' => $source->seats()->where('is_active', true)->count(),
                'floors' => $source->floors,
            ]);
        });
    }

    /**
     * Guarda el layout actual del colectivo como una plantilla con nombre.
     * Devuelve la plantilla creada para anunciarla en la notificación.
     */
    public function saveAsTemplate(Bus $bus, string $name): BusLayoutTemplate
    {
        $data = $this->layoutFor($bus);

        $layout = [
            'seats' => [],
            'areas' => [],
        ];

        foreach ($data['floors'] as $floorData) {
            foreach ($floorData['seats'] as $seat) {
                // Un asiento con boletos no debe clonarse a la plantilla:
                // la plantilla define diseño, no pasajeros.
                $layout['seats'][] = [
                    'seat_number' => $seat['seat_number'],
                    'floor' => $seat['floor'],
                    'row' => $seat['row'],
                    'column' => $seat['column'],
                    'position' => $seat['position'],
                    'seat_type' => $seat['seat_type'],
                    'is_active' => $seat['is_active'],
                ];
            }

            foreach ($floorData['areas'] as $area) {
                $layout['areas'][] = [
                    'area_type' => $area['area_type'],
                    'label' => $area['label'],
                    'floor' => $area['floor'],
                    'row_start' => $area['row_start'],
                    'row_end' => $area['row_end'],
                    'col_start' => $area['col_start'],
                    'col_end' => $area['col_end'],
                ];
            }
        }

        return BusLayoutTemplate::create([
            'name' => trim($name),
            'floors' => max(1, (int) $bus->floors),
            'layout' => $layout,
        ]);
    }

    /**
     * Plantillas disponibles (más nuevas primero) para el selector.
     *
     * @return array<int, array<string, mixed>>{ id, name, floors, seats, areas }
     */
    public function templatesForSelect(): array
    {
        return BusLayoutTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(fn (BusLayoutTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'floors' => (int) $t->floors,
                'seats' => count($t->layout['seats'] ?? []),
                'areas' => count($t->layout['areas'] ?? []),
            ])
            ->all();
    }

    /**
     * Aplica una plantilla al colectivo: guarda los asientos + áreas que
     * define. Falla si el colectivo destino pierde asientos vendidos.
     *
     * @return int Cantidad de asientos aplicados
     */
    public function applyTemplate(Bus $bus, BusLayoutTemplate $template): int
    {
        $layout = $template->layout;

        // El service espera el mismo formato que manda el editor.
        $this->saveLayout($bus, [
            'seats' => array_map(fn (array $s) => array_merge(['id' => null, 'tickets_count' => 0], $s), $layout['seats'] ?? []),
            'areas' => $layout['areas'] ?? [],
        ]);

        // La plantilla decide la cantidad de pisos: el diseño completo viaja con ella.
        $floors = max(1, (int) $template->floors);
        if ((int) $bus->floors !== $floors) {
            $bus->update(['floors' => $floors]);
        }

        return count($layout['seats'] ?? []);
    }

    /**
     * Raw en arrays planos para serializar al editor.
     */
    private function seatToArray(Seat $seat): array
    {
        return [
            'id' => $seat->id,
            'seat_number' => (string) $seat->seat_number,
            'floor' => (int) ($seat->floor ?: 1),
            'row' => (int) $seat->row,
            'column' => (int) $seat->column,
            'position' => $seat->position,
            'seat_type' => $seat->seat_type,
            'is_active' => (bool) $seat->is_active,
            'tickets_count' => $seat->tickets()->count(),
        ];
    }

    private function areaToArray(BusLayoutArea $area): array
    {
        return [
            'id' => $area->id,
            'area_type' => (string) $area->area_type,
            'label' => (string) $area->label,
            'floor' => (int) ($area->floor ?: 1),
            'row_start' => (int) $area->row_start,
            'row_end' => (int) $area->row_end,
            'col_start' => (int) $area->column_start,
            'col_end' => (int) $area->column_end,
        ];
    }

    /**
     * Áreas del payload → normalizadas (row/col inclusivos en 1-based) +
     * attributes listos para create().
     */
    private function normalizeAreas(array $rawAreas): array
    {
        $normalized = [];

        foreach (array_values($rawAreas) as $raw) {
            $floor = (int) ($raw['floor'] ?? 1);
            $rowStart = (int) ($raw['row_start'] ?? $raw['row'] ?? 0);
            $rowEnd = max($rowStart, (int) ($raw['row_end'] ?? $rowStart));
            $colStart = (int) ($raw['col_start'] ?? $raw['column_start'] ?? 0);
            $colEnd = max($colStart, (int) ($raw['col_end'] ?? $raw['column_end'] ?? $colStart));

            $type = (string) ($raw['area_type'] ?? 'other');
            $label = filled($raw['label'] ?? null) ? trim((string) $raw['label']) : $this->defaultLabel($type);

            $normalized[] = [
                'floor' => $floor,
                'area_type' => $type,
                'label' => $label,
                'row_start' => $rowStart,
                'row_end' => $rowEnd,
                'col_start' => $colStart,
                'col_end' => $colEnd,
                'attributes' => [
                    'floor' => $floor,
                    'area_type' => $type,
                    'label' => $label,
                    'row_start' => $rowStart,
                    'row_end' => $rowEnd,
                    'column_start' => $colStart,
                    'column_end' => $colEnd,
                    'span_rows' => $rowEnd - $rowStart + 1,
                    'span_columns' => $colEnd - $colStart + 1,
                ],
            ];
        }

        return $normalized;
    }

    /**
     * Asientos del payload → normalizados. Acepta existing_id (asiento que
     * sobrevive) o nuevo. Detecta duplicados de número y de celda.
     */
    private function normalizeSeats(array $rawSeats): array
    {
        $normalized = [];

        foreach (array_values($rawSeats) as $raw) {
            $number = trim((string) ($raw['seat_number'] ?? ''));

            if ($number === '') {
                throw new \InvalidArgumentException('Hay un asiento sin número en el layout.');
            }

            $normalized[] = [
                'existing_id' => isset($raw['id']) && filled($raw['id']) ? (int) $raw['id'] : null,
                'seat_number' => $number,
                'floor' => (int) ($raw['floor'] ?? 1),
                'row' => (int) ($raw['row'] ?? 0),
                'column' => (int) ($raw['column'] ?? 0),
                'position' => $raw['position'] ?? null,
                'seat_type' => $raw['seat_type'] ?? 'normal',
                'is_active' => (bool) ($raw['is_active'] ?? true),
            ];
        }

        return $normalized;
    }

    private function areaLabelAt(array $areas, array $seat): string
    {
        foreach ($areas as $area) {
            if ($area['floor'] === $seat['floor']
                && $area['row_start'] <= $seat['row'] && $area['row_end'] >= $seat['row']
                && $area['col_start'] <= $seat['column'] && $area['col_end'] >= $seat['column']) {
                return $area['label'];
            }
        }

        return '?';
    }

    private function defaultLabel(string $type): string
    {
        return match ($type) {
            'bathroom' => 'BAÑO',
            'cafeteria' => 'CAFETERA',
            'luggage' => 'EQUIPAJE',
            'driver' => 'CONDUCTOR',
            'pasillo' => 'PASILLO',
            default => 'ÁREA',
        };
    }
}
