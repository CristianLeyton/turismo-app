# Proposal

## Why

Hoy, para armar o corregir una ruta hay que pasar por tres pantallas separadas (Rutas, Paradas y Horarios del cluster Destinos) y entender conceptos de la base de datos: un campo numérico "orden", un "horario de referencia" contra el que se miden las horas, columnas técnicas (`created_at`, `deleted_at`) y errores crudos cuando se repite una localidad o un orden. Una persona no técnica no puede editar una ruta, sus paradas y sus horarios sin ayuda, y además no se entera de que sus cambios mueven las horas de boletos ya vendidos.

## What Changes

- Nueva pantalla de edición por ruta con pestañas **Datos**, **Recorrido** y **Horarios**: todo lo de una ruta se edita en un solo lugar. Al crear una ruta se pasa directo a esa pantalla.
- **Recorrido**: paradas en orden, reordenables arrastrando (se conserva la regla actual de horas pegadas a la posición), agregar parada al final sin escribir un número de orden, elegir la localidad con buscador y poder crear una localidad nueva sin salir.
- **Horas de las paradas**: se cargan como hora del reloj del **primer horario activo** de la ruta (sin el selector "horario de referencia"), y se muestra cómo queda en los demás horarios. Si la ruta no tiene horarios, el recorrido pide cargar uno primero.
- **Validaciones con mensajes en lenguaje simple**: localidad repetida en el recorrido, llegada posterior a la salida en la misma parada, parada que sale antes que la anterior.
- **Aviso de boletos vendidos**: antes de guardar un cambio que mueve horas (horario, hora de parada, orden o eliminación de una parada) se avisa cuántos boletos de viajes que todavía no partieron van a ver la hora nueva y se pide confirmación.
- Un horario con boletos en viajes que todavía no partieron no se puede eliminar: se ofrece desactivarlo.
- **Listado de rutas** más legible: recorrido resumido (origen → destino), cantidad de paradas y de horarios, y alerta visible cuando una ruta activa no tiene paradas u horarios. Se ocultan columnas técnicas en el cluster.
- **BREAKING** (navegación): los menús sueltos "Paradas" y "Horarios" dejan de aparecer en el cluster Destinos; se accede desde cada ruta.

Fuera de alcance: cambiar el modelo de datos (las paradas siguen guardando minutos desde la salida, compartidos por todos los horarios), horas distintas por horario, días de servicio, colectivos y líneas.

## Capabilities

### New Capabilities
- `route-configuration`: cómo se configura una ruta (datos, recorrido de paradas y horarios) desde el panel, con sus validaciones y avisos.

### Modified Capabilities
<!-- ninguna: no cambian requisitos de venta, reprogramación ni días de servicio -->

## Impact

- `app/Filament/Clusters/Locations/Resources/Routes/` (nueva página de edición, relation managers, listado).
- `app/Filament/Clusters/Locations/Resources/RouteStops/` y `Schedules/` (se ocultan de la navegación; su formulario y tabla se reutilizan dentro de la ruta; la lógica de reordenado se mueve a un lugar compartido).
- `app/Filament/Clusters/Locations/Resources/Locations/` (creación rápida de localidades desde el recorrido).
- Tests: `tests/Feature/RouteStopsReorderTest.php`, `tests/Feature/RoutesTableFilterTest.php` y tests nuevos para la pantalla de ruta.
- Sin migraciones ni cambios en ventas, boletos o viajes. Los permisos existentes (`routes.*`, `route_stops.*`, `schedules.*`, `locations.*`) siguen gobernando cada parte.
