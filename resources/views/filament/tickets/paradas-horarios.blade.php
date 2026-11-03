<div class="space-y-4 grid md:grid-cols-2 gap-4">
    @if ($rutas->isEmpty())
        <div class="rounded-xl border p-5 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700">
            <p class="text-gray-500 dark:text-gray-400">
                El colectivo no tiene rutas activas con horarios.
            </p>
        </div>
    @endif

    @foreach ($rutas as $route)
        @php
            $paradas = $route->stops->filter(fn ($stop) => $stop->location?->is_active !== false);
            $horarios = $route->schedules;
        @endphp

        {{-- Tarjeta por ruta, mismo estilo que trip-details --}}
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div
                class="px-6 py-2 border-b border-gray-200 dark:border-gray-700 flex flex-wrap justify-between items-center gap-2">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    <span class="text-fuchsia-600 dark:text-fuchsia-400">
                        {{ $paradas->first()?->location?->name ?? 'Origen' }}
                    </span>
                    <span class="mx-1">→</span>
                    <span class="text-fuchsia-600 dark:text-fuchsia-400">
                        {{ $paradas->last()?->location?->name ?? 'Destino' }}
                    </span>
                </h3>

                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase">
                    {{ $horarios->count() }} horario(s) activo(s)
                </div>
            </div>

            @if ($horarios->isEmpty() || $paradas->isEmpty())
                <div class="px-6 py-8">
                    <p class="text-gray-500 dark:text-gray-400">No hay horarios o paradas activas para esta ruta.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Parada</th>
                                @foreach ($horarios as $schedule)
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                        {{ $schedule->display_name }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($paradas as $stop)
                                @php
                                    $horaParada = $loop->index + 1;
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-2 text-sm whitespace-nowrap">
                                        <span
                                            class="inline-block px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 rounded-lg">
                                            {{ $horaParada }}
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $stop->location?->name }}
                                        </span>
                                    </td>
                                    @foreach ($horarios as $schedule)
                                        @php
                                            $hora = $stop->getDepartureTimeForSchedule($schedule);
                                        @endphp
                                        <td class="px-4 py-2 text-center text-sm text-gray-900 dark:text-gray-100 tabular-nums">
                                            {{ $hora?->format('H:i') ?? '--:--' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Las horas indican la salida del colectivo en cada parada, calculada a partir de la hora de salida de cada
        horario.
    </p>
</div>
