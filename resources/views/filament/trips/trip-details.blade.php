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
                        <strong>Ruta:</strong>
                        <span
                            class="text-fuchsia-600 dark:text-fuchsia-400">{{ $trip->route?->name ?? 'No especificada' }}</span>
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
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1 font-semibold">{{ $trip->total_passengers }}
                    PASAJEROS</div>
            </div>
        </div>
    </div>

    <!-- Tabla de pasajeros -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Pasajeros del Viaje ({{ $passengers->count() }})</h3>
        </div>

        @if ($passengers->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">DNI</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Teléfono</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Asiento</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Destino</th>
                            <!-- <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Diferido</th> -->
                            <!-- <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Precio</th> -->
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($passengers as $passenger)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm">
                                    @if ($passenger['type'] === 'adult')
                                        <span
                                            class="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-lg">Adulto</span>
                                    @else
                                        <span
                                            class="inline-block px-2 py-1 text-xs bg-green-100 text-green-800 rounded-lg">Niño</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    {{ $passenger['name'] }}
                                    @if ($passenger['type'] === 'child' && isset($passenger['parent_name']))
                                        <div class="text-xs text-gray-500 mt-1">Viaja con:
                                            {{ $passenger['parent_name'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm">{{ $passenger['dni'] }}</td>
                                <td class="px-4 py-2 text-sm">
                                    {{ $passenger['phone'] == 'N/A' ? '-' : $passenger['phone'] }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span
                                        class="inline-block px-2 py-1 text-xs bg-fuchsia-100 text-fuchsia-800 rounded-lg">{{ is_numeric($passenger['seat_number']) ? $passenger['seat_number'] : 'No ocupa' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    <span
                                        class="inline-block px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded-lg">{{ $passenger['origin'] }}</span>
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    <span
                                        class="inline-block px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded-lg">{{ $passenger['destination'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-8">
                <p class="text-gray-500">No hay pasajeros registrados para este viaje.</p>
            </div>
        @endif
    </div>
</div>
