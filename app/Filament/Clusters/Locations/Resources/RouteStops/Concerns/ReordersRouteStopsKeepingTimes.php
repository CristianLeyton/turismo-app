<?php

namespace App\Filament\Clusters\Locations\Resources\RouteStops\Concerns;

use App\Models\RouteStop;
use App\Support\AffectedTickets;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reordenado de paradas con los horarios pegados a la posición, guardado
 * recién al confirmar.
 *
 * Al soltar la fila arrastrada, su horario se intercambia con el de la
 * parada de destino (la que ocupaba la posición donde cayó): el horario
 * queda pegado a la posición, no a la parada. Así el horario que el
 * usuario ve sigue coincidiendo con cada localidad impresa en el
 * recorrido, aunque el colectivo pase por ellas en otro orden.
 *
 * En el modo de reordenado por botón (sin arrastre, no llega la clave de
 * la fila arrastrada) se aplica la misma regla por posición para todas.
 *
 * Cada arrastre sólo cambia el orden pendiente (en memoria, visible en la
 * tabla); nada se guarda hasta "Confirmar cambios", y "Descartar cambios"
 * vuelve al orden guardado.
 *
 * Lo usan el listado de paradas y la pestaña "Recorrido" de cada ruta.
 */
trait ReordersRouteStopsKeepingTimes
{
    /** @var list<int|string>|null claves de las paradas en el orden pendiente */
    public ?array $pendingStopOrder = null;

    /** @var array<int|string, array{0: ?int, 1: ?int}> horas pendientes por parada */
    public array $pendingStopTimes = [];

    /**
     * @param  array<int | string>  $order  claves de las filas en su orden final
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        $order = array_values($order);
        [$keysByPosition, $timesByKey] = $this->currentStopOrder($order);

        // Sólo se reordenan paradas que ya están en la tabla.
        if (count($order) !== count($keysByPosition) || array_diff($order, $keysByPosition) !== []) {
            return;
        }

        $draggedIndex = filled($draggedRecordKey)
            ? array_search($draggedRecordKey, $order, false)
            : false;

        $newTimes = $timesByKey;

        if ($draggedIndex !== false && isset($keysByPosition[$draggedIndex], $timesByKey[$draggedRecordKey])) {
            // Intercambio 1-a-1: la arrastrada recibe el horario de la parada
            // que ocupaba la posición de destino, y esa parada recibe el suyo.
            $destinationKey = $keysByPosition[$draggedIndex];

            $newTimes[$draggedRecordKey] = $timesByKey[$destinationKey];
            $newTimes[$destinationKey] = $timesByKey[$draggedRecordKey];
        } else {
            // Modo por botón (sin arrastre): el horario queda pegado a la
            // posición para todas las filas del listado reordenado.
            foreach ($order as $position => $recordKey) {
                $newTimes[$recordKey] = $timesByKey[$keysByPosition[$position]];
            }
        }

        $this->pendingStopOrder = $order;
        $this->pendingStopTimes = $newTimes;

        $this->flushCachedTableRecords();
    }

    /**
     * Salir del modo reordenar sin confirmar equivale a descartar: nunca
     * queda un orden pendiente colgado.
     */
    public function toggleTableReordering(): void
    {
        if ($this->isTableReordering) {
            $this->discardStopOrder();

            return;
        }

        $this->isTableReordering = true;
    }

    public function hasPendingStopOrder(): bool
    {
        return $this->pendingStopOrder !== null;
    }

    /**
     * Botón "Confirmar cambios": con boletos vendidos afectados abre el modal
     * de confirmación (acción saveStopOrder); si no, guarda directo.
     */
    public function requestStopOrderConfirmation(): void
    {
        if ($this->pendingStopOrderAffectedTickets() > 0) {
            $this->mountAction('saveStopOrder', context: ['table' => true]);

            return;
        }

        $this->confirmStopOrder();
    }

    /** Guarda el orden pendiente (si lo hay) y sale del modo reordenar. */
    public function confirmStopOrder(): void
    {
        $order = $this->pendingStopOrder;
        $times = $this->pendingStopTimes;

        $this->discardStopOrder();

        if ($order === null || ! $this->getTable()->isReorderable()) {
            return;
        }

        $stops = RouteStop::query()->whereIn('id', $order)->get(['id', 'route_id']);
        $routeIds = $stops->pluck('route_id')->unique()->values()->all();

        // Si mientras tanto se agregó o eliminó una parada, el orden pendiente
        // ya no corresponde al recorrido: no se guarda nada.
        $activeCount = RouteStop::query()->whereIn('route_id', $routeIds)->count();

        if ($stops->count() !== count($order) || count($routeIds) !== 1 || $activeCount !== count($order)) {
            Notification::make()
                ->title('No se guardó el nuevo orden')
                ->body('El recorrido cambió mientras lo reordenabas. Volvé a intentarlo.')
                ->danger()
                ->send();

            return;
        }

        // Todo o nada: si algo falla, el orden y las horas quedan como estaban.
        DB::transaction(function () use ($order, $times, $routeIds): void {
            $this->moveTrashedStopsOutOfTheWay($routeIds, count($order));

            // Libera el orden actual (offset temporal mayor que cualquier orden
            // de la ruta, incluidas las eliminadas) para no chocar con la
            // UNIQUE (route_id, stop_order) mientras se reescribe el orden.
            $highestOrder = (int) RouteStop::withTrashed()->whereIn('route_id', $routeIds)->max('stop_order');
            $lowestOrder = (int) RouteStop::query()->whereIn('id', $order)->min('stop_order');

            // Tras correrlas, la menor queda por encima de cualquier orden
            // existente y del rango final 1..N.
            RouteStop::query()
                ->whereIn('id', $order)
                ->increment('stop_order', max($highestOrder, count($order)) - $lowestOrder + 1);

            foreach ($order as $position => $recordKey) {
                RouteStop::query()
                    ->whereKey($recordKey)
                    ->update([
                        'stop_order' => $position + 1,
                        'departure_offset_minutes' => $times[$recordKey][0] ?? null,
                        'arrival_offset_minutes' => $times[$recordKey][1] ?? null,
                    ]);
            }
        });

        $this->notifyStopsReordered($routeIds[0]);
    }

    /** Vuelve al orden guardado y sale del modo reordenar. */
    public function discardStopOrder(): void
    {
        $this->pendingStopOrder = null;
        $this->pendingStopTimes = [];
        $this->isTableReordering = false;

        $this->flushCachedTableRecords();
    }

    /** Boletos próximos que verían el cambio del orden pendiente. */
    public function pendingStopOrderAffectedTickets(): int
    {
        if ($this->pendingStopOrder === null) {
            return 0;
        }

        $routeId = RouteStop::query()->whereKey($this->pendingStopOrder[0])->value('route_id');

        return $routeId !== null ? AffectedTickets::forRoute($routeId) : 0;
    }

    /**
     * Muestra el orden y las horas pendientes sin guardarlos.
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $records = parent::getTableRecords();

        if ($this->pendingStopOrder === null) {
            return $records;
        }

        if ($records instanceof AbstractPaginator) {
            return $records->setCollection($this->applyPendingStopOrder($records->getCollection()));
        }

        if ($records instanceof Collection) {
            return $this->applyPendingStopOrder($records);
        }

        return $records;
    }

    protected function notifyStopsReordered(?int $routeId): void
    {
        $warning = $routeId !== null ? AffectedTickets::warning(AffectedTickets::forRoute($routeId)) : null;

        Notification::make()
            ->title('Orden de paradas actualizado')
            ->body(trim('Los horarios de salida y llegada se intercambiaron con el nuevo orden. '.($warning ?? '')))
            ->when(
                $warning !== null,
                fn (Notification $notification) => $notification->warning()->persistent(),
                fn (Notification $notification) => $notification->success(),
            )
            ->send();
    }

    /**
     * Orden y horas de partida para el próximo arrastre: los pendientes, o los
     * guardados si todavía no se arrastró nada.
     *
     * @param  list<int|string>  $order
     * @return array{0: list<int|string>, 1: array<int|string, array{0: ?int, 1: ?int}>}
     */
    private function currentStopOrder(array $order): array
    {
        if ($this->pendingStopOrder !== null) {
            return [$this->pendingStopOrder, $this->pendingStopTimes];
        }

        $stops = RouteStop::query()
            ->whereIn('id', $order)
            ->orderBy('stop_order')
            ->get(['id', 'departure_offset_minutes', 'arrival_offset_minutes']);

        return [
            $stops->modelKeys(),
            $stops->mapWithKeys(fn (RouteStop $stop): array => [
                $stop->getKey() => [$stop->departure_offset_minutes, $stop->arrival_offset_minutes],
            ])->all(),
        ];
    }

    private function applyPendingStopOrder(Collection $records): Collection
    {
        $positions = array_flip(array_map('strval', $this->pendingStopOrder));

        return $records
            ->each(function (RouteStop $stop): void {
                [$departure, $arrival] = $this->pendingStopTimes[$stop->getKey()] ?? [$stop->departure_offset_minutes, $stop->arrival_offset_minutes];

                $stop->setAttribute('departure_offset_minutes', $departure);
                $stop->setAttribute('arrival_offset_minutes', $arrival);
            })
            ->sortBy(fn (RouteStop $stop): int => $positions[(string) $stop->getKey()] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Las paradas eliminadas siguen contando para la UNIQUE (route_id,
     * stop_order). El reordenado numera las activas 1..N, así que las
     * eliminadas que ocupan ese rango se pasan detrás de todas las demás.
     *
     * @param  list<int>  $routeIds
     */
    private function moveTrashedStopsOutOfTheWay(array $routeIds, int $activeCount): void
    {
        foreach ($routeIds as $routeId) {
            $blocking = RouteStop::onlyTrashed()
                ->where('route_id', $routeId)
                ->where('stop_order', '<=', $activeCount)
                ->orderBy('stop_order')
                ->get(['id']);

            if ($blocking->isEmpty()) {
                continue;
            }

            $next = max(
                (int) RouteStop::withTrashed()->where('route_id', $routeId)->max('stop_order'),
                $activeCount,
            ) + 1;

            foreach ($blocking as $stop) {
                RouteStop::withTrashed()->whereKey($stop->getKey())->update(['stop_order' => $next++]);
            }
        }
    }
}
