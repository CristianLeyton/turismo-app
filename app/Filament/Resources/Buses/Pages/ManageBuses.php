<?php

namespace App\Filament\Resources\Buses\Pages;

use App\Filament\Resources\Buses\BusResource;
use App\Models\Bus;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageBuses extends ManageRecords
{
    protected static string $resource = BusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // "Crear colectivo": formulario normal + opción de partir de una plantilla
            // existente o de copiar el layout de otro colectivo.
            CreateAction::make()
                ->label('Nuevo colectivo')
                // El formulario viene del recurso (incluye "Partir de un diseño
                // existente", visible sólo al crear). Esta lógica aplica el
                // diseño elegido justo después de crear el colectivo.
                ->using(function (array $data, string $model) {
                    $seed = $data['layout_seed'] ?? null;
                    unset($data['layout_seed']);

                    /** @var Bus $bus */
                    $bus = $model::create($data);

                    if (filled($seed)) {
                        $service = app(\App\Services\BusLayoutService::class);
                        [$kind, $id] = explode(':', (string) $seed);

                        try {
                            $applied = $kind === 'template'
                                ? $service->applyTemplate($bus, \App\Models\BusLayoutTemplate::findOrFail((int) $id))
                                : tap(Bus::findOrFail((int) $id), function (Bus $source) use ($bus, $service) {
                                    if ($bus->floors !== $source->floors) {
                                        $bus->update(['floors' => $source->floors]);
                                    }
                                    $service->copyLayoutFrom($bus, $source);
                                })->seats()->count();

                            Notification::make()
                                ->title('Colectivo creado')
                                ->body($kind === 'template'
                                    ? "Se aplicó la plantilla: {$applied} asientos cargados."
                                    : "El diseño se copió desde \"{$bus->name}\"; revisá el layout cuando quieras.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('El colectivo se creó, pero el diseño no se pudo copiar')
                                ->body('Podés ajustarlo manualmente desde "Diseñar layout".')
                                ->warning()
                                ->send();
                        }
                    }

                    return $bus;
                }),
        ];
    }
}
