<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class CustomDashboard extends BaseDashboard
{
/*     protected ?string $heading = 'Inicio'; */

    public function getTitle(): string | Htmlable
    {
        return __(''); // Título vacío
    }

    protected function getHeaderWidgets(): array
    {
        return [
            
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Widgets para el footer si los necesitas
        ];
    }
}
