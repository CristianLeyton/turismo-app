@php
    // Con observaciones activas, la columna Observación reemplaza a Teléfono
    // (que no se borra: queda listo para cuando la lista permita elegir
    // qué columnas mostrar). Apagadas, la tabla queda como antes.
    $showObservation = $showObservation ?? \App\Models\Setting::observacionesActivas();
    $showPhone = $showPhone ?? ! $showObservation;

    $stops = $stops ?? $trip->passengerListStops();
    $stopsByOrder = $stops->keyBy('order');

    // Grupos por parada de subida, en el orden que ya trae la lista: los de
    // origen fuera del recorrido quedan al final.
    $groups = $passengers->groupBy(fn (array $passenger): string => (string) ($passenger['origin_order'] ?? 'out'));

    // Índice por boleto para Alpine: el filtro muestra u oculta el boleto
    // completo, así buscar a un menor muestra también a su titular.
    $ticketIndex = $passengers->groupBy('ticket_id')->map(fn ($rows, $ticketId): array => [
        'id' => (int) $ticketId,
        'origin' => (string) $rows->first()['origin_location_id'],
        'destination' => (string) $rows->first()['destination_location_id'],
        'group' => (string) ($rows->first()['origin_order'] ?? 'out'),
        'search' => $rows->pluck('search')->implode(' '),
        'rows' => $rows->count(),
    ]);

    $blank = fn ($value): bool => blank($value) || $value === 'N/A';
    $th = 'px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400';
    $mobileDni = 'whitespace-nowrap text-xs text-gray-500 md:hidden dark:text-gray-400';
    $routeBadge = 'inline-block rounded-lg bg-amber-100 px-2 py-1 text-xs text-amber-800 dark:bg-amber-400/15 dark:text-amber-300';
@endphp

<div class="space-y-4" x-data="{
    origin: '',
    destination: '',
    search: '',
    {{-- Preferencia de cada usuario en su navegador: vale para todos los viajes. --}}
    groupByStop: $persist(true).as('trip-passengers-group-by-stop'),
    tickets: @js($ticketIndex),
    normalize(value) {
        return (value || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').toLowerCase().trim();
    },
    isVisible(id) {
        const ticket = this.tickets[id];
        if (! ticket) return true;
        if (this.origin !== '' && ticket.origin !== this.origin) return false;
        if (this.destination !== '' && ticket.destination !== this.destination) return false;
        const query = this.normalize(this.search);
        return query === '' || ticket.search.includes(query);
    },
    groupVisible(group) {
        return Object.values(this.tickets).some((ticket) => ticket.group === group && this.isVisible(ticket.id));
    },
    get visibleCount() {
        return Object.values(this.tickets).filter((ticket) => this.isVisible(ticket.id)).reduce((total, ticket) => total + ticket.rows, 0);
    },
    get filtering() {
        return this.origin !== '' || this.destination !== '' || this.search.trim() !== '';
    },
    clear() {
        this.origin = '';
        this.destination = '';
        this.search = '';
    },
}">
    {{-- Encabezado principal del viaje --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 md:flex-row md:justify-between">
            <div>
                {{-- autofocus + tabindex -1: el modal enfoca esto en vez del buscador. --}}
                <h2 tabindex="-1" autofocus class="text-2xl font-bold text-gray-900 outline-none dark:text-gray-100">
                    <span class="text-primary-600 dark:text-primary-400">{{ $stops->first()['name'] ?? 'Origen' }}</span>
                    →
                    <span class="text-primary-600 dark:text-primary-400">{{ $stops->last()['name'] ?? 'Destino' }}</span>
                </h2>

                <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <div>
                        <strong>Fecha:</strong> {{ $trip->trip_date->format('d/m/Y') }}
                        <span class="mx-2">•</span>
                        {{ $trip->departure_time?->format('H:i') ?? '--:--' }} →
                        {{ $trip->arrival_time?->format('H:i') ?? '--:--' }}
                    </div>
                    <div><strong>Colectivo:</strong> {{ $trip->bus?->name ?? 'No especificado' }}</div>
                </div>
            </div>

            <div class="text-left md:text-right">
                <div class="text-sm uppercase text-gray-500 dark:text-gray-400">Asientos vendidos</div>
                <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">{{ $trip->occupiedSeatsCount() }}</div>
            </div>
        </div>

    </div>

    {{-- Lista de pasajeros --}}
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-2 md:px-6 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pasajeros</h3>
            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                (<span x-show="filtering" x-cloak><span x-text="visibleCount"></span> de </span>{{ $passengers->count() }})
            </div>
        </div>

        @if ($passengers->count() > 0)
            {{-- Filtros: corren en el navegador, sin esperar al servidor. --}}
            <div class="grid grid-cols-1 gap-2 border-b border-gray-200 p-4 sm:grid-cols-3 dark:border-gray-700">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="search" x-model.debounce.150ms="search" autocomplete="off"
                        placeholder="Nombre, DNI, asiento o N° boleto" aria-label="Buscar por nombre, DNI, asiento o N° de boleto" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input.select x-model="origin" aria-label="Origen" data-filter="origin">
                        <option value="">Origen: todas</option>
                        @foreach ($stops as $stop)
                            <option value="{{ $stop['location_id'] }}">{{ $stop['order'] }}. {{ $stop['name'] }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input.select x-model="destination" aria-label="Destino" data-filter="destination">
                        <option value="">Destino: todas</option>
                        @foreach ($stops as $stop)
                            <option value="{{ $stop['location_id'] }}">{{ $stop['order'] }}. {{ $stop['name'] }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 sm:col-span-3 dark:text-gray-300">
                    <button type="button" role="switch" x-bind:aria-checked="groupByStop.toString()"
                        x-on:click="groupByStop = ! groupByStop" data-switch="group-by-stop"
                        x-bind:class="groupByStop ? 'bg-primary-600' : 'bg-gray-200 dark:bg-white/10'"
                        class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                        <span x-bind:class="groupByStop ? 'translate-x-4' : 'translate-x-0'"
                            class="pointer-events-none mt-0.5 ml-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"></span>
                    </button>
                    Agrupar por parada
                </label>
            </div>

            <div class="md:overflow-x-auto">
                <table class="block w-full md:table">
                    <thead class="hidden bg-gray-50 md:table-header-group dark:bg-gray-800">
                        <tr>
                            <th class="{{ $th }}">Boleto N°</th>
                            <th class="{{ $th }}">Nombre</th>
                            <th class="{{ $th }}">DNI</th>
                            @if ($showPhone)
                                <th class="{{ $th }}">Teléfono</th>
                            @endif
                            <th class="{{ $th }} text-center">Asiento</th>
                            <th class="{{ $th }}">Origen</th>
                            <th class="{{ $th }}">Destino</th>
                            @if ($showObservation)
                                <th class="{{ $th }}">Observación</th>
                            @endif
                        </tr>
                    </thead>

                    @foreach ($groups as $groupKey => $rows)
                        @php
                            $groupStop = $stopsByOrder->get((int) $groupKey);
                        @endphp
                        <tbody class="block md:table-row-group" x-show="groupVisible(@js((string) $groupKey))" data-group="{{ $groupKey }}">
                            <tr class="block md:table-row" x-show="groupByStop" data-group-heading>
                                <th colspan="8" class="block bg-primary-500/10 px-4 py-1.5 text-left text-sm font-semibold text-primary-700 md:table-cell dark:text-primary-400">
                                    @if ($groupStop)
                                        {{ $groupStop['order'] }}. {{ $groupStop['name'] }}
                                    @else
                                        Fuera del recorrido
                                    @endif
                                    <span class="ml-2 text-xs font-normal text-gray-600 dark:text-gray-400">suben {{ $rows->count() }}</span>
                                </th>
                            </tr>

                            @foreach ($rows as $passenger)
                                @php
                                    $isAdult = $passenger['type'] === 'adult';
                                    $hasPhone = ! $blank($passenger['phone']);
                                @endphp
                                <tr wire:key="passenger-{{ $passenger['ticket_id'] }}-{{ $loop->index }}"
                                    x-show="isVisible({{ $passenger['ticket_id'] }})"
                                    data-ticket="{{ $passenger['ticket_id'] }}"
                                    data-origin="{{ $passenger['origin_location_id'] }}"
                                    data-destination="{{ $passenger['destination_location_id'] }}"
                                    data-search="{{ $passenger['search'] }}"
                                    @class([
                                        'flex flex-wrap items-center gap-x-3 gap-y-1.5 border-t border-gray-200 px-4 py-3 md:table-row md:px-0 md:py-0 dark:border-gray-700',
                                        'hover:bg-gray-50 dark:hover:bg-white/5' => $isAdult,
                                        'bg-gray-50/60 dark:bg-white/[0.02]' => ! $isAdult,
                                    ])>
                                    <td class="text-sm md:table-cell md:px-4 md:py-2 md:text-center">
                                        @if ($isAdult)
                                            <span class="inline-block rounded-lg bg-gray-100 px-2 py-1 text-xs text-gray-800 dark:bg-gray-700 dark:text-gray-300">N° {{ $passenger['ticket_id'] }}</span>
                                        @endif
                                    </td>

                                    <td class="order-first w-full text-sm text-gray-900 md:order-0 md:table-cell md:w-auto md:px-4 md:py-2 dark:text-gray-100">
                                        @if ($isAdult)
                                            {{-- En el celular el DNI va al lado del nombre; en escritorio tiene su columna. --}}
                                            <span class="font-medium">{{ $passenger['name'] }}</span>
                                            @unless ($blank($passenger['dni']))
                                                <span class="{{ $mobileDni }}">DNI {{ $passenger['dni'] }}</span>
                                            @endunless
                                            @if (filled($passenger['travels_with'] ?? null))
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Viaja con: {{ $passenger['travels_with'] }}</div>
                                            @endif
                                        @else
                                            <div class="flex items-center gap-2 border-l-2 border-gray-300 pl-3 dark:border-gray-600">
                                                <span class="rounded bg-gray-200 px-1.5 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">{{ $passenger['type'] === 'child' ? 'Menor' : 'Mascota' }}</span>
                                                <span>{{ $passenger['name'] }}</span>
                                                @if ($passenger['type'] === 'child' && ! $blank($passenger['dni']))
                                                    <span class="{{ $mobileDni }}">DNI {{ $passenger['dni'] }}</span>
                                                @endif
                                                @if ($passenger['type'] === 'pet' && ($passenger['pet_count'] ?? 0) > 1)
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">({{ $passenger['pet_count'] }} mascotas)</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    <td class="hidden px-4 py-2 text-sm text-gray-900 md:table-cell dark:text-gray-100">
                                        {{ $blank($passenger['dni']) ? '—' : $passenger['dni'] }}
                                    </td>

                                    @if ($showPhone)
                                        {{-- En el celular, después del N° y el asiento: así esos dos no se separan. --}}
                                        <td @class(['order-1 text-sm text-gray-900 md:order-none md:table-cell md:px-4 md:py-2 dark:text-gray-100', 'hidden' => ! $hasPhone])>
                                            @if ($hasPhone)
                                                <span class="inline-flex items-center gap-2">
                                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $passenger['phone']) }}"
                                                        class="inline-flex items-center gap-1 font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                        title="Llamar">
                                                        <x-filament::icon icon="heroicon-m-phone" class="h-4 w-4" />
                                                        {{ $passenger['phone'] }}
                                                    </a>
                                                    @if (filled($passenger['phone_e164']))
                                                        <a href="https://wa.me/{{ $passenger['phone_e164'] }}" target="_blank" rel="noopener"
                                                            class="inline-flex rounded-md p-1 text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-400/10"
                                                            title="Escribir por WhatsApp" aria-label="Escribir por WhatsApp">
                                                            <x-icons.whatsapp class="h-4 w-4" />
                                                        </a>
                                                    @endif
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif

                                    <td class="text-sm md:table-cell md:px-4 md:py-2 md:text-center">
                                        @if ($isAdult && is_numeric($passenger['seat_number']))
                                            <span class="inline-block rounded-lg bg-primary-500/10 px-2 py-1 text-xs font-semibold text-primary-700 dark:text-primary-400">Asiento {{ $passenger['seat_number'] }}</span>
                                        @elseif (! $isAdult)
                                            <span class="hidden text-xs text-gray-500 md:inline dark:text-gray-400">No ocupa</span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    {{-- En el celular el recorrido completo va en su propia línea, siempre al final de la tarjeta. --}}
                                    <td @class(['order-last w-full text-sm md:order-none md:table-cell md:w-auto md:px-4 md:py-2', 'hidden' => ! $isAdult])>
                                        @if ($isAdult)
                                            <span class="{{ $routeBadge }}">{{ $passenger['origin'] }}</span>
                                            <span class="md:hidden">
                                                <span class="text-gray-400 dark:text-gray-500">→</span>
                                                <span class="{{ $routeBadge }}">{{ $passenger['destination'] }}</span>
                                            </span>
                                        @endif
                                    </td>

                                    <td class="hidden px-4 py-2 text-sm md:table-cell">
                                        @if ($isAdult)
                                            <span class="{{ $routeBadge }}">{{ $passenger['destination'] }}</span>
                                        @endif
                                    </td>

                                    {{-- La observación va solo en la fila del adulto; el menor y la mascota quedan vacíos.
                                         Recuadro con el mismo estilo que el detalle del boleto. --}}
                                    @if ($showObservation)
                                        @if ($isAdult && filled($passenger['observation'] ?? null))
                                            <td class="w-full md:table-cell md:w-auto md:min-w-56 md:max-w-sm md:px-4 md:py-2">
                                                <div
                                                    class="rounded-lg border-l-4 border-amber-400 bg-amber-50 px-3 py-2 text-sm wrap-break-word whitespace-pre-line text-gray-900 dark:border-amber-500 dark:bg-amber-900/20 dark:text-gray-100">{{ $passenger['observation'] }}</div>
                                            </td>
                                        @elseif ($isAdult)
                                            <td class="hidden px-4 py-2 text-sm md:table-cell text-gray-400 dark:text-gray-500">-</td>
                                        @else
                                            <td class="hidden md:table-cell"></td>
                                        @endif
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>

            <div x-show="visibleCount === 0" x-cloak class="flex flex-col items-start gap-2 px-6 py-8">
                <p class="text-gray-500 dark:text-gray-400">Ningún pasajero coincide con los filtros.</p>
                <x-filament::link tag="button" type="button" x-on:click="clear()" color="primary">Limpiar filtros</x-filament::link>
            </div>

            <div x-show="filtering && visibleCount > 0" x-cloak class="border-t border-gray-200 px-4 py-2 text-right dark:border-gray-700">
                <x-filament::link tag="button" type="button" x-on:click="clear()" color="gray" size="sm">Limpiar filtros</x-filament::link>
            </div>
        @else
            <div class="px-6 py-8">
                <p class="text-gray-500 dark:text-gray-400">No hay pasajeros registrados para este viaje.</p>
            </div>
        @endif
    </div>
</div>
