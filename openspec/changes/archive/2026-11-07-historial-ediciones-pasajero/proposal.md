# Proposal

## Why

El 4/11 un vendedor cargó los datos reales de un grupo que había vendido a nombre de una sola persona, editando los boletos de ida. Como la ida y la vuelta comparten el mismo pasajero, el nombre también cambió en la vuelta, pero el historial de la vuelta aparecía vacío: las ediciones se guardan en el boleto desde donde se hicieron. Además, la Auditoría no permite buscar ediciones por qué se cambió, quién lo hizo o cuándo, así que encontrar un caso así obligó a consultar la base a mano.

## What Changes

- **Historial compartido**: el historial de ediciones de un boleto (en "Ver boleto" y en el detalle de Auditoría) muestra también los cambios de datos del pasajero hechos desde otro boleto del mismo pasajero (su ida o su vuelta), indicando desde qué boleto se hizo cada edición.
- **El boleto hermano aparece en Auditoría**: un boleto cuyo pasajero fue editado desde otro boleto cuenta como editado (aparece en la lista y en el filtro "Editados").
- **Filtros nuevos en Auditoría**:
  - "Campo cambiado": DNI, Nombre, Apellido, Teléfono, Precio, Método de pago, etc. (selección múltiple).
  - "Editado por": el usuario que hizo la edición.
  - "Fecha de edición": rango desde/hasta.
  Los tres se combinan entre sí y con los filtros existentes.
- Sin migraciones: se usa lo que ya guarda `ticket_edits`.

## Capabilities

### New Capabilities

- `ticket-edit-history`: qué ediciones muestra el historial de un boleto (propias y compartidas con su boleto hermano) y cómo se buscan ediciones en la Auditoría.

### Modified Capabilities

_Ninguna._

## Impact

- `app/Support/TicketEditHistory.php`: obtener las ediciones visibles de un boleto (propias + de datos del pasajero desde boletos hermanos) y marcar el origen.
- `resources/views/tickets/partials/edit-history.blade.php`: aviso "Editado desde el boleto N° X".
- `app/Filament/Resources/Tickets/Schemas/TicketInfolist.php` y `resources/views/filament/resources/ticket-audit/ticket-audit-detail.blade.php`: usar las ediciones visibles.
- `app/Filament/Resources/TicketAudit/TicketAuditResource.php` y `Classes/TicketAuditTable.php`: consulta base y filtros nuevos.
- Tests: `tests/Feature/TicketAuditResourceTest.php` y uno nuevo para el historial compartido.
