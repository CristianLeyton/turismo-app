@php
    $busName = $busName ?? '—';
    $originName = $originName ?? '—';
    $destinationName = $destinationName ?? '—';
    $isRoundTrip = $isRoundTrip ?? false;
    $returnBusName = $returnBusName ?? null;
    $returnOriginName = $returnOriginName ?? null;
    $returnDestinationName = $returnDestinationName ?? null;
    $showReturn = $isRoundTrip && $returnBusName !== null && $returnOriginName !== null && $returnDestinationName !== null;
@endphp
@if($busName !== '—' || $originName !== '—' || $destinationName !== '—' || $showReturn)
<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-3 text-sm space-y-2">
    <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
        <span class="font-semibold text-fuchsia-600 dark:text-fuchsia-500">Ida: </span>
        {{-- <span class="font-semibold text-gray-700 dark:text-gray-300">Colectivo:</span> --}}
        <span class="text-gray-900 dark:text-gray-100">{{ $busName }}</span>
        <span class="text-gray-400 dark:text-gray-500">|</span>
        <span class="font-semibold text-gray-700 dark:text-gray-300">Origen:</span>
        <span class="text-gray-900 dark:text-gray-100">{{ $originName }}</span>
        <span class="text-gray-400 dark:text-gray-500">|</span>
        <span class="font-semibold text-gray-700 dark:text-gray-300">Destino:</span>
        <span class="text-gray-900 dark:text-gray-100">{{ $destinationName }}</span>
    </div>
    @if($showReturn)
    <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 pt-1 border-t border-gray-200 dark:border-gray-600">
        <span class="font-semibold text-fuchsia-600 dark:text-fuchsia-500">Vuelta: </span>
        {{-- <span class="font-semibold text-gray-700 dark:text-gray-300">Colectivo:</span> --}}
        <span class="text-gray-900 dark:text-gray-100">{{ $returnBusName }}</span>
        <span class="text-gray-400 dark:text-gray-500">|</span>
        <span class="font-semibold text-gray-700 dark:text-gray-300">Origen:</span>
        <span class="text-gray-900 dark:text-gray-100">{{ $returnOriginName }}</span>
        <span class="text-gray-400 dark:text-gray-500">|</span>
        <span class="font-semibold text-gray-700 dark:text-gray-300">Destino:</span>
        <span class="text-gray-900 dark:text-gray-100">{{ $returnDestinationName }}</span>
    </div>
    @endif
</div>
@endif
