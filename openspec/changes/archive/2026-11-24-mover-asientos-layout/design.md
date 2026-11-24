# Diseño

## Contexto

Ver proposal.md (Por qué). Estado actual relevante:

- `DesignBusLayout` guarda el layout en memoria (`$seats`, `$areas`) y solo lo persiste con `save()` → `BusLayoutService::saveLayout()`.
- `saveLayout()` identifica cada asiento por `existing_id` y actualiza el mismo registro (`fill` + `save`). Los boletos referencian `seat_id`, así que cambiar `row`/`column` no afecta las ventas. Ya valida que no haya dos asientos en la misma celda ni asientos dentro de zonas.
- `moveSeat($row, $col, $toRow, $toCol)` existe pero no está conectado a la vista. Hoy rechaza cualquier destino ocupado.
- El modo de edición (`seat` / `area`) vive solo en Alpine (`busLayoutEditor`), no en Livewire.

## Objetivos / No objetivos

**Objetivos:**
- Conectar el movimiento a la interfaz con un modo "Mover" de dos clics.
- Intercambiar asientos cuando el destino es otro asiento.

**No objetivos:**
- Arrastrar y soltar (queda para más adelante como atajo).
- Mover asientos entre pisos.
- Renumerar al mover (para eso está "Reordenar numeración").
- Recalcular `position` (left/right/aisle) al mover.

## Decisiones

### La selección vive en Alpine; el movimiento en Livewire
El asiento marcado (`selected: {row, col} | null`) se guarda en el componente Alpine, igual que `mode` y el arrastre de zonas. Así marcar/desmarcar no genera ida y vuelta al servidor. El segundo clic llama a `$wire.moveSeat(fromRow, fromCol, toRow, toCol)` y limpia la selección.
*Alternativa:* una propiedad Livewire `$selectedSeat`. Se descarta porque cada clic re-renderiza la grilla completa y rompe la coherencia con cómo ya funcionan los modos.

### `moveSeat()` intercambia en lugar de rechazar
Si en el destino hay un asiento, `moveSeat()` intercambia `row`/`column` entre ambos por índice dentro de `$this->seats`. Si el destino es una zona, mantiene el aviso actual y no cambia nada. Mover a la misma celda no hace nada.
Los índices se buscan comparando piso, fila y columna (no con `$s === $seat`), para no depender de igualdad estricta de arrays.

### Clics en modo "Mover"
- Asiento sin selección → seleccionar.
- Asiento seleccionado → deseleccionar.
- Otro asiento o celda vacía con selección → `moveSeat`.
- Zona con selección → `moveSeat`, que muestra el aviso (la validación queda en el servidor).
- Zona o celda vacía sin selección → nada.

En modo "Mover", los botones de asiento y zona no deben ejecutar `removeSeatAt` / `removeAreaAt`: el `wire:click` actual se reemplaza por un manejador Alpine que decide según el modo.

### Cancelación
Esc (ya hay un `@keydown.escape.window`), `setMode()` y el cambio de piso limpian `selected`. Para el piso, el botón de piso limpia la selección en Alpine antes del `wire:click="setFloor(...)"`.

### Sin cambios en `saveLayout()`
El servicio ya cubre la persistencia y la protección de ventas; no se modifica.

## Riesgos / Compromisos

- [`position` queda desactualizado tras mover] → No se usa en el selector de asientos ni en la venta; se acepta. Si en el futuro se usa, se puede derivar de la columna al guardar.
- [El re-render de Livewire puede perder el resaltado si la selección se identifica por `wire:key`] → Se identifica por coordenadas en Alpine, que sobreviven al re-render del morph.
- [Confusión entre modos: en "Asientos" el clic quita] → Texto de ayuda propio del modo "Mover" y resaltado claro del asiento marcado.
