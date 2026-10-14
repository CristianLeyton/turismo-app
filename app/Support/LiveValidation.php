<?php

namespace App\Support;

use Filament\Schemas\Components\Component;

/**
 * Validación en tiempo real de un campo de Filament.
 *
 * Filament no valida solo porque un campo tenga `live()`: el binding de
 * Livewire sólo actualiza el estado. Para que el error aparezca apenas el
 * usuario completa el campo (sin apretar "Siguiente"/"Guardar") hay que
 * disparar `validateOnly()` desde el `afterStateUpdated` del propio campo.
 *
 * Uso:
 *   TextInput::make('dni')
 *       ->live(onBlur: true)
 *       ->afterStateUpdated(fn (TextInput $component) => LiveValidation::only($component))
 */
class LiveValidation
{
    public static function only(Component $component): void
    {
        $livewire = $component->getLivewire();

        if (is_object($livewire) && method_exists($livewire, 'validateOnly')) {
            $livewire->validateOnly($component->getStatePath());
        }
    }
}
