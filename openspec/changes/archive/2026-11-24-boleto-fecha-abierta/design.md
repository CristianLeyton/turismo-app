# Design

## Context

El estado "sin fecha" ya existe: un boleto con `trip_id` NULL y `seat_id` NULL es pendiente (`Ticket::isPendingDate()`). Lo producen la venta sin fecha (permiso `tickets.vender_sin_fecha`, exige pasajero en el padrón) y lo consumen:

- `TicketRescheduleService::rescheduleForActor()`, que asigna fecha a un pendiente o mueve un fechado. Lo usan el personal (`RescheduleTicket`) y el cliente (`ClientTicketAssignmentService`).
- `MyTickets` del panel de clientes, que muestra como "Pendientes" los boletos con `trip_id` NULL.
- El PDF, el link público y el detalle, que ya muestran un boleto sin fecha.

La ocupación de asientos se deriva de los tickets activos (`trip_id` + `seat_id`) más las reservas temporales, así que poner esos campos en NULL libera el asiento sin más pasos.

La convención de ida y vuelta (dos tickets del mismo `sale` + `passenger`; la ida guarda `return_trip_id`) y las ventas mixtas definen cómo queda cada combinación: con la vuelta pendiente, la ida no tiene `return_trip_id`; con la ida pendiente, la ida sigue ligada al viaje de vuelta.

`ticket_date_changes` registra cada tramo movido con `from_*`/`to_*` (todos nullable, con `nullOnDelete`) y el autor (`user_id`, `actor_type`, `client_id`).

## Goals / Non-Goals

**Goals:**
- Una sola operación del servicio que lleva uno o dos tramos al estado pendiente, con las mismas garantías de concurrencia y auditoría que la reprogramación.
- Que el resto del sistema (panel de clientes, PDF, lista de pasajeros, reasignación posterior) funcione sin cambios, reconociendo el boleto como un pendiente más.

**Non-Goals:**
- Que el cliente abra la fecha por su cuenta.
- Distinguir en el panel de clientes un boleto "abierto" de uno vendido sin fecha.
- Cambiar precio, venta o saldos.

## Decisions

### 1. Método propio en `TicketRescheduleService`, no un modo de `rescheduleForActor`

Se agrega `openDate(Ticket $ticket, array $data): array` (scope, motivo, contraseña), con su propia validación y transacción, reusando los helpers privados: `assertPasswordConfirmed`, `findReturnLegTicket`, `findOutboundTicket`, `actorAttributes` y el lock de tickets.

*Alternativa:* un flag `open_date` dentro de `rescheduleForActor`. Se descarta porque ese método está armado alrededor de resolver viajes, horarios, asientos, días de servicio y el orden ida → vuelta, que en este caso no aplican. Meter el flag llenaría de ramas un método crítico que también usa el panel de clientes.

Solo el personal llama a este método (actor = usuario logueado). No se expone al cliente.

### 2. Permisos: `tickets.reschedule` y `tickets.vender_sin_fecha`, con doble control

La UI muestra la opción solo con los dos permisos. El servicio valida los dos antes de tocar nada. Para "al menos un rol tiene asignado el permiso", se reusa el mismo criterio que la venta sin fecha, así un superadministrador no ve la opción si ningún rol la tiene. No hay cambios en `Permissions` ni en los roles por defecto.

### 3. Orden de las validaciones del servicio

1. Boleto no eliminado y, releído desde la base, con fecha en el tramo pedido (un tramo ya pendiente se omite; si no queda ningún tramo para abrir, se rechaza con "El boleto ya está sin fecha").
2. Permisos.
3. Contraseña, si la configuración la exige (`assertPasswordConfirmed`).
4. Padrón: el DNI del pasajero debe existir en `clients`. Mensaje: "El pasajero no está registrado en el padrón de clientes. Agregalo desde Clientes y volvé a intentar." Se aplica siempre, con el panel de clientes activado o no, igual que en la venta sin fecha.
5. Alcance: `outbound` o `both`. Un boleto de vuelta siempre fuerza `outbound`; `both` exige ida y vuelta.

### 4. Mutación dentro de la transacción

Para cada tramo a abrir:
- `lockForUpdate` del ticket.
- UPDATE condicionado al `trip_id` original (como `moveLeg`) que pone `trip_id` y `seat_id` en NULL. Si no afecta filas, se rechaza con "El boleto cambió mientras se procesaba. Reintentá."
- Si el tramo abierto es la vuelta, el boleto de ida queda con `return_trip_id` NULL (convención de vuelta pendiente). Si se abre solo la ida, su `return_trip_id` se mantiene (convención de ida pendiente).
- Una fila en `ticket_date_changes` con `kind = open_date`, `from_*` del tramo, `to_*` NULL, `reason` y el autor.

No hace falta liberar `SeatReservation`: el boleto no tenía reserva temporal, tenía asiento vendido.

El mismo UPDATE copia a `tickets.bus_id` el colectivo del viaje, si el boleto no lo tenía. Un boleto con fecha toma el colectivo de su viaje y no lo guarda. Uno sin fecha lo acarrea él, como en la venta sin fecha, y con eso la reprogramación y el panel de clientes ofrecen solo horarios de la misma línea. Sin esta copia, el boleto abierto quedaría sin colectivo y la fecha nueva se podría elegir en cualquier línea.

### 5. Columnas `kind` y `reason` en `ticket_date_changes`

Migración nueva:
- `kind` string(20), NOT NULL, default `reschedule`. Las filas existentes quedan `reschedule` por el default.
- `reason` text nullable.

Constantes `TicketDateChange::KIND_RESCHEDULE` y `KIND_OPEN_DATE`. La reprogramación normal pasa `kind` explícito.

*Alternativa:* deducir la fecha abierta de `to_trip_id` NULL. Se descarta porque `nullOnDelete` también deja `to_trip_id` en NULL si se borra el viaje de destino, y el registro quedaría ambiguo.

### 6. UI de "Reprogramar boleto"

- Un selector al inicio del formulario, "Nueva fecha" / "Dejar con fecha abierta", visible solo con los dos permisos y si el boleto tiene fecha. Con "fecha abierta" se ocultan y dejan de ser obligatorios fecha, horario y asientos. Se mantienen el alcance (si corresponde), la contraseña (si corresponde) y se agrega el "Motivo" opcional (textarea con límite de longitud).
- `confirmReschedule` deriva según el modo: `openDate()` o `reschedule()`. El manejo de errores (notificación persistente, conservar el form) es el mismo.
- Antes de pasar a fecha abierta se liberan las reservas temporales de asiento que la página haya tomado en esa sesión, como hace hoy al abandonar la página.
- Hay que respetar las convenciones del skill `filament-ui`.

### 7. Entrega

Mismo flujo que reprogramar: PDF en sesión si la descarga automática está activa, o `TicketDelivery::flashForReschedule` con un título propio. Se agrega un parámetro o variante para el título "Boleto N°{id} quedó con fecha abierta". El PDF ya muestra el boleto sin fecha.

### 8. Historial

`ticket-audit-detail.blade.php`: si `kind = open_date`, se muestra "Dejó el boleto con fecha abierta" con el viaje, horario y asiento de origen, y el motivo si existe, en vez del bloque "de → a". El filtro de reprogramados (`whereHas('dateChanges')`) y "Realizado por" ya lo cuentan sin cambios.

## Risks / Trade-offs

- [Un cliente puede pedir que le abran la fecha para después cambiarla él mismo] → Es lo que se busca. La anticipación mínima, los días de servicio y una persona por viaje siguen aplicando.
- [Abrir solo la ida deja una vuelta fechada que puede quedar antes de la nueva ida] → La validación del orden ida → vuelta ya existe en el núcleo y la aplica al asignar la nueva fecha, tanto para el personal como para el cliente.
- [Carrera: alguien vende el asiento liberado mientras otro reprograma el mismo boleto] → El UPDATE está condicionado al `trip_id` original dentro de la transacción. La segunda operación falla con "cambió mientras se procesaba".
- [Interacción con `reservas-boletos-clientes`] → Un boleto con fecha abierta es un ticket, no una reserva. Si ese cambio introduce reservas sin fecha, deben vivir en su propia tabla y no contarse como pendientes de "Mis boletos".
- [La lista de pasajeros de un viaje pasado pierde al pasajero] → Es esperable. El rastro queda en el Historial con el viaje de origen.

## Migration Plan

1. Migración aditiva (`kind` con default, `reason` nullable). No requiere backfill.
2. Deploy del código. No hay permisos nuevos, así que no hace falta sincronizar permisos.
3. Rollback: revertir el código. La migración se puede bajar sin pérdida para las reprogramaciones normales. Las filas `open_date` quedarían como reprogramaciones a un viaje vacío.
