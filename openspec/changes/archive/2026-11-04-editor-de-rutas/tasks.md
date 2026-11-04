# Tasks

## 1. Pantalla por ruta

- [x] 1.1 Consultar `search-docs` de Filament v4 sobre relation managers con related resource y pestañas combinadas con el contenido, y anotar en design.md (decisión 2) qué variante se usa
- [x] 1.2 Crear la página `EditRoute` (`php artisan make:filament-page` o el comando de páginas de recurso, con `--no-interaction`), registrarla como `edit` en `RouteResource`, combinar pestañas con `hasCombinedRelationManagerTabsWithContent()` (pestaña "Datos") y redirigir a ella tras crear una ruta desde el listado; verificar con un test nuevo `tests/Feature/RouteEditorTest.php` que crear una ruta redirige a su edición y que la página renderiza para un Super Administrador
- [x] 1.3 Ocultar `RouteStopResource` y `ScheduleResource` de la navegación (`shouldRegisterNavigation = false`) sin quitar sus páginas; verificar con un test que la navegación del cluster no incluye Paradas ni Horarios y que `RouteStopsReorderTest` sigue pasando

## 2. Recorrido

- [x] 2.1 Extraer la lógica de `ManageRouteStops::reorderTable()` a un trait compartido y usarlo desde la página existente; verificar con `php artisan test --compact tests/Feature/RouteStopsReorderTest.php`
- [x] 2.2 Crear `RouteStopsRelationManager` (relación `stops`, pestaña "Recorrido") reutilizando form y tabla de `RouteStopResource`, con el trait de reordenado y arrastre siempre habilitado, sin select de ruta ni filtro de ruta; tests en `RouteEditorTest`: lista paradas en orden, arrastrar conserva horas por posición
- [x] 2.3 Quitar `stop_order` y `reference_schedule_id` del formulario; asignar `stop_order = max + 1` (incluyendo eliminadas) al crear; mostrar en el encabezado "Horas del horario de las HH:MM" y una columna "En otros horarios"; tests: parada nueva queda última, horas se guardan contra el primer horario activo, columna muestra la hora en otro horario y el caso que pasa la medianoche
- [x] 2.4 Estado vacío sin horarios activos: mensaje "Primero cargá al menos un horario para poder indicar a qué hora pasa por cada parada." y acción crear oculta; test con ruta sin horarios
- [x] 2.5 Select de destino con buscador y `createOptionForm` visible solo con `locations.create`; validación de destino repetido ("Esta localidad ya está en el recorrido." y mensaje de restaurar si choca con una eliminada); tests: crear destino desde la parada, rechazo de repetido activo y eliminado, opción oculta sin `locations.create`
- [x] 2.6 Validaciones de horas (llegada posterior a salida; salida anterior a la parada previa, con nombre y hora de la previa en el mensaje); tests de ambos rechazos y de un caso válido que cruza la medianoche

## 3. Horarios

- [x] 3.1 Crear `SchedulesRelationManager` (relación `schedules`, pestaña "Horarios") reutilizando form y tabla de `ScheduleResource`, sin select de ruta, con ayuda en la hora de salida ("Las horas de las paradas se corren junto con esta hora"); tests: crear y editar un horario desde la ruta
- [x] 3.2 Verificar que cada pestaña respeta su policy (`route_stops.view_any`, `schedules.view_any`); test con rol custom sin permisos de horarios que no ve la pestaña Horarios
- [x] 3.3 Revisar roles afectados por el cambio de navegación: consultar en la base local los roles (distintos de Super Administrador) que tienen `route_stops.*` o `schedules.*` pero no `routes.view_any` / `routes.update` (necesarios para abrir la pantalla de la ruta), reportarlos al usuario junto con la consulta equivalente para correr en producción antes de desplegar, y agregar un test que documente que un rol con `route_stops.*` sin `routes.update` no accede a la pantalla de la ruta

## 4. Boletos afectados

- [x] 4.1 Crear `app/Support/AffectedTickets.php` (`php artisan make:class`) con conteo de boletos no eliminados en viajes que todavía no partieron por ruta y por horario (ida y vuelta); tests unitarios/feature de conteo incluyendo viajes pasados y boletos eliminados que no cuentan
- [x] 4.2 Confirmación con "Hay N boletos vendidos en viajes próximos que van a ver la hora nueva." en editar/eliminar horario y editar/eliminar parada cuando N > 0, sin aviso cuando N = 0; notificación con el conteo tras reordenar; tests de ambos casos
- [x] 4.3 Bloquear eliminar un horario con boletos futuros, explicar el motivo y ofrecer la acción "Desactivar"; tests: no se elimina con boletos, se elimina sin boletos, "Desactivar" deja `is_active = false`

## 5. Listado de rutas y limpieza del cluster

- [x] 5.1 Columnas "Recorrido" (origen → destino), "Paradas" y "Horarios" con `withCount` y eager loading, y badge "Sin paradas" / "Sin horarios" en rutas activas incompletas; actualizar `tests/Feature/RoutesTableFilterTest.php` o agregar tests del listado, verificando que no hay N+1 relevante (cantidad de queries acotada)
- [x] 5.2 Quitar las columnas `created_at`, `updated_at`, `deleted_at` de los recursos del cluster Destinos y revisar etiquetas sin traducir; test de que la tabla de rutas no las ofrece

## 6. Reordenado con confirmación

- [x] 6.1 Guardar el orden arrastrado como pendiente y aplicarlo recién con "Confirmar cambios" (con modal si hay boletos afectados) o descartarlo con "Descartar cambios"; tests en `RouteEditorTest` y `AffectedTicketsTest` (no guarda hasta confirmar, descartar, varios arrastres, salir sin confirmar, recorrido cambiado, modal de boletos)
- [x] 6.2 Reordenar aunque haya paradas eliminadas en órdenes bajos, todo en una transacción; tests de parada eliminada en el orden 1 y de órdenes desfasados
- [x] 6.3 Bloquear la eliminación de rutas con viajes pendientes (listado, pantalla de la ruta y en lote) con aviso y opción "Desactivar ruta"; tests en `tests/Feature/RouteDeletionTest.php`
- [x] 6.4 Bloquear la eliminación de destinos usados en rutas no eliminadas (normal, definitiva y en lote), avisar al editar en qué rutas se ve el cambio y mostrar la columna "Rutas"; tests en `tests/Feature/LocationDeletionTest.php`

## 7. Cierre

- [x] 7.1 Correr `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter="RouteEditorTest|RouteStopsReorderTest|RoutesTableFilterTest|AffectedTickets"`, y verificar que todo pasa
- [x] 7.2 Revisar en el navegador la pantalla de una ruta (crear ruta, horario, paradas, reordenar, aviso de boletos) y confirmar que se entiende sin conocer la base de datos

## Workflow follow-up

- Archivar el change tras la revisión.
