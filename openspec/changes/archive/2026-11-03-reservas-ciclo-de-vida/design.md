# Diseño: ciclo de vida de las reservas temporales

## Context

La motivación está en `proposal.md`. Los hallazgos de origen (H1, H3, H6, H7 y H10) están documentados y verificados en `openspec/changes/archive/2026-11-02-baseline-venta-y-reservas/design.md`.

Estado actual que condiciona el diseño:

- Las reservas se identifican por el identificador de sesión de Laravel (`user_session_id`). Todas las pestañas de una sesión comparten reservas, y `reserve` reemplaza las reservas de la sesión **por viaje**. Esto no cambia: el usuario eligió liberar por venta, no aislar por pestaña.
- El selector (`resources/views/tickets/seat-selector.blade.php`) también se usa en la reprogramación (`TicketRescheduleForm`) y en la asignación de clientes (`ClientTicketAssignForm`), siempre con `enable_reservation`. Cualquier cambio en el selector o en la API afecta a los tres flujos.
- En el paso 1, al cambiar origen, destino, fecha u horario se borra `trip_id` (y `return_trip_id`). Por eso, en `afterValidation` ya no se conoce el viaje anterior. La variable `static $lastSearchHash` (TF:61) arranca en `null` en cada pedido y nunca detecta el cambio (H3).
- La cuenta regresiva es solo del navegador: `window.seatReservationGlobalExpiresAt` se fija al elegir un asiento y se corrige con `updateReservationTime()` cuando `reserve` responde. El `reservation_expires_at` que manda `TicketForm` en `viewData` no se usa.
- El panel tiene `unsavedChangesAlerts()`. El selector libera en `unload` solo si antes hubo `beforeunload`.
- `SeatReservation::reserveSeats` reconoce el choque de unicidad buscando el nombre del índice `unique_trip_seat_reservation` en el mensaje. SQLite nombra las columnas, así que en los tests sale como 500 (H1).

## Goals / Non-Goals

**Goals:**
- Que ninguna acción de una venta libere reservas de otra venta de la misma sesión en viajes distintos.
- Que las reservas duren mientras el vendedor trabaja y venzan solas cuando abandona la venta.
- Que una reserva vencida nunca bloquee a otro vendedor, sin depender del cron.
- Un único cálculo de "asientos libres" para el paso 1 y la reserva.

**Non-Goals:**
- Aislar pestañas que venden **el mismo viaje** en la misma sesión: siguen compartiendo reservas, como hoy.
- Autenticación de la API, exposición del identificador de sesión en el DOM y mensajes internos en los errores (H5, change `api-reservas-segura`).
- Cambiar la disponibilidad que muestran la reprogramación, la asignación de clientes o el listado de viajes (`remainingSeats` sigue como está para ellos).

## Decisions

### 1. Liberar por viajes, no por sesión

`POST /release` y `POST /keep-alive` aceptan `trip_ids` opcional (array de enteros). Con `trip_ids` actúan solo sobre las reservas de la sesión en esos viajes. Sin ese parámetro se comportan como hoy, para que ningún cliente viejo se rompa. En el modelo: `SeatReservation::releaseBySession($sessionId, ?array $tripIds = null)` y `extendReservationTime($sessionId, $minutes, ?array $tripIds = null)`.

Cada punto de liberación pasa a acotarse:

| Punto | Hoy | Después |
|---|---|---|
| `CreateTicket::__destruct` | borra todas las de la sesión en cada carga de página | **se elimina** |
| Cancelar (`getCancelFormAction`) | todas las de la sesión | viajes de la venta |
| Emisión con error (`handleRecordCreation`, catch) | todas las de la sesión | viajes de la venta |
| Emisión exitosa (después del commit) | todas las de la sesión | viajes de la venta |
| Rechazo por asiento tomado (CT:183, 240) | todas las de la sesión | viajes de la venta |
| Cierre de página (selector, `unload`) | todas las de la sesión | el viaje de ese selector |
| Vencimiento de la cuenta regresiva (selector) | todas las de la sesión | el viaje de ese selector |

"Viajes de la venta" = `trip_id`, `return_trip_id` y los viajes de reserva del tramo (decisión 3), sin nulos ni repetidos. Lo resuelve un método privado de `CreateTicket` (`saleTripIds()`) a partir de `$this->data`.

En el selector, `releaseReservations()` manda `trip_ids: [this.tripId]`. Con ida y vuelta hay dos selectores y cada uno libera su viaje. El evento global `seatReservationExpired` hace que cada selector libere el suyo, en lugar de que el que disparó el evento libere todo. El cierre se escucha con `pagehide` en lugar de `unload`: Chrome ya no ejecuta los listeners de `unload` (verificado en Chrome 156: `document.featurePolicy.allowsFeature('unload')` es `false`), así que el cierre de página nunca liberaba. `pagehide` solo llega si el vendedor confirma la salida en el aviso de `unsavedChangesAlerts()`. El `fetch` usa `keepalive: true` para que el pedido sobreviva al cierre. Los listeners de `window` (`beforeunload`, `pagehide`, `seatReservationExpired`, `refresh-seats`) se guardan por componente y se quitan en `destroy()`: si sobrevivieran a un re-render, un selector ya destruido seguiría liberando su viaje anterior, que puede ser el de otra venta de la misma sesión (se vio en la verificación en el navegador).

*Alternativa descartada:* identificador por pestaña (token de venta). Aísla del todo, pero cambia la identidad de la reserva en la API, la emisión, la reprogramación y la asignación de clientes. El usuario eligió el alcance acotado.

### 2. Renovación por actividad desde el navegador

Se borran `startKeepAlive()`, `stopKeepAlive()` y `keepAliveInterval`, que nunca se ejecutaban. En su lugar, el selector escucha la actividad del vendedor en la página (`input`, `change`, `click`, `keydown` sobre el documento) y, **como mucho una vez cada 60 segundos** y solo si tiene asientos elegidos, llama a `keep-alive` con `trip_ids: [this.tripId]`. Con el `expires_at` de la respuesta corrige la cuenta regresiva con `updateReservationTime()`. El límite de 60 segundos es global (`window.seatReservationLastKeepAlive`), así que dos selectores (ida y vuelta) no duplican pedidos por la misma interacción. Cada uno renueva su viaje dentro de esa misma ventana.

Por qué en el navegador y no en el servidor (por ejemplo, en `hydrate()` de Livewire): la mayoría de los campos de pasajeros no son `live`, así que escribir no genera pedidos al servidor. Un vendedor que tipea durante 5 minutos sin salir del campo perdería la reserva. Escuchar eventos del DOM captura cualquier interacción.

La renovación al volver a mostrar el selector (TF:1540, 1836) se mantiene.

Como el selector existe en la reprogramación y la asignación de clientes, esos flujos también renuevan por actividad. Es el comportamiento deseado y no tiene costo extra.

### 3. Cambio de viaje en el paso 1: viaje "de reserva" en un campo oculto

Dos `Hidden` nuevos en el paso 1: `reserved_trip_id` y `reserved_return_trip_id`. Guardan el viaje en el que se eligieron los asientos de cada tramo. En el `afterValidation` del paso 1, después de resolver los viajes:

- Si `reserved_trip_id` no está vacío y es distinto del `trip_id` resuelto, libera las reservas de la sesión en `reserved_trip_id` y limpia `seat_ids` y `passenger_data`.
- Lo mismo para la vuelta, con `reserved_return_trip_id`, `return_seat_ids` y `return_passenger_data`. También libera si la vuelta pasó a "sin fecha" o se desactivó el ida y vuelta.
- Al final, guarda los viajes actuales en los dos campos ocultos.

Como la selección queda vacía antes de mostrar el selector nuevo, la detección de asientos expirados (TF:1476) no encuentra nada que avisar. Así se elimina el falso aviso de "asientos expirados" del hallazgo H3, sin tocar esa lógica. Se borran `static $lastSearchHash` y su bloque.

*Alternativa descartada:* comparar el hash de búsqueda en el estado del formulario. Libera en cambios que no cambian el viaje (por ejemplo, la cantidad de pasajeros) y no dice qué viaje liberar.

### 4. Reserva sobre una vencida: borrar antes de insertar

En `SeatReservation::reserveSeats`, antes del `insert`, eliminar las filas de `(trip_id, seat_id)` de los asientos pedidos con `expires_at <= now()`, de cualquier sesión. Es seguro: una reserva vencida ya no cuenta como ocupación para nadie (requisito "Reservas vencidas no cuentan como ocupación"). El comentario "No limpiar reservas expiradas aquí para evitar eliminar reservas recién creadas" se refería a la limpieza global. Esta solo toca los asientos pedidos y solo las filas vencidas.

El choque de unicidad se reconoce como `SeatConflict` ya hace con los boletos: código 23000 y nombre del índice (MySQL) o `UNIQUE constraint failed` con `seat_reservations.trip_id` y `seat_reservations.seat_id` (SQLite). Se agrega `SeatConflict::isReservationViolation()` en `app/Support/SeatConflict.php`, junto al método existente.

**Cron y scheduler (deuda de H1):** la limpieza periódica sigue a cargo del cron del servidor. Se borran `app/Console/Kernel.php` y `config/schedule.php`, que no se cargan en Laravel 12. No se agrega la tarea a `routes/console.php`: con la decisión 4 la limpieza deja de ser necesaria para la disponibilidad y solo mantiene la tabla chica. Además, programarla sin un `schedule:run` en el servidor sería otra configuración que no se ejecuta.

### 5. Un cálculo de asientos libres para la sesión

Nuevo `Trip::availableSeatsCountForSession(string $sessionId): int`: asientos activos del colectivo del viaje, sin boleto vigente y sin reserva vigente **de otra sesión**. Es la misma regla que `reserve` y `reserveSeatsWithLock`. `TicketForm` lo usa en lugar de `$result['available_seats']` (el `remainingSeats()` de `findOrCreateForBooking`) en los cuatro lugares del paso 1 (TF:125, 262, 804, 918), y en la visibilidad del selector y del aviso de asientos insuficientes de los pasos 2 y 3 (TF:1428-1446, 1588, 1721-1743, 1884), para que el paso 1 y el selector no discrepen.

`remainingSeats()` y `findOrCreateForBooking` no cambian: los usan la reprogramación, la asignación de clientes, `getFullLayoutData` y `CreateTicket::handleSeatReservationConflict`, que quedan fuera de alcance.

## Risks / Trade-offs

- [Dos pestañas de la misma sesión en el mismo viaje se pisan las reservas (`reserve` reemplaza por viaje), y cancelar en una libera las de la otra en ese viaje] → Limitación aceptada al elegir liberar por venta. La verificación final de la emisión sigue protegiendo contra la venta doble.
- [Sin `__destruct`, recargar la página deja reservadas las reservas de la venta anterior hasta 5 minutos] → Vencen solas y, con la decisión 4, no bloquean a nadie después de vencer. Si el vendedor vuelve a elegir en el mismo viaje, `reserve` las reemplaza.
- [El pedido de liberación al cerrar puede perderse] → Se envía en `pagehide` con `keepalive: true`, que Chrome, Edge y Firefox completan aunque la página se cierre. Si se pierde, la reserva vence a los 5 minutos.
- [Que una actividad mínima mantenga reservas indefinidamente] → Solo cuentan `input`, `change`, `click` y `keydown`, no el movimiento del mouse. Una pestaña en segundo plano no genera esos eventos y vence a los 5 minutos.
- [El selector cambia también en la reprogramación y la asignación de clientes] → Esos flujos ganan renovación por actividad y liberación acotada a su viaje. Se agrega una prueba manual de la reprogramación en las tareas.
- [Borrar `Kernel.php` y `config/schedule.php` en un servidor que los use] → No los carga ninguna versión de Laravel 12 (verificado en el baseline con `schedule:list`). El cron llama al comando directamente.

## Migration Plan

- Sin migraciones ni cambios de configuración en el servidor. El cron de `app:cleanup-expired-reservations` sigue igual.
- Deploy normal: código y `npm run build` (cambia un blade). Ver `docs/deploy.md`.
- Rollback: volver al commit anterior. No hay datos que revertir.
