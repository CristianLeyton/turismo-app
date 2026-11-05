# Tasks

## 1. Marca explícita al crear

- [x] 1.1 Cambiar el hook `creating` de `Ticket` para que aplique la convención solo si `is_return_leg` no está entre los atributos (design D1). Verificar con tests: un boleto de ida y vuelta, sin viaje de vuelta, a precio 0 y sin marca queda como vuelta, y el mismo boleto con `is_return_leg => false` queda como ida.
- [x] 1.2 En `CreateTicket`, marcar `is_return_leg` en todos los boletos que crea la emisión: ida y vuelta de la venta con fecha, ida sin fecha y vuelta con fecha del flujo sin fecha o mixto (design D2). Verificar con tests en `SellWithoutDateTest`, a precio 0: en la venta mixta la ida queda como ida; con ambos tramos sin fecha cada pasajero tiene una ida y una vuelta; en la venta con fecha la ida queda como ida y la vuelta como vuelta.

## 2. Lectura solo por marca

- [x] 2.1 Reescribir `isReturnLeg()`, `isPartOfRoundTrip()` y `getDisplayPriceAttribute()` de `Ticket` según design D3. Verificar con tests: una ida mixta con precio mayor a 0 es parte de ida y vuelta; una ida a precio 0 no es vuelta y muestra su propio precio; la vuelta muestra el precio de su ida.
- [x] 2.2 Cambiar `EditTicket::isReturnLegWithSharedPrice()` para que use `isReturnLeg()`. Verificar con un test en `TicketEditTest` que el precio de una ida a precio 0 se puede editar y que el de la vuelta sigue bloqueado.
- [x] 2.3 Verificar con un test de reprogramación que la ida a precio 0 de una venta mixta se reprograma como ida (alcance ida o ambos) y que su vuelta pendiente se encuentra por la marca.
- [x] 2.4 Correr `SellWithoutDateTest`, `TicketEditTest`, `TicketRescheduleServiceTest`, `RescheduleTicketPageTest`, `ClientPanelTicketsTest`, `PendingTicketPdfTest`, `EmissionAtomicityTest` y `SeatReservationWizardTest`, y corregir lo que dependa del fallback por precio. Todos tienen que pasar.

## 3. Migración de datos

- [x] 3.1 Crear con `php artisan make:migration` la migración de datos de design D4 (backfill de boletos dados de baja y corrección de grupos de H2 con forma exacta; `down()` sin cambios de datos). Verificar con un test que la corre sobre datos armados: un boleto de baja con la convención queda marcado; un grupo con dos vueltas deja como ida el de menor id; un grupo con tres boletos o con una ida existente no cambia.
- [x] 3.2 Correr la migración en la base local y verificar con una consulta que no quedan boletos que cumplan la convención sin marca ni grupos (venta, pasajero) de ida y vuelta sin ida vigente.

## 4. Cierre

- [x] 4.1 Correr `vendor/bin/pint --dirty --format agent` y los tests de las secciones 1 a 3. Todo tiene que pasar sin cambios de formato pendientes.
- [x] 4.2 Verificar que el change valida con `openspec validate marca-tramo-vuelta --strict`.

## Workflow follow-up

- Antes del deploy, correr en producción las consultas del paso 1 del Migration Plan y revisar a mano los grupos que la migración no corrige.
- Archivar con `/opsx:archive` y sincronizar las specs.
- Queda pendiente el change `api-reservas-segura` (H5).
