{{--
    Historial de ediciones de un boleto (ticket_edits), en el estilo del resto
    del panel: una entrada por EDICIÓN (no por campo), con autor, fecha, motivo
    y el detalle Antes/Después de cada campo cambiado.

    Se usa en dos lugares, siempre con el mismo render:
      - la vista del boleto (infolist "Historial de ediciones");
      - el detalle del Historial general (modal "Ver" y página de vista).

    Variables: $edits (colección de TicketEdit). Si no se pasa, se toman del
    registro del schema ($getRecord()) — así funciona como vista de un
    ViewEntry de Filament.
--}}
@php
    $record = isset($getRecord) && $getRecord() ? $getRecord() : null;
    $edits = $edits ?? ($record?->edits ?? collect());
    $groups = \App\Support\TicketEditHistory::groups($edits);
@endphp

@forelse ($groups as $group)
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
        <div class="flex flex-col sm:flex-row sm:justify-between gap-2">
            <span
                class="self-start inline-block px-2.5 py-1 text-xs font-semibold rounded-lg
                         bg-fuchsia-100 dark:bg-blue-900/30 text-fuchsia-800 dark:text-fuchsia-300">
                {{ count($group['changes']) }} campo{{ count($group['changes']) === 1 ? '' : 's' }}
                corregido{{ count($group['changes']) === 1 ? '' : 's' }}
            </span>

            <div class="text-sm text-gray-600 dark:text-gray-400 sm:text-right">
                <span class="font-semibold text-gray-900 dark:text-gray-100">
                    Realizado por:
                    {{ $group['user']?->name ?? 'Usuario eliminado del sistema' }}{{ $group['user']?->username ? ' (' . $group['user']->username . ')' : '' }}
                </span>
                <span class="mx-1">•</span>
                {{ $group['at']?->format('d/m/Y H:i') ?? '—' }}
            </div>
        </div>

        @if ($group['reason'])
            <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                <span class="font-semibold text-gray-900 dark:text-gray-100">Motivo:</span>
                {{ $group['reason'] }}
            </div>
        @endif

        <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto_1fr] sm:items-start">
            <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-3">
                <div class="text-xs uppercase text-red-700 dark:text-red-400">Antes</div>
                @foreach ($group['changes'] as $change)
                    <div class="mt-1 text-sm">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $change['label'] }}:</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $change['from'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="hidden sm:block self-center text-gray-400 dark:text-gray-500 text-lg">→</div>

            <div class="rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-3">
                <div class="text-xs uppercase text-green-700 dark:text-green-400">Después</div>
                @foreach ($group['changes'] as $change)
                    <div class="mt-1 text-sm">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $change['label'] }}:</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $change['to'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@empty
    <div class="px-6 py-8">
        <p class="text-gray-500 dark:text-gray-400">Este boleto no tiene ediciones registradas.</p>
    </div>
@endforelse
