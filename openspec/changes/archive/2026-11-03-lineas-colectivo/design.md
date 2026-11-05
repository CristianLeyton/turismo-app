# Design

## Context

La venta busca origen, destino y horario filtrando por `bus_id` (`TicketForm.php`: `originOptions()`, `destinationOptions()`, las opciones de horario y `autoSelectedScheduleId()`). Los colectivos de refuerzo de producción son colectivos separados con las mismas paradas que su regular (verificado con una copia de `turismo_produccion`). Por eso, sin agrupación, sus horarios no aparecen al vender con el colectivo regular.

Los boletos, asientos y viajes ya apuntan a su colectivo y su viaje. Esa relación no cambia. Ver `proposal.md` para el motivo y `specs/` para el comportamiento esperado.

## Goals / Non-Goals

**Goals**
- Buscar origen, destino y horario sobre todos los colectivos de la línea del colectivo elegido.
- Mostrar el nombre del colectivo en cada horario ofrecido.
- Migración aditiva: ninguna fila existente cambia de valor.

**Non-Goals**
- Renombrar colectivos de refuerzo.
- Cambiar asientos, reserva temporal, boletos, viajes o la reprogramación de boletos.
- Cambiar la búsqueda de horarios de vuelta, que ya admite cualquier colectivo (`ticket-sale`, "Horarios ofrecidos para el tramo de vuelta").

## Decisions

### D1. Línea como tabla propia, no como nombre compartido
Se crea `lineas` (id, name, timestamps) y `buses.linea_id` nullable con `nullOnDelete`.
- Alternativa: agrupar por prefijo del nombre ("Oran - Salta" y "Oran - Salta (Refuerzo)"). Rechazada: depende del texto, y se rompe si alguien renombra un colectivo.
- Alternativa: tabla pivote muchos a muchos. Rechazada: un colectivo pertenece a una sola línea, así que una columna alcanza.

### D2. Colectivo sin línea es su propia línea
Helper `Bus::busIdsInSameLine(): array` que devuelve `[id]` si `linea_id` es null, o los ids de todos los colectivos con esa `linea_id`. Todas las consultas de venta usan ese helper en lugar de `bus_id` directo. Así, sin datos nuevos, el comportamiento es idéntico.

### D3. Regulares en el selector
El selector "Colectivo" lista los colectivos que no son refuerzo. Para distinguirlos se usa una marca explícita en `buses` (por ejemplo `is_reinforcement` boolean default false) y no el texto "(Refuerzo)". Un colectivo sin línea no es refuerzo, así que aparece como hoy.
- Alternativa: filtrar por nombre. Rechazada por la misma razón que D1.
- Riesgo: hay que marcar `is_reinforcement = true` en los refuerzos de producción después del despliegue. Mientras no se haga, aparecerían en el selector, lo que es el comportamiento actual.

### D4. Origen: unión de paradas de la línea
`originOptions()` toma las paradas de la primera ruta activa de cada colectivo de la línea, sin duplicar localidades, numeradas con la posición de su ruta de origen. Hoy usa solo la primera ruta; con la unión no se pierde ninguna parada si un refuerzo llegara a tener una adicional.

### D5. Destino: agrupado por sentido
`destinationOptions()` cambia su alcance a los colectivos de la línea y agrupa las paradas posteriores al origen por **sentido**, no por ruta.
- **Sentido**: la última parada de la ruta por la que se llega al destino. El grupo se titula "Hacia {parada final}", por ejemplo "Hacia Salta" y "Hacia Oran". Su clave es la parada final, así que las rutas regular y de refuerzo con el mismo sentido caen en el mismo grupo.
- **Sin duplicados**: cada localidad aparece una sola vez por grupo. Como un refuerzo tiene las mismas paradas que su regular, no agrega destinos; sus horarios sí se consideran, según D6.
- **Numeración**: cada parada se numera con su posición en la ruta de su sentido. Un mismo destino tiene la misma numeración en el grupo de su sentido, lo que evita números ambiguos cuando el origen está en el medio del recorrido.
- **Origen en el extremo**: queda un solo grupo, como hoy.
- Alternativa descartada: lista plana. Mezcla los dos sentidos cuando el origen está en el medio del recorrido y deja números ambiguos.
- Alternativa descartada: agrupar por ruta. Repite cada destino en cada grupo de regular y refuerzo, y el vendedor no elige la ruta sino el horario.
- Caso no cubierto: si un refuerzo tuviera una parada que el regular no tiene, esa parada aparecería en el grupo de su sentido. Con los datos actuales no pasa.

### D6. Horario: el colectivo sale del horario
La consulta de horarios (`TicketForm.php`, opciones de horario y `autoSelectedScheduleId()`) filtra por `busIdsInSameLine`. El viaje y el asiento se crean sobre `schedule->route->bus_id`, que ya es el flujo actual de la emisión. La etiqueta del horario agrega el nombre del colectivo.

## Risks / Trade-offs

- **[Horario de refuerzo confunde al vendedor]** → la etiqueta incluye el nombre del colectivo y la regla de D3 deja el selector igual que hoy.
- **[Un colectivo marcado mal como refuerzo desaparece del selector]** → el despliegue no marca refuerzos automáticamente. Se marcan a mano desde Filament, y se verifica en la lista de colectivos antes de vender.
- **[Consultas más pesadas]** → cada búsqueda pasa de un colectivo a pocos. Los índices existentes de `routes (bus_id, is_active)` cubren la consulta.
- **[Horarios con la misma hora en regular y refuerzo pierden la preselección]** → aceptado por ahora: el vendedor elige entre dos opciones con el nombre del colectivo. Alternativa a evaluar: preferir el horario del regular cuando hay empate de hora.
- **[Datos de prueba distintos de producción]** → la base de pruebas tiene refuerzos como rutas dentro del mismo colectivo. Las pruebas de la línea se hacen con una copia de producción.

## Migration Plan

1. Desplegar la migración: crea `lineas` y las columnas nuevas, sin datos. No cambia el comportamiento porque todos los colectivos quedan sin línea.
2. Desplegar el código: con colectivos sin línea, la búsqueda es idéntica a la actual.
3. Operación manual, después del despliegue: crear la línea "Oran - Salta", asignar el regular y el refuerzo, marcar el refuerzo con `is_reinforcement`. Repetir para "Tartagal - Salta" y "Don Esteban" si corresponde.
4. Verificar en una venta de prueba que el horario del refuerzo aparece y que el boleto queda en el colectivo del refuerzo.

**Rollback**: quitar la línea de los colectivos (`linea_id = null`) devuelve la búsqueda al comportamiento anterior. La migración puede revertirse porque las columnas son nullable y no hay datos que dependan de ellas.

## Open Questions

- ¿La reprogramación de boletos (`TicketRescheduleService`) debe usar la línea para ofrecer horarios? No cambia la venta, así que puede decidirse después de desplegar.
