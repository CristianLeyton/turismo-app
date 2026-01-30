<div class="space-y-4">
    <!-- Encabezado principal del viaje -->
    <div class="rounded-xl border p-5 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700">
        <div class="flex flex-col md:flex-row md:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    <span class="text-fuchsia-600">
                        @php
                            $stops = $trip->route->stops()->with('location')->get();
                            $firstStop = $stops->first();
                            $lastStop = $stops->last();
                        @endphp

                        {{-- Primera parada --}}
                        {{ $firstStop?->location->name ?? 'Origen' }}
                    </span>
                    →
                    <span class="text-fuchsia-600">
                        {{ $lastStop?->location->name ?? 'Destino' }}
                    </span>
                </h2>

                <div class="mt-2 space-y-1">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Fecha:</strong> {{ $trip->trip_date->format('d/m/Y') }}
                        <span class="mx-2">•</span>
                        {{ $trip->departure_time?->format('H:i') ?? '--:--' }} →
                        {{ $trip->arrival_time?->format('H:i') ?? '--:--' }}
                    </div>

                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Colectivo:</strong> {{ $trip->bus?->name ?? 'No especificado' }}
                    </div>
                </div>
            </div>

            <div class="text-left md:text-right">
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase">Asientos vendidos</div>
                <div class="text-3xl font-bold text-fuchsia-600 dark:text-fuchsia-400">{{ $trip->occupiedSeatsCount() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de pasajeros -->
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-2 border-b border-gray-200 dark:border-gray-700 flex justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pasajeros</h3>
            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 text-right">({{ $passengers->count() }})
            </div>
        </div>

        @if ($passengers->count() > 0)
            <div class="overflow-x-scroll max-w-[83dvw] sm:max-w-[90dvw] md:max-w-full md:overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Boleto N°</th>
                            <th
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Nombre</th>
                            <th
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                DNI</th>
                            <th
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Teléfono</th>
                            <th
                                class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Asiento</th>
                            <th
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Origen</th>
                            <th
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Destino</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($passengers as $passenger)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-2 text-sm text-center">
                                    <span
                                        class="inline-block px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 rounded-lg">
                                        {{ $passenger['ticket_id'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $passenger['name'] }}
                                    @if ($passenger['type'] === 'child' && isset($passenger['parent_name']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Viaja con:
                                            {{ $passenger['parent_name'] }}</div>
                                    @endif
                                    @if ($passenger['type'] === 'pet' && isset($passenger['parent_name']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Mascota de:
                                            {{ $passenger['parent_name'] }}</div>
                                        @if (isset($passenger['pet_count']) && $passenger['pet_count'] > 1)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                ({{ $passenger['pet_count'] }} mascotas)
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $passenger['dni'] == 'N/A' ? '-' : $passenger['dni'] }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $passenger['phone'] == 'N/A' ? '-' : $passenger['phone'] }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 text-center">

                                    <span
                                        class="inline-block px-2 py-1 text-xs bg-fuchsia-100 dark:bg-fuchsia-900/30 text-fuchsia-800 dark:text-fuchsia-300 rounded-lg">{{ is_numeric($passenger['seat_number']) ? $passenger['seat_number'] : 'No ocupa' }}
                                    </span>

                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                    <span
                                        class="inline-block px-2 py-1 text-xs bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300 rounded-lg">{{ $passenger['origin'] }}</span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                    <span
                                        class="inline-block px-2 py-1 text-xs bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300 rounded-lg">{{ $passenger['destination'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-8">
                <p class="text-gray-500 dark:text-gray-400">No hay pasajeros registrados para este viaje.</p>
            </div>
        @endif
    </div>
</div>
