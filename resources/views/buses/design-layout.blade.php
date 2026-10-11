@php
    /** @var \App\Filament\Resources\Buses\Pages\DesignBusLayout $this */
    $bus = $this->getRecord();
    $floorCount = max(1, (int) $bus->floors);

    // Leyenda de zonas especiales (color → etiqueta amigable).
    $areaLegend = [
        ['type' => 'bathroom', 'label' => 'Baño', 'chip' => 'bg-sky-100 border-sky-300 text-sky-800'],
        ['type' => 'cafeteria', 'label' => 'Cafetera', 'chip' => 'bg-amber-100 border-amber-300 text-amber-800'],
        ['type' => 'luggage', 'label' => 'Equipaje', 'chip' => 'bg-purple-100 border-purple-300 text-purple-800'],
        ['type' => 'driver', 'label' => 'Conductor', 'chip' => 'bg-gray-200 border-gray-400 text-gray-700'],
        ['type' => 'pasillo', 'label' => 'Pasillo', 'chip' => 'bg-white border-gray-300 text-gray-500'],
        ['type' => 'other', 'label' => 'Otra', 'chip' => 'bg-pink-100 border-pink-300 text-pink-800'],
    ];

    $floorName = fn (int $f): string => $f === 1 ? 'Planta baja' : ($f === 2 ? 'Planta alta' : "Piso {$f}");
@endphp

<x-filament-panels::page>
    <div
        x-data="busLayoutEditor()"
        @keydown.escape.window="cancelDrag()"
        @mouseup.window="endDragOnWindow($event)"
        class="flex flex-col gap-4"
    >

        {{-- Selector de piso (sólo multi piso) --}}
        @if ($floorCount > 1)
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Piso:</span>
                <div class="flex rounded-lg overflow-hidden border border-gray-300 dark:border-gray-700">
                    @foreach (range(1, $floorCount) as $f)
                        <button
                            type="button"
                            wire:click="setFloor({{ $f }})"
                            wire:loading.attr="disabled"
                            class="px-4 py-1.5 text-sm font-medium transition
                                {{ $f === $this->floor
                                    ? 'bg-primary-500 text-white'
                                    : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                        >{{ $floorName($f) }}</button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Modo de edición (manejado 100 % en el navegador: sin carreras de estados) --}}
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Modo:</span>
            <div class="flex rounded-lg overflow-hidden border border-gray-300 dark:border-gray-700">
                <button
                    type="button"
                    x-on:click="setMode('seat')"
                    :class="mode === 'seat'
                        ? 'bg-primary-500 text-white'
                        : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300'"
                    class="px-4 py-1.5 text-sm font-medium transition"
                >Asientos</button>
                <button
                    type="button"
                    x-on:click="setMode('area')"
                    :class="mode === 'area'
                        ? 'bg-indigo-500 text-white'
                        : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300'"
                    class="px-4 py-1.5 text-sm font-medium transition"
                >Zonas</button>
            </div>

            <span class="text-xs text-gray-500 dark:text-gray-400" x-show="mode === 'seat'">
                Clic en una celda vacía para agregar un asiento · Clic en un asiento para quitarlo
            </span>
            <span class="text-xs text-indigo-600 dark:text-indigo-400" x-show="mode === 'area'" x-cloak>
                Arrastrá sobre la grilla para marcar la zona · Clic en una zona para quitarla
            </span>

            <select
                wire:model.live="pendingAreaType"
                x-show="mode === 'area'"
                x-cloak
                title="Tipo de zona que va a marcar"
                class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"
            >
                <option value="bathroom">Baño</option>
                <option value="cafeteria">Cafetera</option>
                <option value="luggage">Guardaequipaje</option>
                <option value="driver">Cabina conductor</option>
                <option value="pasillo">Pasillo</option>
                <option value="other">Otra</option>
            </select>
        </div>

        {{-- Leyenda de colores --}}
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Colores:</span>
            @foreach ($areaLegend as $item)
                <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                    <span class="inline-block size-3 rounded border {{ $item['chip'] }}"></span>
                    {{ $item['label'] }}
                </span>
            @endforeach
        </div>

        {{-- Grilla --}}
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 overflow-x-auto">
            <div
                class="inline-grid select-none"
                style="grid-template-columns: repeat({{ $this->gridCols }}, 48px); gap: 4px; width: fit-content"
            >
                @for ($row = 0; $row <= $this->gridRows; $row++)
                    @for ($col = 0; $col <= $this->gridCols; $col++)
                        @php
                            $seat = $this->seatAt($row, $col);
                            $area = $seat ? null : $this->areaAt($row, $col);
                        @endphp

                        @if ($seat)
                            <button
                                type="button"
                                wire:key="seat-{{ $seat['floor'] }}-{{ $row }}-{{ $col }}"
                                title="Asiento {{ $seat['seat_number'] }}{{ ($seat['tickets_count'] ?? 0) > 0 ? ' — tiene boletos vendidos (no se puede quitar)' : '' }}{{ $seat['is_active'] ? '' : ' — inactivo' }}"
                                wire:click="removeSeatAt({{ $row }}, {{ $col }})"
                                wire:loading.attr="disabled"
                                class="rounded border text-xs font-semibold h-9 flex items-center justify-center transition
                                    {{ ($seat['tickets_count'] ?? 0) > 0
                                        ? 'bg-red-100 border-red-300 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-200'
                                        : ($seat['is_active']
                                            ? 'bg-green-100 border-green-300 text-green-800 hover:bg-green-200 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200'
                                            : 'bg-gray-100 border-gray-300 text-gray-400 line-through opacity-60 dark:bg-gray-800 dark:border-gray-700') }}"
                            >{{ $seat['seat_number'] }}</button>
                        @elseif($area)
                            <button
                                type="button"
                                wire:key="area-{{ $area['type'] }}-{{ $row }}-{{ $col }}"
                                title="{{ $area['label'] }} — clic para quitar"
                                wire:click="removeAreaAt({{ $row }}, {{ $col }})"
                                wire:loading.attr="disabled"
                                class="rounded border text-[0.6rem] font-semibold h-9 flex items-center justify-center transition
                                    {{ match ($area['type']) {
                                        'bathroom' => 'bg-sky-100 border-sky-300 text-sky-800 dark:bg-sky-900/30 dark:border-sky-700 dark:text-sky-200',
                                        'cafeteria' => 'bg-amber-100 border-amber-300 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200',
                                        'luggage' => 'bg-purple-100 border-purple-300 text-purple-800 dark:bg-purple-900/30 dark:border-purple-700 dark:text-purple-200',
                                        'driver' => 'bg-gray-200 border-gray-400 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                                        'pasillo' => 'bg-white border-gray-100 dark:bg-gray-900 dark:border-gray-800',
                                        default => 'bg-pink-100 border-pink-300 text-pink-800 dark:bg-pink-900/30 dark:border-pink-700 dark:text-pink-200',
                                    } }}"
                                :class="isDragging() ? 'opacity-60' : ''"
                            >{{ $area['label'] }}</button>
                        @else
                            {{-- Celda vacía: callada y callada-hover. El drag del área es 100 % navegador --}}
                            <div
                                wire:key="cell-{{ $this->floor }}-{{ $row }}-{{ $col }}"
                                title="{{ 'Zona libre' }}"
                                @mousedown.prevent="startDrag({{ $row }}, {{ $col }})"
                                @mouseenter="extendDrag({{ $row }}, {{ $col }})"
                                @click="addSeatOrNothing({{ $row }}, {{ $col }})"
                                :class="cellPreviewClass({{ $row }}, {{ $col }})"
                                class="h-9 rounded cursor-pointer transition-colors"
                            ></div>
                        @endif
                    @endfor
                @endfor
            </div>

            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                {{ $floorName($this->floor) }} ·
                {{ count($this->seatsForFloor($this->floor)) }} asientos ·
                {{ count($this->areasForFloor($this->floor)) }}
                {{ count($this->areasForFloor($this->floor)) === 1 ? 'zona especial' : 'zonas especiales' }}
                en este piso
            </p>
        </div>

        {{-- Vista previa (como viaja el pasajero) --}}
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-2">
                <button
                    type="button"
                    x-on:click="showPreview = ! showPreview"
                    class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                >
                    <span x-text="showPreview ? 'Ocultar vista previa del colectivo' : 'Ver cómo viajan los pasajeros'"></span>
                </button>
            </div>

            <div x-show="showPreview" x-cloak x-transition class="mt-4 overflow-x-auto">
                <div class="text-[0.65rem] uppercase tracking-wide text-gray-400 mb-2">Adelante</div>
                <div
                    class="inline-grid gap-1"
                    style="grid-template-columns: repeat({{ max(1, $this->gridCols) }}, 34px)"
                >
                    @for ($row = 0; $row <= $this->gridRows; $row++)
                        @for ($col = 0; $col <= $this->gridCols; $col++)
                            @php
                                $seat = $this->seatAt($row, $col);
                                $area = $seat ? null : $this->areaAt($row, $col);
                            @endphp
                            @if ($seat)
                                <div
                                    title="Asiento {{ $seat['seat_number'] }} ({{ $floorName((int) $seat['floor']) }})"
                                    class="flex items-center justify-center text-[0.55rem] font-semibold rounded
                                        {{ $seat['is_active']
                                            ? 'bg-green-100 border border-green-300 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200'
                                            : 'bg-gray-100 border border-gray-300 text-gray-400 line-through dark:bg-gray-800 dark:border-gray-700' }}"
                                    style="height: 24px"
                                >{{ $seat['seat_number'] }}</div>
                            @elseif ($area)
                                <div
                                    title="{{ $area['label'] }} ({{ $floorName($this->floor) }})"
                                    class="rounded border text-[0.5rem] font-semibold flex items-center justify-center
                                        {{ match ($area['type']) {
                                            'bathroom' => 'bg-sky-100 border-sky-300 text-sky-800',
                                            'cafeteria' => 'bg-amber-100 border-amber-300 text-amber-800',
                                            'luggage' => 'bg-purple-100 border-purple-300 text-purple-800',
                                            'driver' => 'bg-gray-200 border-gray-400 text-gray-700',
                                            'pasillo' => 'bg-white border-gray-200 text-gray-500',
                                            default => 'bg-pink-100 border-pink-300 text-pink-800',
                                        } }}"
                                    style="height: 24px"
                                >{{ $area['label'] }}</div>
                            @else
                                <div style="height: 24px"></div>
                            @endif
                        @endfor
                    @endfor
                </div>
                <div class="text-[0.65rem] uppercase tracking-wide text-gray-400 mt-2">Atrás</div>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="save"
                wire:loading.attr="disabled"
                class="fi-btn fi-btn-color-success fi-btn-size-md rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
            >Guardar layout</button>
            <button
                type="button"
                wire:click="discard"
                wire:loading.attr="disabled"
                class="fi-btn rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
            >Descartar cambios</button>

            {{-- Renumerar asientos (directo en BD: los vendidos conservan su número) --}}
            <button
                type="button"
                wire:click="renumber"
                wire:loading.attr="disabled"
                wire:confirm="¿Renumerar los asientos de TODOS los pisos de adelante hacia atrás? Los asientos con boletos vendidos conservan su número."
                class="fi-btn rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                title="Ordena los números de asiento en orden lógico"
            >Reordenar numeración</button>

            {{-- Guardar el diseño guardado como plantilla reutilizable --}}
            <div x-data="{ open: false }" class="contents">
                <button
                    type="button"
                    x-on:click="open = true"
                    class="fi-btn rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                >Guardar como plantilla…</button>

                <div
                    x-show="open"
                    x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50"
                x-on:click.self="open = false"
                @keydown.escape.window="open = false"
            >
                <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-900">
                    <h3 class="text-lg font-semibold mb-1 text-gray-900 dark:text-white">Guardar como plantilla</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Se guarda el diseño YA GUARDADO de este colectivo (asientos + zonas) para reutilizarlo al crear otros colectivos.
                    </p>
                    <form wire:submit="saveAsTemplate">
                        <label class="block text-sm font-medium mb-1" for="template-name">Nombre de la plantilla</label>
                        <input
                            id="template-name"
                            type="text"
                            wire:model="templateName"
                            required
                            maxlength="120"
                            placeholder="Ej: Colectivo 2 pisos con baño al fondo"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 mb-4"
                        />
                        @error('templateName') <p class="text-danger text-sm mb-2">{{ $message }}</p> @enderror
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                x-on:click="open = false"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200"
                            >Cancelar</button>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="rounded-lg bg-primary-500 px-4 py-2 text-sm font-medium text-white hover:bg-primary-600"
                            >Guardar plantilla</button>
                        </div>
                    </form>
                </div>
            </div>

            <a
                href="{{ \App\Filament\Resources\Buses\BusResource::getUrl('index') }}"
                class="ml-auto rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
            >Volver a colectivos</a>
        </div>
    </div>

    @script
        <script>
            Alpine.data('busLayoutEditor', () => ({
                mode: 'seat',
                showPreview: false,

                dragging: false,
                dragStart: null,
                dragEnd: null,

                setMode(mode) {
                    this.mode = mode;
                    this.cancelDrag();
                },

                // ── Dibujo de zonas por arrastre (100 % navegador) ──
                startDrag(row, col) {
                    if (this.mode !== 'area') {
                        return;
                    }
                    this.dragging = true;
                    this.dragStart = { row, col };
                    this.dragEnd = { row, col };
                },

                extendDrag(row, col) {
                    if (!this.dragging) {
                        return;
                    }
                    this.dragEnd = { row, col };
                },

                // Sueltas el botón FUERA de la grilla: cerrar el drag acá.
                endDragOnWindow() {
                    if (!this.dragging) {
                        return;
                    }
                    this.finishDrag();
                },

                // Sueltas dentro de la grilla: mandamos las 2 esquinas en UN solo ida y vuelta.
                endDrag(row, col) {
                    if (!this.dragging) {
                        return;
                    }
                    this.dragEnd = { row, col };
                    this.finishDrag();
                },

                finishDrag() {
                    this.dragging = false;

                    const start = this.dragStart;
                    const end = this.dragEnd;
                    this.dragStart = null;
                    this.dragEnd = null;

                    if (!start || !end) {
                        return;
                    }

                    const tl = {
                        row: Math.min(start.row, end.row),
                        col: Math.min(start.col, end.col),
                    };
                    const br = {
                        row: Math.max(start.row, end.row),
                        col: Math.max(start.col, end.col),
                    };

                    this.$wire.completeRect(tl.row, tl.col, br.row, br.col);
                },

                addSeatOrNothing(row, col) {
                    // En modo asiento, el clic agrega; en modo zonas el clic ya perteneció al drag.
                    if (this.mode !== 'seat') {
                        return;
                    }
                    this.$wire.addSeatAt(row, col);
                },

                cancelDrag() {
                    this.dragging = false;
                    this.dragStart = null;
                    this.dragEnd = null;
                },

                isDragging() {
                    return this.dragging;
                },

                cellPreviewClass(row, col) {
                    const base = 'border';
                    const idle = `${base} border-dashed border-gray-300 hover:border-gray-400`;
                    const darkIdle = 'dark:border-gray-700';

                    if (!this.dragging || !this.dragStart || !this.dragEnd) {
                        return `${idle} ${darkIdle}`;
                    }

                    const inside =
                        row >= Math.min(this.dragStart.row, this.dragEnd.row) &&
                        row <= Math.max(this.dragStart.row, this.dragEnd.row) &&
                        col >= Math.min(this.dragStart.col, this.dragEnd.col) &&
                        col <= Math.max(this.dragStart.col, this.dragEnd.col);

                    return inside
                        ? `${base} border-2 border-indigo-500 bg-indigo-500/20`
                        : `${idle} ${darkIdle}`;
                },
            }));
        </script>
    @endscript
</x-filament-panels::page>
