# Design

## Context

- `ticket_edits` guarda una fila por campo cambiado, con `ticket_id`, `field`, `old_value`, `new_value`, `reason`, `user_id` y `created_at`. `EditTicket::applyChanges()` la escribe siempre con el boleto desde donde se editó.
- Los datos del pasajero (`passengers`) son compartidos: la ida y la vuelta de una venta de ida y vuelta apuntan al mismo `passenger_id`. Los datos del menor viven en los pasajeros hijos de ese pasajero. Precio, método de pago, mascotas y observación son del boleto.
- El historial se muestra con `TicketEditHistory::groups()` en `tickets/partials/edit-history.blade.php`, desde el infolist de "Ver boleto" y desde el detalle de Auditoría (`ticket-audit-detail.blade.php`). Ambos leen `$record->edits`.
- La Auditoría (`TicketAuditResource::getEloquentQuery()`) lista boletos eliminados, reprogramados o con `edits`. `TicketAuditTable` tiene filtros de estado, N° de boleto y pasajero, con `persistFiltersInSession()` y `filtersFormColumns(3)`.

## Goals / Non-Goals

**Goals:**
- Un único lugar que defina "las ediciones visibles de un boleto", usado por el historial, la lista y los filtros.
- Filtros que encuentran el caso del 4/11 sin consultar la base.

**Non-Goals:**
- Cambiar cómo se guardan las ediciones (no se duplican filas en el boleto hermano ni se agrega columna `passenger_id`).
- Alertas automáticas de ediciones sospechosas.
- Exportar la Auditoría.

## Decisions

### 1. Campos compartidos
Constante `TicketEdit::PASSENGER_FIELDS = [first_name, last_name, dni, phone_number, child_name, child_dni]`. `travels_with_child` queda fuera: es una marca del boleto, cada tramo tiene la suya.

### 2. Ediciones visibles: consulta, no duplicación
Nuevo scope `TicketEdit::visibleFor(Ticket $ticket)` (o método en `TicketEditHistory`):

```
ticket_edits.ticket_id = :ticket
OR (ticket_edits.field IN PASSENGER_FIELDS
    AND ticket_edits.ticket_id IN (SELECT id FROM tickets
                                   WHERE passenger_id = :passenger AND id <> :ticket))
```

Se incluyen los boletos hermanos eliminados (`withTrashed`): la edición ocurrió igual. Alternativa descartada: escribir la edición también en el boleto hermano. Arregla solo lo nuevo (las ediciones del 4/11 quedarían sin arreglar), duplica filas y complica los conteos.

Para el detalle (un boleto por vez) se hace una consulta por boleto. Para la lista (N boletos) se usa la versión en SQL como `whereExists`, sin cargar ediciones de más.

### 3. Origen de cada edición
`TicketEditHistory::groups()` ya agrupa por usuario + momento + motivo. Se agrega `ticket_id` a la clave y el grupo expone `from_ticket_id` (null si es propia). El Blade muestra "Editado desde el boleto N° X" con un link a "Ver boleto" de ese número.

### 4. Lista y filtro "Editados"
`getEloquentQuery()` y el filtro de estado `edited` cambian `whereHas('edits')` por un `whereExists` sobre "ediciones visibles" (scope de Builder `Ticket::scopeWithVisibleEdits()`), así los dos usan la misma condición.

### 5. Filtros de edición en una sola subconsulta
Un `Filter::make('edit_search')` con tres campos (`fields` multiple Select, `user_id` Select, `from`/`to` DatePicker). La `query()` arma UN solo `whereExists` con todas las condiciones elegidas sobre la misma fila de `ticket_edits` (requisito "Filtros combinados": la misma edición cumple todo). Al tener una sola fila por campo, la condición de campo y usuario/fecha se cumplen en la misma fila, que es lo que se busca.

- Opciones de "Campo cambiado": las de `EditTicketForm::fieldLabel()`, para no duplicar etiquetas.
- "Editado por": usuarios del panel (`User::query()->orderBy('name')`), `searchable()`.
- Fechas: `whereDate` con la zona de la app; `displayFormat('d/m/Y')`.
- `indicateUsing()` con textos tipo "Campo: DNI, Nombre", "Editado por: Julian", "Editado: 04/11/2026 – 04/11/2026".
- El filtro ocupa una fila propia (`columnSpanFull()`, 1/2/4 columnas según el ancho) y `filtersFormColumns` queda en 3: los filtros existentes no cambian de tamaño.

### 6. Rendimiento
Índices existentes: `ticket_edits(ticket_id, created_at)` y `ticket_edits(field)`. La subconsulta de hermanos usa `tickets.passenger_id`; verificar que tenga índice (FK). Si no, documentarlo como riesgo, sin migración en este cambio.

## Risks / Trade-offs

- [Un pasajero reutilizado en muchos boletos] → hoy cada venta crea su pasajero; si en el futuro se reutiliza, el historial mostraría ediciones de boletos de otras ventas. Mitigación: limitar los hermanos a la misma venta (`sale_id`). **Se aplica esa restricción**: hermano = mismo `passenger_id` y misma `sale_id`.
- [Consulta pesada en la lista] → la tabla tiene paginación de 50; el `whereExists` usa índices. Verificar con la base local (~39k boletos).
- [Tests de Auditoría existentes] → el filtro de estado y la consulta base cambian; correr `TicketAuditResourceTest`.

## Migration Plan

Sin migraciones. Deploy: `git pull` y `php artisan view:clear`.
