{{-- Tarjeta "Próximo viaje" de "Mis boletos". Recibe $ticket (el boleto del
     viaje más cercano desde hoy) o null: sin viaje próximo no muestra nada. --}}

@if ($ticket)
    <div
        class="flex flex-col gap-3 rounded-xl border border-primary-300 bg-primary-50 p-4 dark:border-primary-400/30 dark:bg-primary-400/10 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-1">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-400">
                Próximo viaje
            </p>
            <p class="text-base font-bold text-gray-950 dark:text-white">
                {{ $ticket->origin?->name ?? '?' }} → {{ $ticket->destination?->name ?? '?' }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ $ticket->trip->trip_date->format('d/m/Y') }}
                @if ($ticket->trip->schedule?->departure_time)
                    · {{ $ticket->trip->schedule->departure_time->format('H:i') }}
                @endif
                @if ($ticket->seat?->seat_number !== null)
                    · Asiento {{ $ticket->seat->seat_number }}
                @endif
                · N°{{ $ticket->id }}
            </p>
        </div>

        <x-filament::button
            tag="a"
            :href="route('clientes.boletos.show', ['ticket' => $ticket->id])"
            target="_blank"
            color="gray"
            size="sm"
            icon="heroicon-m-eye">
            Ver boleto
        </x-filament::button>
    </div>
@endif
