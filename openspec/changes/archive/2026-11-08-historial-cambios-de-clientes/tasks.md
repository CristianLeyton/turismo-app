# Tasks

## 1. Campo de autor y migración con backfill

- [x] 1.1 Crear la migración que agrega `actor_type` (por defecto `user`) y `client_id` (FK nula a `clients`, `nullOnDelete`) a `ticket_date_changes`, con `down()` y un método público `backfill()` por lotes: `client` donde `user_id` es nulo y enlace por DNI del pasajero (incluidos clientes dados de baja). Verificar con un test que corre `backfill()` sobre filas armadas como las viejas: con usuario → `user`; sin usuario y DNI en el padrón → `client` enlazado; sin usuario y sin coincidencia → `client` sin enlace.
- [x] 1.2 En `TicketDateChange`, agregar las constantes `ACTOR_USER` y `ACTOR_CLIENT`, los campos en `$fillable` y la relación `client()` (que incluye clientes dados de baja). Verificar con un test que un cambio de cliente carga su cliente aunque esté dado de baja.

## 2. Registrar el autor al reprogramar

- [x] 2.1 Sumar `?int $clientId = null` a `TicketRescheduleService::rescheduleForActor()` para guardar `actor_type = client`, `client_id` y `user_id` nulo, y `user` con el `user_id` recibido en los demás casos; pasar el cliente de la sesión desde `ClientTicketAssignmentService::assign()`. Verificar con tests de que la asignación de un cliente queda como cliente enlazado, la de un vendedor queda como usuario con su `user_id`, y que borrar del todo al usuario deja su cambio como `user`. Actualizar los tests de `ClientPanelTicketsTest` que esperan `user_id` nulo para identificar al cliente.

## 3. Texto del autor en el detalle del Historial

- [x] 3.1 Agregar `TicketDateChange::actorLabel()` (usuario con su acceso, "Cliente" con o sin nombre, "Usuario eliminado del sistema" solo para un usuario inexistente), usarlo en `ticket-audit-detail.blade.php` y cargar `dateChanges.client` en `TicketAuditTable`. Verificar con tests de los cuatro casos de la spec (cliente con nombre, cliente sin enlace, usuario, usuario borrado) y que un cambio de cliente no dice "Usuario eliminado del sistema".

## 4. Filtro "Realizado por"

- [x] 4.1 Pasar `TicketAuditTable::actorFilter()` a `actor_type` ("Clientes": reprogramaciones de cliente; "Vendedores": reprogramaciones de usuario, ediciones y eliminaciones) y ocultarlo y neutralizarlo con "Panel de clientes activo" apagado, incluso con un valor guardado en la sesión. Verificar actualizando los tests existentes de `TicketAuditResourceTest` y con nuevos: usuario borrado del todo cuenta como vendedor y no como cliente, un boleto con cambios de ambos sale en los dos filtros, el filtro no existe con el panel apagado o sin configurar, un valor guardado con el panel apagado no filtra ni muestra indicador, y vuelve al encenderlo.

## 5. Cierre

- [x] 5.1 Correr `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter="TicketAudit|ClientPanel|TableRowClick"`; correr la migración en una base con filas viejas (`php artisan migrate`) y verificar el resultado con una consulta de solo lectura.

## Workflow follow-up

- Preguntar si se corre la suite completa antes de archivar.
- Desplegar la migración antes que el código y archivar el cambio cuando se haya probado en el navegador.
