@php
    $hasAllFields = $hasAllFields ?? false;
    $tripId = $tripId ?? null;
    $trip = $trip ?? null;
    $searchStatus = $searchStatus ?? null;
    $availableSeats = $availableSeats ?? null;
    $requiredSeats = $requiredSeats ?? 0;
@endphp

@if (!$hasAllFields)
    <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 p-4 border border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Complete la cantidad de pasajeros para buscar un viaje disponible.
        </p>
    </div>
@elseif ($tripId && $searchStatus === 'available' && $trip)
    <div class="rounded-lg bg-success-50 dark:bg-success-900/20 p-4 border border-success-200 dark:border-success-800">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-success-600 dark:text-success-400 mt-0.5 shrink-0" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold text-success-800 dark:text-success-200 mb-2">
                    ✓ Viaje encontrado y disponible
                </h3>
                <div class="text-sm text-success-700 dark:text-success-300 space-y-1">
                    <p><strong>Ruta:</strong> {{ $trip->route->name ?? 'N/A' }}</p>
                    <p><strong>Fecha:</strong> {{ $trip->trip_date->format('d/m/Y') }}</p>
                    <p><strong>Horario:</strong> {{ $trip->schedule->display_name ?? 'N/A' }}</p>
                    <p><strong>Asientos disponibles:</strong> <span class="font-semibold">{{ $availableSeats }}</span>
                        de {{ $trip->bus->seat_count ?? 'N/A' }}</p>
                </div>
                <p
                    class="text-xs text-success-600 dark:text-success-400 mt-3 pt-2 border-t border-success-200 dark:border-success-700">
                    Puede continuar al siguiente paso para seleccionar los asientos.
                </p>
            </div>
        </div>
    </div>
@elseif ($tripId && $searchStatus === 'insufficient_seats' && $trip)
    <div class="rounded-lg bg-warning-50 dark:bg-warning-900/20 p-4 border border-warning-200 dark:border-warning-800">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-warning-600 dark:text-warning-400 mt-0.5 shrink-0" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                </path>
            </svg>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold text-warning-800 dark:text-warning-200 mb-2">
                    ⚠ Asientos insuficientes
                </h3>
                <div class="text-sm text-warning-700 dark:text-warning-300 space-y-1">
                    <p><strong>Ruta:</strong> {{ $trip->route->name ?? 'N/A' }}</p>
                    <p><strong>Asientos disponibles:</strong> <span class="font-semibold">{{ $availableSeats }}</span>
                    </p>
                    <p><strong>Asientos requeridos:</strong> <span class="font-semibold">{{ $requiredSeats }}</span></p>
                    <p><strong>Faltan:</strong> <span
                            class="font-semibold">{{ $requiredSeats - $availableSeats }}</span> asiento(s)</p>
                </div>
                <p
                    class="text-xs text-warning-600 dark:text-warning-400 mt-3 pt-2 border-t border-warning-200 dark:border-warning-700">
                    No puede continuar hasta que haya suficientes asientos disponibles.
                </p>
            </div>
        </div>
    </div>
@endif
