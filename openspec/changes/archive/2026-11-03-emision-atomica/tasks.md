# Tasks

## 1. Restricción en la base

- [x] 1.1 Crear `App\Support\SeatConflict::isActiveSeatViolation()` (design D6), que reconozca la violación en MySQL y en SQLite. Verificar con tests unitarios que usen excepciones reales de cada motor (el mensaje de MySQL como fixture, el de SQLite generado en la base de tests).
- [x] 1.2 Crear con `php artisan make:migration` la migración de la columna generada virtual `active_seat_guard` y el índice `tickets_active_trip_seat_unique`, reemplazando `tickets_trip_seat_unique`, con el control previo de duplicados y `down()` reversible (design D4 y D5). Verificar con un test que insertar dos boletos vigentes en el mismo viaje y asiento falla, que uno dado de baja más uno vigente se permite y que varios boletos sin fecha se permiten.
- [x] 1.3 Verificar el control previo de la migración: con un test que cree duplicados con el índice viejo y ejecute el `up()` de la migración, comprobar que falla, lista viaje, asiento e ids, y deja la tabla sin cambios.
- [x] 1.4 Ejecutar la migración en MySQL local y verificar con `SHOW INDEX FROM tickets` el índice nuevo. Dentro de una transacción revertida, verificar que MySQL rechaza un duplicado vigente. Correr también `migrate:rollback` y volver a migrar para verificar que `down()` funciona.

## 2. Emisión atómica

- [x] 2.1 Crear la excepción de dominio para asientos no disponibles y hacer que `Trip::createTicketsWithLock` la lance en lugar de confirmar parcialmente, usando `SeatConflict` para la violación de unicidad (design D1, D2 y D6). Verificar con los tests existentes de venta más uno nuevo de `createTicketsWithLock` con un asiento ocupado que no crea ningún boleto.
- [x] 2.2 Envolver en `DB::transaction` la creación de la venta, los pasajeros y los boletos de ambos tramos en `handleRecordCreation` y en `createPendingTickets`, bloqueando los viajes en orden de id al inicio, y mover la entrega (PDF o link) y la liberación de reservas después del commit (design D1 a D3). Verificar con tests: si falla la vuelta, no queda venta, ni pasajeros, ni boletos de ida; en una venta mixta con fallo en la ida, no queda el boleto pendiente; una venta exitosa sigue creando todo y entregando los boletos.
- [x] 2.3 Verificar que un fallo al generar el PDF después del commit deja la venta emitida y muestra la notificación de error del PDF. Test simulando la falla de `TicketPdfService`.

## 3. Otros caminos de escritura

- [x] 3.1 Cambiar `TicketRescheduleService::isUniqueSeatViolation` para que use `SeatConflict`. Verificar en `TicketRescheduleServiceTest` y `RescheduleDoesNotAffectOtherSeatsTest` que mover un boleto a un asiento ocupado sigue informando el conflicto, sin 500, también en SQLite.
- [x] 3.2 Capturar el conflicto en `RestoreAction` (`ViewTicket`) y `RestoreBulkAction` (`TicketsTable`) con una notificación que indique el asiento ocupado (design D7). Verificar con tests: restaurar un boleto cuyo asiento se revendió se rechaza y el boleto sigue dado de baja; en la acción masiva, los demás se restauran.
  - `RestoreBulkAction` está comentado en `TicketsTable` (los `toolbarActions` están desactivados), así que el único camino real es `RestoreAction` en `ViewTicket`, que quedó cubierto con tests en `TicketDeleteReasonTest`. La acción masiva no se tocó: si se reactiva, hay que aplicarle el mismo manejo.
  - Además, `TicketRescheduleServiceTest` simula una venta concurrente para cubrir el rechazo de la base durante la reprogramación.

## 4. Cierre

- [x] 4.1 Ejecutar `vendor/bin/pint --dirty --format agent` y la suite completa (`php artisan test --compact`), y verificar que pasa.
- [x] 4.2 Probar en el navegador una venta de ida y vuelta normal y una venta mixta. Verificar en la base que se creó todo y que la entrega (PDF o link) funciona igual que antes.
  - Ida y vuelta (La Plata → El Charco, 20/11 y 21/11, asiento 10): venta 14680, boletos 27270 (ida, viaje 704) y 27271 (vuelta, viaje 705), un pasajero, `active_seat_guard = 1`. Se abrió la página del link con la vista previa del PDF y se liberaron las reservas de la sesión.
  - Mixta (ida 20/11 con asiento 9, vuelta sin fecha): venta 14681, boletos 27272 (ida, viaje 704) y 27273 (vuelta pendiente, sin viaje ni asiento). El link muestra el aviso de pasaje diferido y el PDF.
  - Para la venta mixta se dio de alta en el padrón local el cliente de prueba 8 (DNI 99000222), porque la vuelta sin fecha exige pasajero registrado.

## Workflow follow-up

- Antes del deploy, revisar duplicados vigentes en producción con la consulta de la migración y corregirlos.
- Archivar con `/opsx:archive`.
