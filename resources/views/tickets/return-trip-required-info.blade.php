@php
    $tripId = $tripId ?? null;
    $trip = $trip ?? null;
    $availableSeats = $availableSeats ?? 0;
    $requiredSeats = $requiredSeats ?? 0;
@endphp

@if (blank($tripId))
    <div class="rounded-lg bg-warning-50 dark:bg-warning-900/20 p-4 border border-warning-200 dark:border-warning-800">
        <p class="text-sm text-warning-800 dark:text-warning-200">
            ⚠ Por favor, regrese al primer paso y busque un viaje de vuelta disponible antes de continuar.
        </p>
    </div>
@elseif (!$trip)
    <div class="rounded-lg bg-danger-50 dark:bg-danger-900/20 p-4 border border-danger-200 dark:border-danger-800">
        <p class="text-sm text-danger-800 dark:text-danger-200">
            ❌ El viaje de vuelta seleccionado no existe. Por favor, regrese y busque un viaje nuevo.
        </p>
    </div>
@elseif ($availableSeats < $requiredSeats)
    <div class="rounded-lg bg-warning-50 dark:bg-warning-900/20 p-4 border border-warning-200 dark:border-warning-800">
        <p class="text-sm text-warning-800 dark:text-warning-200">
            ⚠ El viaje de vuelta tiene {{ $availableSeats }} asiento(s) disponible(s), pero necesita
            {{ $requiredSeats }}.
        </p>
    </div>
@endif
