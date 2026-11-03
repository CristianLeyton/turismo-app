# Tasks

## 1. Modelo y migración

- [x] 1.1 Crear la migración de la tabla `lineas` (id, name, timestamps) y de las columnas `buses.linea_id` (nullable, `nullOnDelete`) e `buses.is_reinforcement` (boolean, default false)
- [x] 1.2 Crear el modelo `Linea` con factory y seeder, siguiendo las convenciones de los modelos existentes
- [x] 1.3 Agregar a `Bus` la relación `linea()` y el método `busIdsInSameLine()` que devuelve `[id]` si no tiene línea, o los ids de la línea
- [x] 1.4 Test: un colectivo sin línea devuelve solo su id; con línea devuelve los ids de todos los de la línea

## 2. Administración

- [x] 2.1 Crear el recurso Filament de Líneas dentro del clúster de ubicaciones o de colectivos, según la convención del proyecto
- [x] 2.2 Agregar al formulario de colectivos los campos "Línea" y "Es refuerzo"
- [x] 2.3 Test del recurso: crear y editar una línea, y asignar colectivos

## 3. Búsqueda de venta

- [x] 3.1 `TicketForm::originOptions()`: tomar las paradas de todas las rutas activas de la línea del colectivo, sin duplicar localidades, numeradas con la posición en su primera ruta
- [x] 3.2 `TicketForm::destinationOptions()`: agrupar las paradas posteriores al origen por sentido (última parada de la ruta), con título "Hacia {parada final}", sin duplicar localidades dentro de cada grupo y con la numeración de la ruta de su sentido
- [x] 3.3 Opciones de horario y `autoSelectedScheduleId()`: filtrar por `busIdsInSameLine()` y agregar el nombre del colectivo a la etiqueta del horario
- [x] 3.4 Selector "Colectivo": listar solo los colectivos con `is_reinforcement = false`
- [x] 3.5 Al emitir la venta, el viaje y el asiento se crean sobre el colectivo del horario elegido, no sobre el del selector
- [x] 3.6 Tests de la venta: horario de refuerzo visible desde el regular; colectivo sin línea sin cambios; el boleto queda en el colectivo del horario

## 4. Verificación con datos de producción

- [x] 4.1 Con una copia de `turismo_produccion`, aplicar la migración y comprobar que ningún boleto, asiento, viaje ni venta cambia (conteos y sumas antes y después)
- [x] 4.2 Con la copia, asignar la línea "Oran - Salta" al regular y al refuerzo, y verificar en una venta de prueba que aparece el horario del refuerzo
- [x] 4.3 Verificar que una venta de un colectivo sin línea produce exactamente los mismos horarios, origen y destino que antes

## 5. Despliegue

- [x] 5.1 Desplegar la migración y el código sin asignar líneas
- [x] 5.2 Asignar líneas y marcar los refuerzos desde Filament, y verificar en una venta real de prueba antes de usarlo con clientes

## 6. Reprogramación de tramos sin fecha

- [x] 6.1 Revisar `TicketRescheduleForm` y `TicketRescheduleService`: hoy un tramo sin fecha conserva el `bus_id` del selector y solo acepta horarios de ese colectivo. Decidir si la reprogramación usa `busIdsInSameLine()` como la venta. Esto cambia el requisito de reprogramación, así que requiere confirmación antes de implementar
- [x] 6.2 Si se aprueba, aplicar el filtro por línea en la reprogramación, mostrar el colectivo del horario y agregar tests: horario de refuerzo disponible para un tramo sin fecha del regular; colectivo sin línea sin cambios; el asiento se valida en el colectivo del horario
- [x] 6.3 Verificar en la copia de producción una reprogramación de un tramo sin fecha, con conteos antes y después

## Workflow follow-up

- Archivar el change con `/opsx:archive` después de desplegar y verificar en producción
