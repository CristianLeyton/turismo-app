# Propuesta: emisión atómica y asiento único garantizado por la base

## Why

La emisión de una venta no es atómica (hallazgo H4): crea la venta, los pasajeros, los boletos de ida y los de vuelta en pasos separados, sin transacción. Si un asiento se ocupa en el último momento, pueden quedar ventas sin boletos o una ida sin su vuelta. Además, la base de datos no impide dos boletos vigentes en el mismo asiento y viaje (H9): el índice único incluye `deleted_at`, que es `NULL` en los boletos vigentes. A eso se suma que el bloqueo del viaje se libera antes de crear los boletos. Las dos cosas juntas permiten vender dos veces el mismo asiento.

## What Changes

- **BREAKING (comportamiento):** la emisión pasa a ser **todo o nada**. La venta, los pasajeros y los boletos de los dos tramos se crean en una sola transacción. Si falla cualquier parte, no queda nada creado y se informa el motivo.
- Durante esa transacción, los viajes de ida y de vuelta quedan **bloqueados hasta el commit**: la verificación final de disponibilidad y la creación de boletos ocurren bajo el mismo bloqueo.
- La entrega de los boletos (descarga del PDF o previsualización con link compartible) ocurre **después** del commit. Un error al generar el PDF ya no afecta lo emitido.
- Nueva restricción en la base: **un solo boleto vigente por viaje y asiento**, mediante una columna generada (1 si el boleto está vigente, `NULL` si está dado de baja) con un índice único (viaje, asiento, vigencia). Los boletos dados de baja siguen sin bloquear el asiento.
- La migración primero **busca duplicados vigentes**. Si encuentra alguno, se detiene sin modificar nada y lista los viajes, asientos y boletos en conflicto para corregirlos a mano.
- Los conflictos que ahora detecta la base se traducen a mensajes claros en los tres caminos que escriben asientos: la emisión, la reprogramación y la restauración de un boleto dado de baja.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `seat-reservation`:
  - "Verificación final de disponibilidad al emitir" pasa a hacerse con los viajes bloqueados hasta el commit.
  - "Unicidad de boletos vigentes solo por control de la aplicación" se reemplaza por una unicidad garantizada por la base.
  - "Emisión no atómica ante fallos tardíos" se reemplaza por la emisión atómica.
  - Se agrega el comportamiento al restaurar un boleto cuyo asiento ya está ocupado.
- `ticket-sale`: "Cierre de una venta exitosa" aclara que la entrega de los boletos ocurre después de confirmar la emisión y que un fallo en la entrega no anula la venta.

## Impact

- **Código:**
  - `app/Filament/Resources/Tickets/Pages/CreateTicket.php`: `handleRecordCreation` y `createPendingTickets` en una transacción, con la entrega posterior al commit.
  - `app/Models/Trip.php`: `reserveSeatsWithLock` y `createTicketsWithLock` participan de la transacción externa y la detección del conflicto ya no depende del nombre del índice.
  - `app/Services/TicketRescheduleService.php`: detección del conflicto con el índice nuevo.
  - Restauración de boletos en `ViewTicket` (`RestoreAction`) y en `TicketsTable` (`RestoreBulkAction`).
- **Base de datos:** una migración nueva sobre `tickets` (columna generada, reemplazo de `tickets_trip_seat_unique`). Requiere MySQL 5.7 o superior (local: 8.4) y funciona en SQLite para los tests.
- **Deploy:** antes de migrar en producción hay que revisar si existen duplicados vigentes. La migración los detecta y se detiene.
- **Tests:** nuevos para la atomicidad (un fallo en la vuelta no deja nada), la restricción de la base, la migración con duplicados, la reprogramación y la restauración con conflicto.
