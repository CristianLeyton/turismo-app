# Design

## Context

- `CreateTicket::handleRecordCreation()` (y `createPendingTickets()` para las ventas mixtas o sin fecha) crea, en orden: la venta (`Sale::createNew`), los pasajeros, la verificación de la ida (`reserveSeatsWithLock`), los boletos de ida (`createTicketsWithLock`), la verificación de la vuelta y los boletos de vuelta. Después entrega los boletos (`generateAndDownloadTickets` o `finishSaleWithShareLink`). No hay transacción externa: el `catch` solo borra las reservas de la sesión y vuelve a lanzar la excepción.
- `Trip::reserveSeatsWithLock()` abre su propia transacción con `lockForUpdate()` sobre el viaje, verifica y hace commit, con lo que libera el bloqueo. `Trip::createTicketsWithLock()` abre otra transacción, sin bloquear el viaje, vuelve a verificar e inserta. Si algún boleto falla, devuelve `success=false` pero **confirma los que sí creó**.
- `tickets_trip_seat_unique` es (`trip_id`, `seat_id`, `deleted_at`). En MySQL un índice único admite varios `NULL`, así que no frena dos boletos vigentes. Los boletos sin fecha tienen `trip_id` y `seat_id` en `NULL`.
- La detección del duplicado busca el texto `tickets_trip_seat_unique` en el mensaje (`Trip::createTicketsWithLock` y `TicketRescheduleService::isUniqueSeatViolation`). En SQLite el mensaje de unicidad nombra las columnas y no el índice (se vio en H1), así que esa detección no funciona en los tests.
- `TicketRescheduleService` ya ejecuta el movimiento dentro de `DB::transaction`.
- Los boletos dados de baja se restauran con `RestoreAction` en `ViewTicket` y con `RestoreBulkAction` en `TicketsTable`, sin ningún control del asiento.
- Base local: MySQL 8.4. Tests: SQLite en memoria.

## Goals / Non-Goals

**Goals:**
- Que la emisión sea todo o nada, con el bloqueo de los viajes sostenido hasta el commit.
- Que la base sea la última defensa ante un asiento duplicado, en todos los caminos de escritura.
- Que cada conflicto se vea como un mensaje claro, sin errores 500.

**Non-Goals:**
- Cambiar el ciclo de vida de las reservas temporales (H3, H6, H7, H10): es el change `reservas-ciclo-de-vida`.
- Corregir la marca de tramo de vuelta (H2).
- Reparar automáticamente duplicados existentes: si los hay, se corrigen a mano con la lista que da la migración.

## Decisions

### D1. Una transacción externa en `handleRecordCreation`
Todo el bloque, desde `Sale::createNew` hasta la creación del último boleto (incluido `createPendingTickets`), se envuelve en `DB::transaction()`. Las verificaciones previas que hoy frenan con `Halt` (padrón, métodos de pago, corte de venta, días de servicio) quedan **antes** de la transacción, como ahora, porque no escriben.
- Un fallo de asientos dentro de la transacción lanza una excepción de dominio (`SeatUnavailableException`, con los números de asiento) en lugar de devolver `success=false`. Eso fuerza el rollback, y afuera se convierte en la notificación actual.
- *Alternativa:* compensar a mano (borrar lo creado si falla la vuelta). Se descartó: es frágil y no cubre caídas del proceso.

### D2. Bloqueo sostenido: `lockForUpdate` dentro de la transacción externa
`reserveSeatsWithLock` y `createTicketsWithLock` siguen usando `DB::transaction`. Dentro de una transacción externa, Laravel las convierte en savepoints, así que el `lockForUpdate` del viaje se mantiene hasta el commit externo. Así la verificación y la inserción quedan bajo el mismo bloqueo, sin cambiar la firma de los métodos ni sus otros usos.
- Los dos viajes (ida y vuelta) se bloquean siempre en el mismo orden (id ascendente) al inicio de la transacción, para evitar deadlocks entre dos ventas de ida y vuelta cruzadas.
- `createTicketsWithLock` deja de confirmar parcialmente: ante cualquier fallo lanza la excepción de D1.

### D3. Entrega después del commit
`generateAndDownloadTickets` / `finishSaleWithShareLink` y la liberación de reservas de la sesión se ejecutan después de que `DB::transaction` devuelve. La generación del PDF ya tiene su propio `try/catch` con notificación, así que un fallo ahí no revierte nada.

### D4. Columna generada `active_seat_guard` más un índice único
Una columna generada **virtual** `active_seat_guard`, definida como `CASE WHEN deleted_at IS NULL THEN 1 END`, y el índice único `tickets_active_trip_seat_unique` sobre (`trip_id`, `seat_id`, `active_seat_guard`). Se elimina `tickets_trip_seat_unique`.
- Es virtual (no stored) porque SQLite no permite agregar columnas `STORED` con `ALTER TABLE`, y MySQL/InnoDB sí admite índices sobre columnas virtuales.
- Vigentes con viaje y asiento: valor 1, únicos por viaje y asiento. Dados de baja: `NULL`, sin restricción. Sin fecha: `trip_id` y `seat_id` en `NULL`, sin restricción.
- *Alternativa:* índice parcial (`WHERE deleted_at IS NULL`). MySQL no lo soporta.
- *Alternativa:* volver a (`trip_id`, `seat_id`). Rompe la reventa del asiento de un boleto dado de baja, que es la razón de la migración de enero.

### D5. Migración con control previo
`up()` primero consulta los grupos (`trip_id`, `seat_id`) con más de un boleto vigente. Si hay alguno, lanza una excepción con la lista (viaje, asiento, ids) **antes** de cualquier DDL, porque en MySQL el DDL no es transaccional. `down()` restaura `tickets_trip_seat_unique` sobre (`trip_id`, `seat_id`, `deleted_at`) y elimina la columna.

### D6. Detección del conflicto independiente del motor
Un helper `App\Support\SeatConflict::isActiveSeatViolation(QueryException)` reconoce el error de unicidad tanto en MySQL (código 1062 con el nombre del índice) como en SQLite (`UNIQUE constraint failed: tickets.trip_id, tickets.seat_id, tickets.active_seat_guard`). Lo usan `Trip::createTicketsWithLock`, `TicketRescheduleService` y la restauración.

### D7. Restauración con mensaje claro
`RestoreAction` y `RestoreBulkAction` de boletos capturan la violación (D6) y muestran una notificación con el número de asiento ocupado. En la acción masiva, cada boleto en conflicto se informa y el resto se restaura.
- *Alternativa:* verificar antes de restaurar. Igual hace falta capturar la excepción por la carrera, así que se captura directamente.

## Risks / Trade-offs

- [Producción puede tener duplicados vigentes y la migración no avanza] → La migración lista los conflictos, y el deploy incluye un paso previo de revisión (Migration Plan).
- [Bloqueo más largo de los viajes durante la emisión] → La transacción solo incluye inserts (unas decenas de filas como mucho), sin generación de PDF ni llamadas externas. Las ventas concurrentes del mismo viaje esperan milisegundos.
- [Deadlock entre dos ventas de ida y vuelta cruzadas] → Bloqueo en orden de id (D2). Si igual ocurre, MySQL aborta una y se informa como error de emisión, sin datos parciales.
- [`Sale::createNew` u otro código que use `DB::` con otra conexión] → Se revisa durante la implementación. Todo el código usa la conexión por defecto.
- [El índice sobre una columna virtual no se puede verificar en SQLite igual que en MySQL] → Los tests corren en SQLite, y la verificación en MySQL local se hace con la migración y una inserción duplicada dentro de una transacción revertida.

## Migration Plan

1. Antes del deploy, revisar duplicados vigentes en producción con la misma consulta que usa la migración (queda documentada en la migración).
2. Si hay, corregirlos a mano: dar de baja o reprogramar uno de cada par.
3. Deploy más `php artisan migrate`. Si la migración encuentra duplicados, falla sin cambios y lista los conflictos; se corrigen y se reintenta.
4. Rollback: `php artisan migrate:rollback` vuelve al índice anterior. El código revertido sigue funcionando con el índice viejo.
