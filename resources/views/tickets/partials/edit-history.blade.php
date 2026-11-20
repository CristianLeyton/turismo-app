{{--
    Historial de ediciones de un boleto (ticket_edits), en el estilo del resto
    del panel: una entrada por EDICIÓN (no por campo), con autor, fecha, motivo
    y el detalle Antes/Después de cada campo cambiado.

    Se usa en dos lugares, siempre con el mismo render:
      - la vista del boleto (infolist "Historial de ediciones");
      - el detalle del Historial general (modal "Ver" y página de vista).

    Variables: $edits (colección de TicketEdit) y $forTicketId (el boleto que
    se está mirando). Si no se pasan, se toman del registro del schema
    ($getRecord()) — así funciona como vista de un ViewEntry de Filament.

    Las ediciones del pasajero hechas desde su ida o su vuelta se muestran
    con "Editado desde el boleto N° X": la ida y la vuelta comparten
    pasajero, así que el cambio también afectó a este boleto.
--}}
@php
    $record = isset($getRecord) && $getRecord() ? $getRecord() : null;
    $edits = $edits ?? ($record?->visibleEdits() ?? collect());
    $forTicketId = $forTicketId ?? $record?->id;
    $groups = \App\Support\TicketEditHistory::groups($edits, $forTicketId);
@endphp

@forelse ($groups as $group)
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
        <div class="flex flex-col sm:flex-row sm:justify-between gap-2">
            <span
                class="self-start inline-block px-2.5 py-1 text-xs font-semibold rounded-lg
                         bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-300">
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

        @if ($group['from_ticket_id'])
            <div class="mt-2 flex items-start gap-1.5 text-sm text-gray-600 dark:text-gray-400" data-shared-edit>
                <x-filament::icon icon="heroicon-m-arrows-right-left" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    Editado desde el boleto
                    <a href="{{ \App\Filament\Resources\Tickets\TicketResource::getUrl('view', ['record' => $group['from_ticket_id']]) }}"
                        target="_blank" rel="noopener"
                        class="font-semibold text-primary-600 hover:underline dark:text-primary-400">N° {{ $group['from_ticket_id'] }}</a>
                    (mismo pasajero)
                </span>
            </div>
        @endif

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
