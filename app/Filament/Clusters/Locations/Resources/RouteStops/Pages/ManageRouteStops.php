<?php

namespace App\Filament\Clusters\Locations\Resources\RouteStops\Pages;

use App\Filament\Clusters\Locations\Resources\RouteStops\RouteStopResource;
use App\Models\RouteStop;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageRouteStops extends ManageRecords
{
    protected static string $resource = RouteStopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Al soltar la fila arrastrada, su horario se intercambia con el de la
     * parada de destino (la que ocupaba la posición donde cayó): el horario
     * queda pegado a la posición, no a la parada. Así el horario que el
     * usuario ve sigue coincidiendo con cada localidad impresa en el
     * recorrido, aunque el colectivo pase por ellas en otro orden.
     *
     * En el modo de reordenado por botón (sin arrastre, no llega la clave de
     * la fila arrastrada) se aplica la misma regla por posición para todas.
     *
     * @param  array<int | string>  $order  claves de las filas en su orden final
     */
    public function reorderTable(array $order, int | string | null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        $order = array_values($order);

        // Snapshot antes de guardar: horarios de cada posición (ordenadas por
        // stop_order vigente) y de cada fila.
        $stops = RouteStop::query()
            ->whereIn('id', $order)
            ->orderBy('stop_order')
            ->get(['id', 'departure_offset_minutes', 'arrival_offset_minutes']);

        $times = fn (RouteStop $stop): array => [
            $stop->departure_offset_minutes,
            $stop->arrival_offset_minutes,
        ];

        $oldByPosition = $stops->values()->map($times)->all();
        $oldKeysByPosition = $stops->values()->modelKeys();
        $oldByKey = $stops->keyBy('id')->map($times)->all();

        // Libera el orden actual (offset temporal) para no chocar con la
        // UNIQUE (route_id, stop_order) mientras se reescribe el orden.
        RouteStop::query()
            ->whereIn('id', $order)
            ->increment('stop_order', 10000);

        parent::reorderTable($order, $draggedRecordKey);

        $draggedIndex = filled($draggedRecordKey)
            ? array_search($draggedRecordKey, $order, false)
            : false;

        if ($draggedIndex !== false && isset($oldKeysByPosition[$draggedIndex], $oldByKey[$draggedRecordKey])) {
            // Intercambio 1-a-1: la arrastrada recibe el horario de la parada
            // que ocupaba la posición de destino, y esa parada recibe el suyo.
            $destinationKey = $oldKeysByPosition[$draggedIndex];
            $destinationOld = $oldByKey[$destinationKey];
            $draggedOld = $oldByKey[$draggedRecordKey];

            $this->updateStopTimes($draggedRecordKey, $destinationOld);

            if ((string) $destinationKey !== (string) $draggedRecordKey) {
                $this->updateStopTimes($destinationKey, $draggedOld);
            }
        } else {
            // Modo por botón (sin arrastre): el horario queda pegado a la
            // posición para todas las filas del listado reordenado.
            foreach ($order as $position => $recordKey) {
                $oldTimes = $oldByPosition[$position] ?? null;

                if ($oldTimes === null) {
                    continue;
                }

                $this->updateStopTimes($recordKey, $oldTimes);
            }
        }

        Notification::make()
            ->title('Orden de paradas actualizado')
            ->body('Los horarios de salida y llegada se intercambiaron con el nuevo orden.')
            ->success()
            ->send();
    }

    /**
     * @param  array{0: ?int, 1: ?int}  $times
     */
    private function updateStopTimes(int | string $recordKey, array $times): void
    {
        RouteStop::query()
            ->whereKey($recordKey)
            ->update([
                'departure_offset_minutes' => $times[0],
                'arrival_offset_minutes' => $times[1],
            ]);
    }
}
