{{-- Vista previa de la paleta del color elegido en Parámetros > Apariencia,
     antes de guardar. Los colores van inline: salen del color del formulario,
     no de la paleta guardada (--primary-*). --}}
<div class="flex flex-col gap-3" data-palette-preview>
    @if ($palette === [])
        <p class="text-sm text-gray-500 dark:text-gray-400">Elegí un color válido para ver la vista previa.</p>
    @else
        <div class="grid grid-cols-6 gap-1 sm:grid-cols-11">
            @foreach ($palette as $shade => $hex)
                <div class="flex flex-col items-center gap-1" wire:key="palette-{{ $shade }}">
                    <div class="h-8 w-full rounded-md ring-1 ring-gray-950/10 dark:ring-white/10"
                        style="background-color: {{ $hex }}" title="{{ $hex }}"></div>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $shade }}</span>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-md px-3 py-1.5 text-sm font-semibold"
                style="background-color: {{ $palette[600] }}; color: {{ $textColor }}">Botón</span>
            <span class="rounded-md px-3 py-1.5 text-sm font-medium"
                style="background-color: {{ $palette[50] }}; color: {{ $palette[700] }}">Destacado</span>
            <span class="text-sm font-semibold" style="color: {{ $palette[600] }}">Link</span>
        </div>
    @endif
</div>
