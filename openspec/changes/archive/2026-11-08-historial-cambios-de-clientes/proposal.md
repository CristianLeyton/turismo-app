# Proposal

## Why

Cuando un cliente elige la fecha de su boleto desde su panel, el cambio entra al Historial del admin, pero sin autor: hoy un cambio "de cliente" es simplemente una reprogramación con `user_id` nulo. Eso es ambiguo, porque la clave foránea es `nullOnDelete` y borrar del todo a un usuario también deja sus cambios sin autor, y el detalle del Historial dice en los dos casos "Usuario eliminado del sistema", lo que es falso para un cliente. Además, el personal no puede separar lo que hicieron los clientes de lo que hizo el equipo.

## What Changes

- Cada reprogramación guarda **quién la hizo** con un campo propio: un usuario del panel o un cliente (con su referencia, si existe). Deja de inferirse de `user_id` nulo.
- Una **migración** agrega el campo y completa las filas existentes: las reprogramaciones sin usuario se asumen de clientes (hasta hoy el único otro origen era un usuario borrado), y se enlaza el cliente cuando se lo puede deducir por el DNI del pasajero.
- Al reprogramar desde el panel de clientes se registra al cliente como autor; al hacerlo desde el admin, al usuario, como hasta ahora.
- El **detalle del Historial** dice "Realizado por: Cliente" (con su nombre, si lo hay) cuando lo hizo un cliente, y "Usuario eliminado del sistema" solo cuando fue un usuario que ya no existe.
- La tabla del Historial gana el filtro **"Realizado por"** con "Clientes" y "Vendedores" (sin elegir: todo el historial). "Vendedores" son los cambios de usuarios de cualquier rol: reprogramaciones, ediciones y eliminaciones. "Clientes" son las reprogramaciones hechas por clientes. Un boleto con cambios de ambos sale en los dos filtros.
- El filtro **solo existe con "Panel de clientes activo" encendido**. Apagado, no se muestra ni se aplica, aunque haya quedado un valor guardado en la sesión.
- El filtro ya está escrito en el código (`TicketAuditTable::actorFilter`) sin OpenSpec y usando `user_id` nulo; este cambio lo pasa al campo nuevo y le suma la condición del panel.

## Capabilities

### New Capabilities
- `ticket-audit-actor`: quién hizo cada cambio del Historial de boletos (usuario o cliente), cómo se lo muestra en el detalle y cómo se filtra la lista por "Realizado por".

### Modified Capabilities
<!-- Ninguna: ticket-edit-history no cambia; su filtro "Editado por" sigue igual. -->

## Impact

- Base de datos: una migración sobre `ticket_date_changes` (campo del tipo de autor y referencia opcional al cliente) con backfill.
- Código: `app/Models/TicketDateChange.php`, `app/Services/TicketRescheduleService.php` (el autor cliente), `app/Services/ClientTicketAssignmentService.php`, `app/Filament/Resources/TicketAudit/Classes/TicketAuditTable.php` (filtro y eager loading) y `resources/views/filament/resources/ticket-audit/ticket-audit-detail.blade.php`.
- Tests: `tests/Feature/TicketAuditResourceTest.php` (los tests del filtro con `user_id` nulo pasan a usar el campo) y nuevos para la migración, el autor al reprogramar y la visibilidad del filtro.
- Sin dependencias nuevas ni cambios de permisos. Las reprogramaciones nuevas hechas por un usuario siguen guardando `user_id`.
