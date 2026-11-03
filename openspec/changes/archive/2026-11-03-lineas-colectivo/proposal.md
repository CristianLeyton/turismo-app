# Proposal

## Why

Algunas líneas tienen un colectivo regular y uno o más colectivos de refuerzo con las mismas paradas. Hoy la búsqueda de horarios al vender se hace por colectivo, así que los horarios del refuerzo nunca aparecen cuando el vendedor elige el colectivo regular. Hay que agrupar esos colectivos en una línea para que la búsqueda cubra todos, sin cambiar lo que el vendedor ya conoce ni los datos de las ventas existentes.

## What Changes

- Se introduce el concepto de **Línea**: un agrupador de colectivos que comparten recorrido. Un colectivo puede pertenecer a una línea o no pertenecer a ninguna.
- Un colectivo sin línea se comporta exactamente como hoy: sus horarios, paradas y destinos se buscan solo por él.
- Los colectivos de refuerzo conservan su nombre (por ejemplo, "Oran - Salta (Refuerzo)") y no se renombran ni se reasignan boletos, asientos o viajes.
- En el formulario de venta, el selector "Colectivo" sigue existiendo, con su misma etiqueta y posición, pero solo lista los colectivos regulares de cada línea (los que no son refuerzo).
- Al elegir origen, destino y horario, se consideran todos los colectivos de la línea del colectivo elegido. Cada opción de horario muestra el nombre de su colectivo, por ejemplo `14:00 · Oran - Salta (Refuerzo)`.
- El asiento, el boleto y el viaje se crean sobre el colectivo del horario elegido, no sobre el colectivo del selector.
- Las líneas se administran desde Filament y cada colectivo se asigna a una línea desde su formulario.
- **Sin cambios de datos**: la migración es aditiva (tabla `lineas` y columna `linea_id` nullable en `buses`). No se modifica ninguna venta, boleto, asiento ni viaje existente.

## Capabilities

### New Capabilities

- `linea-colectivo`: agrupación de colectivos en líneas que comparten recorrido, con la regla de que un colectivo sin línea se busca solo por sí mismo.

### Modified Capabilities

- `ticket-sale`: los horarios de ida (y el selector de colectivo) pasan a considerar la línea del colectivo elegido. El requisito "Colectivo obligatorio" y el de "Horarios ofrecidos para el tramo de ida" cambian en ese sentido.

## Impact

- **Base de datos**: nueva tabla `lineas`, columna `linea_id` nullable en `buses`. Migración aditiva, sin reescritura de filas.
- **Venta de boletos** (`app/Filament/Resources/Tickets/Schemas/TicketForm.php`): las consultas de origen, destino y horario dejan de filtrar solo por `bus_id` y pasan a usar los colectivos de la línea. La etiqueta de cada horario incluye el nombre del colectivo.
- **Administración**: nuevo recurso de Líneas y selector de línea en el formulario de colectivos.
- **Sin impacto esperado** en reprogramación, asientos (`seat-reservation`), venta de clientes ni boletos ya emitidos, porque operan sobre el colectivo del viaje.
- **Producción**: impacto nulo en datos existentes, verificado con una copia de `turismo_produccion` (las paradas de cada par de rutas coinciden y en producción los refuerzos son colectivos separados).
