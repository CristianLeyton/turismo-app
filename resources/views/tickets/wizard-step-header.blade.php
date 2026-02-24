@php
    $busName = $busName ?? '—';
    $originName = $originName ?? '—';
    $destinationName = $destinationName ?? '—';
@endphp
@if($busName !== '—' || $originName !== '—' || $destinationName !== '—')
<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-3 text-sm">
    <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
        <span class="font-semibold text-gray-700 dark:text-gray-300">Colectivo:</span>
        <span class="text-gray-900 dark:text-gray-100">{{ $busName }}</span>
        <span class="text-gray-400 dark:text-gray-500">|</span>
        <span class="font-semibold text-gray-700 dark:text-gray-300">Origen:</span>
        <span class="text-gray-900 dark:text-gray-100">{{ $originName }}</span>
        <span class="text-gray-400 dark:text-gray-500">|</span>
        <span class="font-semibold text-gray-700 dark:text-gray-300">Destino:</span>
        <span class="text-gray-900 dark:text-gray-100">{{ $destinationName }}</span>
    </div>
</div>
@endif
