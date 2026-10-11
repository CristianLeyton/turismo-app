<?php

namespace App\Filament\Resources\Buses\Pages;

use App\Filament\Resources\Buses\BusResource;
use App\Models\Bus;
use App\Models\BusLayoutArea;
use App\Models\Seat;
use App\Services\BusLayoutService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Support\Facades\Auth;

class DesignBusLayout extends ResourcePage
{
    use InteractsWithRecord;

    protected static string $resource = BusResource::class;

    protected static ?string $breadcrumb = 'Diseño del layout';

    protected ?string $heading = 'Diseño del layout';

    protected static ?string $title = 'Diseño del layout';

    protected string $view = 'buses.design-layout';

    /** Piso activo en el editor (1-based). */
    public int $floor = 1;

    /** Tipo que se aplicará al área dibujada (elegido en el selector del modo zonas). */
    public string $pendingAreaType = 'bathroom';

    /** Nombre ingresado en la modal "Guardar como plantilla". */
    public ?string $templateName = null;

    /**
     * Estado del layout editado (copia local hasta "Guardar").
     *
     * @var array<int, array<string, mixed>>
     */
    public array $seats = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $areas = [];

    public int $gridRows = 10;

    public int $gridCols = 5;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
        $this->loadLayout();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::check() && (bool) Auth::user()?->can('buses.update');
    }

    protected function service(): BusLayoutService
    {
        return app(BusLayoutService::class);
    }

    protected function loadLayout(): void
    {
        $data = $this->service()->layoutFor($this->getRecord());
        $floors = array_values($data['floors']);

        $this->seats = [];
        $this->areas = [];
        foreach ($floors as $i => $floorData) {
            foreach ($floorData['seats'] as $seat) {
                $seat['floor'] = $i + 1; // Reindexa piso si el bus cambió de pisos.
                $this->seats[] = $seat;
            }
            foreach ($floorData['areas'] as $area) {
                $area['floor'] = $i + 1;
                $this->areas[] = $area;
            }
        }

        $this->floor = 1;
        $this->refreshGridSize();
    }

    public function getRecord(): Bus
    {
        /** @var Bus $record */
        $record = $this->record;

        return $record;
    }

    // ─────────────────────────── Grilla ───────────────────────────

    public function refreshGridSize(): void
    {
        $maxRow = 9;
        $maxCol = 4;

        foreach ($this->seatsForFloor($this->floor) as $seat) {
            $maxRow = max($maxRow, (int) $seat['row']);
            $maxCol = max($maxCol, (int) $seat['column']);
        }
        foreach ($this->areasForFloor($this->floor) as $area) {
            $maxRow = max($maxRow, (int) $area['row_end']);
            $maxCol = max($maxCol, (int) $area['col_end']);
        }

        // +2 filas y una columna extra para dar aire de edición.
        // Los datos pueden ser 0-based (columna 0) o 1-based: se conserva el
        // rango real para no alterar las coordenadas guardadas.
        $this->gridRows = $maxRow + 3;
        $this->gridCols = $maxCol + 2;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function seatsForFloor(int $floor): array
    {
        return array_values(array_filter($this->seats, fn ($s) => (int) $s['floor'] === $floor));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function areasForFloor(int $floor): array
    {
        return array_values(array_filter($this->areas, fn ($a) => (int) $a['floor'] === $floor));
    }

    /**
     * Asiento en una celda de este piso, o null.
     *
     * @return array<string, mixed>|null
     */
    public function seatAt(int $row, int $col): ?array
    {
        foreach ($this->seatsForFloor($this->floor) as $seat) {
            if ((int) $seat['row'] === $row && (int) $seat['column'] === $col) {
                return $seat;
            }
        }

        return null;
    }

    /**
     * @return array{label: string, type: string}|null
     */
    public function areaAt(int $row, int $col): ?array
    {
        foreach ($this->areasForFloor($this->floor) as $area) {
            if ($area['row_start'] <= $row && $area['row_end'] >= $row
                && $area['col_start'] <= $col && $area['col_end'] >= $col) {
                return ['label' => $area['label'], 'type' => $area['area_type']];
            }
        }

        return null;
    }

    public function isFloorValid(int $floor): bool
    {
        return $floor >= 1 && $floor <= max(1, (int) $this->getRecord()->floors);
    }

    public function setFloor(int $floor): void
    {
        if ($this->isFloorValid($floor)) {
            $this->floor = $floor;
            $this->refreshGridSize();
        }
    }

    // ─────────────────────────── Asientos ───────────────────────────

    /** Clic en celda vacía: agrega asiento con el primer número libre. */
    public function addSeatAt(int $row, int $col): void
    {
        if ($this->areaAt($row, $col) || $this->seatAt($row, $col)) {
            return; // Es un "toggle" por UI: nada si la celda está ocupada.
        }

        $used = array_map(fn ($s) => (int) $s['seat_number'], $this->seats);
        $next = 1;
        while (in_array($next, $used, true)) {
            $next++;
        }

        $this->seats[] = [
            'id' => null,
            'seat_number' => (string) $next,
            'floor' => $this->floor,
            'row' => $row,
            'column' => $col,
            'position' => null,
            'seat_type' => 'normal',
            'is_active' => true,
            'tickets_count' => 0,
        ];

        $this->refreshGridSize();
    }

    /** Clic en un asiento: lo elimina (si no tiene boletos). */
    public function removeSeatAt(int $row, int $col): void
    {
        $seat = $this->seatAt($row, $col);

        if (! $seat) {
            return;
        }

        if (($seat['tickets_count'] ?? 0) > 0 || ($seat['id'] && $this->seatHasTickets($seat['id']))) {
            Notification::make()
                ->title('No se puede eliminar el asiento')
                ->body("El asiento {$seat['seat_number']} tiene boletos vendidos. Desactívelo en su lugar.")
                ->danger()
                ->send();

            return;
        }

        $this->seats = array_values(array_filter(
            $this->seats,
            fn ($s) => ! ((int) $s['floor'] === $this->floor && (int) $s['row'] === $row && (int) $s['column'] === $col),
        ));
    }

    /** Toggle activo/inactivo sin borrar el asiento (para con boletos). */
    public function toggleSeatAt(int $row, int $col): void
    {
        foreach ($this->seats as $i => $seat) {
            if ((int) $seat['floor'] === $this->floor && (int) $seat['row'] === $row && (int) $seat['column'] === $col) {
                $this->seats[$i]['is_active'] = ! $this->seats[$i]['is_active'];
                break;
            }
        }
    }

    /** Cambia el tipo (normal/especial/discapacitado) del asiento. */
    public function setSeatTypeAt(int $row, int $col, string $type): void
    {
        foreach ($this->seats as $i => $seat) {
            if ((int) $seat['floor'] === $this->floor && (int) $seat['row'] === $row && (int) $seat['column'] === $col) {
                $this->seats[$i]['seat_type'] = $type;
                break;
            }
        }
    }

    /** Mueve un asiento a otra celda (por explicación del usuario o drag). */
    public function moveSeat(int $row, int $col, int $toRow, int $toCol): void
    {
        $seat = $this->seatAt($row, $col);

        if (! $seat) {
            return;
        }

        if ($this->areaAt($toRow, $toCol) || $this->seatAt($toRow, $toCol)) {
            Notification::make()
                ->title('Celda ocupada')
                ->body('Hay un área o asiento en la celda de destino; primero mové el asiento o quitá el área.')
                ->warning()
                ->send();

            return;
        }

        foreach ($this->seats as $i => $s) {
            if ($s === $seat) {
                $this->seats[$i]['row'] = $toRow;
                $this->seats[$i]['column'] = $toCol;
                break;
            }
        }

        $this->refreshGridSize();
    }

    // ─────────────────────────── Áreas ───────────────────────────

    /**
     * Crea el área en un solo ida y vuelta: el navegador ya resolvió el
     * rectángulo con el arrastre (mouse down → enter → up).
     */
    public function completeRect(int $rowStart, int $colStart, int $rowEnd, int $colEnd): void
    {
        $rowStart = min($rowStart, $rowEnd);
        $rowEnd = max($rowStart, $rowEnd);
        $colStart = min($colStart, $colEnd);
        $colEnd = max($colStart, $colEnd);

        // ¿Pisa un asiento?
        foreach ($this->seatsForFloor($this->floor) as $seat) {
            if ($seat['row'] >= $rowStart && $seat['row'] <= $rowEnd
                && $seat['column'] >= $colStart && $seat['column'] <= $colEnd) {
                Notification::make()
                    ->title('La zona tapa un asiento')
                    ->body("La zona seleccionada incluye el asiento {$seat['seat_number']}. Quitá primero el asiento.")
                    ->danger()
                    ->send();

                return;
            }
        }

        // ¿Pisa una zona?
        foreach ($this->areasForFloor($this->floor) as $existing) {
            if ($existing['row_start'] <= $rowEnd && $rowStart <= $existing['row_end']
                && $existing['col_start'] <= $colEnd && $colStart <= $existing['col_end']) {
                Notification::make()
                    ->title('La zona se superpone a otra')
                    ->body("Ya existe la zona \"{$existing['label']}\" en esa parte de la grilla.")
                    ->danger()
                    ->send();

                return;
            }
        }

        $this->areas[] = [
            'id' => null,
            'floor' => $this->floor,
            'area_type' => $this->pendingAreaType,
            'label' => $this->defaultAreaLabel($this->pendingAreaType),
            'row_start' => $rowStart,
            'row_end' => $rowEnd,
            'col_start' => $colStart,
            'col_end' => $colEnd,
        ];

        $this->refreshGridSize();
    }

    /** Elimina un área (por etiqueta y coordenadas, única celda tocada). */
    public function removeAreaAt(int $row, int $col): void
    {
        $this->areas = array_values(array_filter(
            $this->areas,
            function ($a) use ($row, $col) {
                $touches = (int) $a['floor'] === $this->floor
                    && $a['row_start'] <= $row && $a['row_end'] >= $row
                    && $a['col_start'] <= $col && $a['col_end'] >= $col;

                return ! $touches;
            },
        ));

        $this->refreshGridSize();
    }

    /**
     * Cambia el tipo/etiqueta de un área exacta (match por coordenadas).
     */
    public function updateArea(int $row, int $col, string $type, ?string $label = null): void
    {
        foreach ($this->areas as $i => $area) {
            $touches = (int) $area['floor'] === $this->floor
                && $area['row_start'] <= $row && $area['row_end'] >= $row
                && $area['col_start'] <= $col && $area['col_end'] >= $col;

            if ($touches) {
                $this->areas[$i]['area_type'] = $type;
                $this->areas[$i]['label'] = $label ?: $this->defaultAreaLabel($type);
                break;
            }
        }
    }

    private function defaultAreaLabel(string $type): string
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

    // ─────────────────────────── Guardar ───────────────────────────

    public function save(): void
    {
        try {
            $this->service()->saveLayout($this->getRecord(), [
                'seats' => $this->seats,
                'areas' => $this->areas,
            ]);

            $this->loadLayout(); // Recarga con IDs reales.

            Notification::make()
                ->title('Layout guardado')
                ->body('Los asientos y áreas del colectivo se actualizaron correctamente.')
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('No se pudo guardar el layout')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function discard(): void
    {
        $this->loadLayout();

        Notification::make()
            ->title('Cambios descartados')
            ->body('El layout volvió a la última versión guardada.')
            ->info()
            ->send();
    }

    /**
     * Guarda el diseño ACTUAL (guardado en BD) como plantilla reutilizable.
     * Usa modal para pedir el nombre amigable de la plantilla.
     */
    public function saveAsTemplate(): void
    {
        $name = trim((string) ($this->templateName ?? ''));

        if ($name === '') {
            Notification::make()->title('Poné un nombre para la plantilla')->warning()->send();

            return;
        }

        if (\App\Models\BusLayoutTemplate::withTrashed()->where('name', $name)->exists()) {
            Notification::make()
                ->title('Ya existe una plantilla con ese nombre')
                ->body('Elegí otro nombre para diferenciarla.')
                ->warning()
                ->send();

            return;
        }

        $template = $this->service()->saveAsTemplate($this->getRecord(), $name);

        Notification::make()
            ->title("Plantilla \"{$template->name}\" guardada")
            ->body('La vas a encontrar como opción al crear otro colectivo.')
            ->success()
            ->send();
    }

    /** Formulario de la modal "Guardar como plantilla". */
    protected function templateFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre de la plantilla')
                ->required()
                ->maxLength(120)
                ->placeholder('Ej: Colectivo 2 pisos con baño al fondo')
                ->helperText('Va a aparecer en la lista al crear otro colectivo.'),
        ];
    }

    /**
     * Renumeración en memoria del editor (01toLocaleString separado del
     * servicio porque acá lo usuario quizo todavía no guardó): de adelante
     * hacia atrás, piso por piso, de izquierda a derecha. Los asientos con
     * boletos vendidos conservan su número.
     */
    public function renumber(): void
    {
        $soldIds = collect($this->seats)
            ->filter(fn ($s) => filled($s['id'] ?? null) && $this->seatHasTickets((int) $s['id']))
            ->pluck('id')
            ->all();

        // Orden pedido: piso, fila (de adelante hacia atrás), columna.
        $ordered = collect($this->seats)
            ->sortBy([['floor', 'asc'], ['row', 'asc'], ['column', 'asc']])
            ->values();

        $next = 1;
        $used = collect();

        foreach ($ordered as $i => $seat) {
            if (in_array($seat['id'] ?? null, $soldIds, true)) {
                $used->push((string) $seat['seat_number']);
            }
        }

        foreach ($ordered as $i => $seat) {
            if (in_array($seat['id'] ?? null, $soldIds, true)) {
                continue; // El vendido conserva su número.
            }

            while ($used->contains((string) $next)) {
                $next++;
            }

            $this->seats[$this->seatIndex($seat)]['seat_number'] = (string) $next;
            $next++;
        }

        Notification::make()
            ->title('Numeración reordenada')
            ->body('Los asientos quedaron numerados de adelante hacia atrás. Recién se aplica cuando presiones "Guardar layout".')
            ->info()
            ->send();
    }

    private function seatIndex(array $seat): int
    {
        foreach ($this->seats as $i => $s) {
            if ($s === $seat) {
                return $i;
            }
        }

        return -1;
    }

    // ─────────────────────────── Copiar de otro colectivo ───────────────────────────

    /** Copia el layout de otro colectivo: origen elegido desde una modal. */
    public $copyFromBusId = null;

    public function copyFromAnotherBus(array $data): void
    {
        $sourceId = (int) ($data['source_bus_id'] ?? 0);

        if ($sourceId < 1 || $sourceId === (int) $this->record->getKey()) {
            return;
        }

        $source = Bus::find($sourceId);
        if (! $source) {
            Notification::make()->title('Colectivo de origen no encontrado')->danger()->send();

            return;
        }

        try {
            $this->service()->copyLayoutFrom($this->getRecord(), $source);
            $this->loadLayout();

            Notification::make()
                ->title('Layout copiado')
                ->body("El layout de \"{$source->name}\" se copió a este colectivo.")
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('No se pudo copiar el layout')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    // ─────────────────────────── Helpers ───────────────────────────

    private function seatHasTickets(int $seatId): bool
    {
        $seat = Seat::find($seatId);

        return $seat ? $seat->tickets()->count() > 0 : false;
    }
}
