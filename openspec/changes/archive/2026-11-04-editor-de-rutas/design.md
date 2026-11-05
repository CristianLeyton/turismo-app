# Design

## Context

- Cluster `LocationsCluster` ("Destinos") con cuatro recursos simples (`ManageRecords`, todo en modales): `LocationResource` (Destinos), `RouteResource`, `RouteStopResource` y `ScheduleResource`.
- `RouteStop` guarda `departure_offset_minutes` / `arrival_offset_minutes`: minutos desde la salida del horario, **compartidos por todos los horarios de la ruta**. El formulario actual los convierte a hora del reloj contra un "horario de referencia" (`RouteStopResource::referenceSchedule()`, `offsetToClock()`, `clockToOffset()`), con vuelta por medianoche.
- `ManageRouteStops::reorderTable()` implementa el reordenado con horas pegadas a la posición (arrastre: intercambio 1 a 1; modo botón: por posición), liberando la UNIQUE `(route_id, stop_order)` con un offset temporal.
- Restricciones de base: UNIQUE `(route_id, stop_order)` y `(route_id, location_id)`. Hoy un duplicado sale como error SQL.
- Los boletos no guardan horas: se calculan con `trip.schedule` + offsets de la parada. Por eso editar horarios/paradas cambia lo que ve un boleto ya vendido.
- Políticas por módulo con `AuthorizesModule`: `routes`, `route_stops`, `schedules`, `locations`.

## Goals / Non-Goals

**Goals:**
- Una pantalla por ruta que concentre datos, recorrido y horarios, reutilizando formularios, tablas y la lógica de reordenado existentes.
- Ocultar todo concepto de base de datos (orden numérico, horario de referencia, columnas técnicas) sin cambiar cómo se guardan los datos.

**Non-Goals:**
- Migraciones o cambios al cálculo de horas en venta, reprogramación, PDF o enlaces de boleto.
- Horas distintas por horario (el modelo comparte los offsets entre horarios).
- Rediseñar el recurso Destinos más allá de la creación rápida desde la parada.

## Decisions

1. **Página `EditRoute` con relation managers en pestañas.** `RouteResource` pasa de solo `ManageRoutes` a `index` + `edit` (`EditRoute`), con `RouteStopsRelationManager` (relación `stops`) y `SchedulesRelationManager` (relación `schedules`), y `hasCombinedRelationManagerTabsWithContent()` para que el formulario sea la pestaña "Datos". La creación sigue en modal desde el listado y redirige a `edit`. Alternativa descartada: un único formulario con `Repeater` de paradas y horarios; obliga a reescribir reordenado, validaciones y guardado transaccional, y pierde el arrastre con horas pegadas a la posición ya probado.
2. **Reutilizar los recursos como "related resource".** Los relation managers apuntan a `RouteStopResource` / `ScheduleResource` (`$relatedResource`), así form, tabla y policies son una sola fuente. Los recursos se dejan registrados pero con `shouldRegisterNavigation = false`, para no romper URLs ni los tests existentes. Verificado en el código de Filament v4 (`InteractsWithRelationshipTable`, ya que `search-docs` no estaba disponible): con `$relatedResource` el relation manager usa `form()`, `configureTable()` y las autorizaciones del recurso, y `canViewForRecord()` delega en `canAccess()` del recurso. Se usa `$relatedResource` + `$relationship` explícito (`stops` / `schedules`), porque no son recursos anidados. El reordenado se autoriza con la habilidad `reorder`: se agrega `reorder()` a `RouteStopPolicy` mapeado a `route_stops.update`, para que un rol con permiso de editar paradas pueda arrastrarlas. Dentro del relation manager se ocultan el select de ruta (lo fija la relación) y el filtro de ruta.
3. **Reordenado compartido.** Mover el cuerpo de `ManageRouteStops::reorderTable()` a un trait (`ReordersRouteStopsKeepingTimes`) usado por la página vieja y por el relation manager. El relation manager siempre está acotado a una ruta, así que el arrastre está siempre habilitado.
4. **Primer horario activo como única referencia.** Se elimina el campo `reference_schedule_id` del formulario; `referenceSchedule()` sin `scheduleId` ya devuelve el primero activo por `departure_time`, se mantiene. El encabezado del recorrido dice "Horas del horario de las HH:MM". Una columna "En otros horarios" lista `HH:MM` por cada horario activo extra usando `offsetToClock()`. Sin horarios activos: la tabla muestra el estado vacío con el mensaje del spec y la acción crear queda oculta.
5. **`stop_order` automático.** El campo sale del formulario; al crear se asigna `max(stop_order) + 1` de la ruta (incluye eliminadas lógicamente, para no chocar con la UNIQUE).
6. **Validaciones de Filament antes de la base.** Regla de localidad única por ruta que ignora el propio registro y también considera las paradas eliminadas lógicamente, porque la UNIQUE de la base no las excluye. Si choca con una parada activa, mensaje del spec; si choca con una eliminada, el mensaje indica que se puede restaurar desde el filtro de eliminadas. Reglas de horas en el formulario comparando offsets: llegada ≤ salida; salida ≥ salida de la parada anterior (por `stop_order`). Al estar en offsets (0–1439 tras `clockToOffset`), la vuelta por medianoche ya está resuelta; para no confundir una hora apenas anterior con "el día siguiente", un salto de más de 12 h entre dos horas seguidas se toma como hora anterior (`MAX_GAP_MINUTES`).
7. **Aviso de boletos afectados.** Clase de soporte `AffectedTickets` (en `app/Support/`) que cuenta boletos no eliminados cuyo `trip_id` o `return_trip_id` apunta a viajes que todavía no partieron (fecha posterior a hoy, o de hoy con hora de salida del horario posterior a la actual) de la ruta (o del horario, en cambios de horario). Las acciones de editar/eliminar horario, editar/eliminar parada y el reordenado usan `requiresConfirmation()` con `modalDescription` dinámico solo cuando el conteo es > 0. El arrastre deja el orden pendiente en el componente (no en la base) y la tabla lo muestra; "Confirmar cambios" lo guarda en una transacción y, con boletos afectados, antes abre el modal de la acción `saveStopOrder`. Como Filament oculta encabezado y acciones mientras se reordena, "Confirmar cambios" y "Descartar cambios" van en un encabezado propio (`->header()`), y "Cambiar orden" se esconde por CSS en ese modo (Filament lo dibuja sin mirar `hidden()`). Salir del modo sin confirmar descarta.
8. **Eliminar horario con boletos.** `DeleteAction` del horario con `before()` que cancela y notifica cuando `AffectedTickets` > 0, y una acción "Desactivar" visible en ese caso.
9. **Listado de rutas.** Columnas calculadas con `withCount` (`stops`, `activeSchedules`) y origen/destino con eager loading de `stops.location` para evitar N+1; badge de alerta "Sin paradas" / "Sin horarios" en rutas activas. Se eliminan las columnas `created_at`, `updated_at`, `deleted_at` de los recursos del cluster.
10. **Destino nuevo desde la parada.** `Select::make('location_id')->relationship(...)->searchable()->preload()->createOptionForm([...])`, visible solo con `locations.create`.

## Risks / Trade-offs

- [El orden pendiente se pierde si el usuario sale de la página sin confirmar] → el aviso "Hay cambios sin guardar." queda visible mientras se reordena; nunca se guarda nada a medias.
- [Paradas eliminadas que ocupan órdenes bajos chocaban con la UNIQUE al reordenar] → al confirmar se pasan detrás de todas y el corrimiento temporal supera cualquier orden de la ruta, todo en una transacción.
- [Datos existentes que no cumplen las nuevas validaciones de horas] → solo se validan al editar esa parada; el mensaje dice qué parada choca para corregirla.
- [Contar boletos en cada apertura de modal] → una consulta indexada por `trip_id`; aceptable para el volumen actual.
- [Tests que usan `ManageRouteStops` directamente] → la página sigue existiendo (solo oculta del menú), los tests actuales siguen válidos; los nuevos cubren el relation manager.

- [Roles a medida con permisos de paradas u horarios pero sin `routes.view_any` / `routes.update` pierden el acceso, porque ya no hay menús sueltos] → antes de desplegar se listan esos roles (tarea 3.3) y se les habilitan los permisos de rutas desde la matriz si corresponde.

## Migration Plan

Despliegue solo de código, sin migraciones ni `permissions:sync`. Antes de desplegar, revisar los roles listados en la tarea 3.3. Rollback: revertir el código; los datos no cambian de forma.

## Open Questions

- Si conviene agregar luego un acceso directo "Ver recorrido" desde el listado de viajes; no afecta este alcance.
