@php
    $tripId = $trip_id ?? null;
    $requiredSeats = (int) ($passengers_count ?? 0);

    $trip = $trip ?? ($tripId ? \App\Models\Trip::find($tripId) : null);
    $layoutData = $trip ? $trip->getFullLayoutData() : null;

    // Obtener pisos disponibles
    $floors = $layoutData['seats'] ?? [];
@endphp


<div x-data="{
    selected: @entangle('data.' . $fieldId),
    required: {{ (int) $passengers_count }},

    init() {
        if (!Array.isArray(this.selected)) {
            this.selected = [];
        }
    },

    toggleSeat(seatId) {
        if (!Array.isArray(this.selected)) {
            this.selected = [];
        }

        if (this.selected.includes(seatId)) {
            this.selected = this.selected.filter(id => id !== seatId);
            return;
        }

        if (this.selected.length >= this.required) {
            return;
        }

        this.selected = [...this.selected, seatId];
    },

    isSelected(seatId) {
        return Array.isArray(this.selected) && this.selected.includes(seatId);
    }
}" class="seat-selector-container grid grid-cols-1 gap-4">
    @if (!$trip || !$layoutData)
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 w-full">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Primero debe buscar y seleccionar un viaje disponible.
            </p>
        </div>
    @else
        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 w-full">
            <div class="flex flex-wrap gap-4 w-full">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded border border-gray-400"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Disponible</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-purple-500 rounded border border-purple-600"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Seleccionado</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-red-500 rounded border border-red-600"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Ocupado</span>
                </div>
            </div>
            @if ($requiredSeats > 0)
                <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    <span x-text="`Asientos seleccionados: ${selected.length} de ${required}`"></span>
                </div>
            @endif
        </div>

        <h3 class="text-lg font-semibold text-primary text-fuchsia-600">Seleccionar asientos de
            {{ strpos($fieldId, 'return') !== false ? 'vuelta' : 'ida' }}</h3>

        <!-- Contenedor de pisos con flexbox para cambiar orden -->
        <div class="flex flex-col-reverse md:flex-row-reverse md:justify-center items-end *:w-full gap-4">


            @foreach ($floors as $floorKey => $seats)
                @php
                    $floorNumber = str_replace('floor_', '', $floorKey);
                    $floorName =
                        $floorNumber == '1'
                            ? 'Primer Piso'
                            : ($floorNumber == '2'
                                ? 'Segundo Piso'
                                : "Piso {$floorNumber}");
                @endphp

                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        {{ $floorName }} ({{ count($seats) }} asientos)
                    </h3>

                    @php
                        // Obtener áreas especiales para este piso
                        $areas = $layoutData['areas'][$floorKey] ?? [];

                        // Organizar asientos por fila y columna
                        $seatsByRow = [];
                        $minRow = PHP_INT_MAX;
                        $maxRow = 0;
                        $maxColumn = 0;

                        foreach ($seats as $seat) {
                            $row = (int) ($seat['row'] ?? 0);
                            $column = (int) ($seat['column'] ?? 0);
                            if ($row > 0) {
                                $minRow = min($minRow, $row);
                                $maxRow = max($maxRow, $row);
                            }
                            $maxColumn = max($maxColumn, $column);

                            if (!isset($seatsByRow[$row])) {
                                $seatsByRow[$row] = [];
                            }
                            $seatsByRow[$row][$column] = $seat;
                        }

                        // Incluir filas de áreas en el cálculo de maxRow y minRow
                        foreach ($areas as $area) {
                            $areaRowStart = (int) ($area['row_start'] ?? 0);
                            $areaRowEnd = (int) ($area['row_end'] ?? $areaRowStart);
                            if ($areaRowStart > 0) {
                                $minRow = min($minRow, $areaRowStart);
                                $maxRow = max($maxRow, $areaRowEnd);
                            }
                            $areaColStart = (int) ($area['column_start'] ?? 0);
                            $areaColEnd = (int) ($area['column_end'] ?? $areaColStart);
                            $maxColumn = max($maxColumn, $areaColEnd);
                        }

                        // Normalizar: si las filas empiezan en 1 (o más), ajustar para que empiecen en 0 para el grid
                        $rowOffset = 0;
                        if ($minRow !== PHP_INT_MAX && $minRow > 0) {
                            $rowOffset = $minRow;
                            $adjustedSeatsByRow = [];
                            foreach ($seatsByRow as $row => $rowSeats) {
                                $adjustedRow = $row - $rowOffset;
                                $adjustedSeatsByRow[$adjustedRow] = $rowSeats;
                            }
                            $seatsByRow = $adjustedSeatsByRow;
                            $maxRow = $maxRow - $rowOffset;
                        } else {
                            $minRow = 0;
                        }

                        // Si no hay configuración de filas/columnas, usar layout simple por número
                        if ($maxRow === 0 && $maxColumn === 0 && count($seats) > 0) {
                            // Layout simple: mostrar asientos en filas de 4
                            $seatsPerRow = 4;
                            $currentRow = 0;
                            $currentCol = 0;
                            $rowOffset = 0; // Reset para layout simple

                            $seatsByRow = [];
                            foreach ($seats as $seat) {
                                if (!isset($seatsByRow[$currentRow])) {
                                    $seatsByRow[$currentRow] = [];
                                }
                                $seatsByRow[$currentRow][$currentCol] = $seat;
                                $currentCol++;
                                if ($currentCol >= $seatsPerRow) {
                                    $currentCol = 0;
                                    $currentRow++;
                                }
                            }
                            $maxRow = $currentRow;
                            $maxColumn = $seatsPerRow - 1;
                        }
                    @endphp

                    @php
                        // Asegurar que rowOffset esté definido para el loop
                        $rowOffset = $rowOffset ?? 0;
                    @endphp

                    <div
                        class="flex justify-center items-center bg-white dark:bg-gray-900 p-4 rounded-lg border border-gray-200 dark:border-gray-700 overflow-x-auto">
                        <div class="seat-grid inline-grid"
                            style="--grid-columns: {{ max($maxColumn + 1, 4) }}; grid-template-columns: repeat({{ max($maxColumn + 1, 4) }}, 45px); gap: 4px; width: fit-content;">
                            @for ($row = 0; $row <= $maxRow; $row++)
                                @php
                                    $rowSeats = $seatsByRow[$row] ?? [];
                                @endphp

                                @for ($col = 0; $col <= $maxColumn; $col++)
                                    @php
                                        $seat = $rowSeats[$col] ?? null;
                                        $isArea = false;
                                        $areaInfo = null;

                                        // Verificar si esta posición es un área especial (ajustar row para comparación)
                                        $actualRow = $row + $rowOffset;
                                        foreach ($areas as $area) {
                                            $areaRowStart = (int) ($area['row_start'] ?? 0);
                                            $areaRowEnd = (int) ($area['row_end'] ?? $areaRowStart);
                                            $areaColStart = (int) ($area['column_start'] ?? 0);
                                            $areaColEnd = (int) ($area['column_end'] ?? $areaColStart);

                                            if (
                                                $areaRowStart <= $actualRow &&
                                                $areaRowEnd >= $actualRow &&
                                                $areaColStart <= $col &&
                                                $areaColEnd >= $col
                                            ) {
                                                $isArea = true;
                                                $areaInfo = $area;
                                                break;
                                            }
                                        }
                                    @endphp

                                    @if ($seat)
                                        @php
                                            $seatId = $seat['id'];
                                            $seatNumber = $seat['seat_number'];
                                            $isOccupied = $seat['is_occupied'] ?? false;
                                        @endphp

                                        <button type="button" @click="toggleSeat({{ $seatId }})"
                                            @if ($isOccupied) disabled @endif
                                            :class="{
                                                'bg-gray-300 dark:bg-gray-600 border-gray-400 hover:bg-gray-400 dark:hover:bg-gray-500':
                                                    !isSelected({{ $seatId }}) && !@js($isOccupied),
                                            
                                                'bg-purple-500 border-purple-600 text-white hover:bg-purple-600': isSelected(
                                                    {{ $seatId }}),
                                            
                                                'bg-red-500 border-red-600 cursor-not-allowed opacity-75': @js($isOccupied)
                                            }"
                                            class="seat-button rounded border-2 flex items-center justify-center text-xs font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-200 disabled:opacity-75 disabled:cursor-not-allowed"
                                            title="{{ $isOccupied ? 'Asiento ocupado' : 'Asiento ' . $seatNumber }}">
                                            <span>{{ $seatNumber }}</span>
                                        </button>
                                    @elseif($isArea && $areaInfo)
                                        @php
                                            // Renderizar el área en cada celda que ocupa
                                            // Solo mostrar el label en la primera celda (esquina superior izquierda)
                                            $isFirstCellOfArea =
                                                $actualRow == (int) ($areaInfo['row_start'] ?? 0) &&
                                                $col == (int) ($areaInfo['column_start'] ?? 0);
                                            $areaType = $areaInfo['area_type'] ?? '';
                                            $isPasillo = $areaType === 'pasillo';
                                        @endphp
                                        @if ($isPasillo)
                                            {{-- Pasillo blanco --}}
                                            <div
                                                class="area-cell flex items-center justify-center p-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded">
                                            </div>
                                        @else
                                            {{-- Otras áreas especiales (BAÑO, CAFETERA, etc.) --}}
                                            <div
                                                class="area-cell flex items-center justify-center p-1 bg-amber-100 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 rounded text-xs font-semibold text-amber-800 dark:text-amber-200 text-center">
                                                @if ($isFirstCellOfArea)
                                                    {{ $areaInfo['label'] }}
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <div class="empty-cell"></div>
                                    @endif
                                @endfor
                            @endfor
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div x-show="selected.length < required"
            class="w-full mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                Debe seleccionar <span x-text="required - selected.length"></span> asiento(s) más.
            </p>
        </div>
    @endif
</div>


<style>
    .seat-selector-container {
        width: 100%;
    }

    .seat-selector-container [x-cloak] {
        display: none !important;
    }

    .seat-grid {
        width: fit-content;
        display: inline-grid;
    }

    .seat-button {
        width: 45px;
        height: 32px;
        min-width: 45px;
        max-width: 45px;
        font-size: 0.7rem;
        padding: 0.125rem;
        line-height: 1;
    }

    .empty-cell {
        width: 45px;
        height: 32px;
        min-width: 45px;
    }

    .area-cell {
        width: 45px;
        min-height: 32px;
        font-size: 0.65rem;
        padding: 0.25rem 0.125rem;
        line-height: 1.2;
    }

    @media (max-width: 768px) {
        .seat-grid {
            grid-template-columns: repeat(var(--grid-columns, 4), 40px) !important;
            width: max-content;
            margin-left: auto;
            margin-right: auto;
        }

        .seat-button {
            width: 40px;
            height: 28px;
            min-width: 40px;
            max-width: 40px;
            font-size: 0.65rem;
        }

        .empty-cell {
            width: 40px;
            height: 28px;
            min-width: 40px;
        }

        .area-cell {
            width: 40px;
            min-height: 28px;
            font-size: 0.6rem;
            padding: 0.2rem 0.1rem;
        }
    }

    @media (max-width: 640px) {
        .seat-grid {
            grid-template-columns: repeat(var(--grid-columns, 4), 35px) !important;
        }

        .seat-button {
            width: 35px;
            height: 24px;
            min-width: 35px;
            max-width: 35px;
            font-size: 0.6rem;
        }

        .empty-cell {
            width: 35px;
            height: 24px;
            min-width: 35px;
        }

        .area-cell {
            width: 35px;
            min-height: 24px;
            font-size: 0.55rem;
        }
    }
</style>
