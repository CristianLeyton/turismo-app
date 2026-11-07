# Design

## Context

- `Trip::getPassengersWithDetails()` alimenta el modal, el PDF (`TripPdfService`) y el Excel (`TripExcelService`). Devuelve filas planas (`type` = `adult` | `child` | `pet`) con `parent_name` en las de acompañantes; el Excel usa `parent_name` para su columna "Viaja con".
- Hoy ya ordena por `route_stops.stop_order` del origen y después por `seats.seat_number`, pero:
  - `seat_number` es texto, así que "10" < "2".
  - El `join` (inner) con `route_stops` no filtra `deleted_at` (RouteStop usa SoftDeletes y la UNIQUE incluye eliminadas): una parada eliminada y vuelta a agregar duplica el boleto, y un boleto cuyo origen ya no está en el recorrido desaparece.
  - `passenger->children` se carga por fila (N+1).
- El modal es una vista Blade (`filament.trips.trip-details`) inyectada con `modalContent()` en dos acciones duplicadas (escritorio y menú de celular) de `TripResource`. La vista consulta `$trip->route->stops()` dentro del Blade.
- La regla Teléfono/Observación (`$showPhone = ! $showObservation`) se mantiene: el usuario eligió que el teléfono sea clickeable solo cuando ya se ve.

## Goals / Non-Goals

**Goals:**
- Un solo lugar que defina orden y forma de los datos, compartido por modal, PDF y Excel.
- Filtros, buscador y agrupado del modal 100 % en el navegador (Alpine), sin round-trips de Livewire: el control se hace en la vereda, a veces con mala señal.
- Vista usable en celular sin scroll horizontal obligatorio.

**Non-Goals:**
- Check-in persistente ("Subió"/"Bajó"): queda como propuesta futura (requiere migración y permiso).
- Filtros o agrupado en el PDF/Excel: el PDF recibe "Viaja con" en el titular y encabezados por parada, pero no filtros.
- Cambiar qué columnas se muestran según el parámetro de observaciones.

## Decisions

### 1. Orden y datos en `getPassengersWithDetails()`
- Sin `join` a `route_stops`: la posición de cada origen sale de un mapa ubicación → posición armado con `passengerListStops()` (paradas vigentes). Así no se pierde ningún boleto (antes el inner join descartaba los orígenes que nunca fueron parada) y una parada eliminada ya no conserva su posición vieja. No hay duplicados posibles: la UNIQUE `(route_id, location_id)` incluye las eliminadas.
- Orden **en PHP** sobre los boletos ya traídos: `sortBy([[origen fuera del recorrido al final], [stop_order], [asiento como int], [tickets.id]])`. Alternativa descartada: `CAST` en SQL, que cambia según el motor (MySQL en producción, el de tests puede diferir). La cantidad de boletos por viaje es chica (≤ capacidad del colectivo), así que ordenar en memoria no pesa.
- Eager loading: `with(['passenger.children', 'seat', 'origin', 'destination'])`.
- Campos nuevos por fila (aditivos, no rompen PDF/Excel):
  - `origin_location_id`, `destination_location_id`, `origin_order` (`?int`, null si fuera del recorrido), `destination_order`.
  - En la fila `adult`: `companions` = `list<array{type: 'child'|'pet', name: string}>` (mascotas con `pet_count`).
  - `phone_e164` = `WhatsAppShare::normalizePhone($phone)` (vacío si no hay).
  - `search` = texto normalizado (minúsculas, sin tildes vía `Str::ascii`) con nombre, DNI, asiento y N° de boleto de esa fila. "Mostrar titular y acompañantes" se resuelve filtrando por boleto (decisión 3), no repitiendo textos entre filas.
- Se conserva `parent_name` en acompañantes (lo usa el Excel).

Alternativa descartada: devolver grupos anidados (boleto → acompañantes). Rompería PDF y Excel, que iteran filas planas.

### 2. Paradas del viaje fuera del Blade
Nuevo método en `Trip` (p. ej. `passengerListStops()`) que devuelve las paradas vigentes del recorrido con `order` (posición 1..n, no el `stop_order` crudo, que puede tener huecos) y `name`. `TripResource` y `TripPdfService` lo pasan a las vistas; la numeración "1. Orán" sale de ahí. El `origin_order` de cada fila se mapea a esa misma posición 1..n.

Las dos copias de `ViewAction::make('view_details')` en `TripResource` se unifican en un método estático `viewDetailsAction()` (convención filament-ui §6) para no tocar la vista dos veces.

### 3. Modal con Alpine
- El Blade serializa las filas a JSON (`@js`) y un componente `x-data` mantiene `origin`, `destination`, `search`.
- **Filtrado por boleto**: un boleto es visible si su titular cumple origen/destino (todos comparten origen/destino) y si el buscador coincide con alguna fila del boleto. Así se cumple "buscar al menor muestra al titular" y viceversa.
- Agrupado: las filas visibles se agrupan por `origin_order` (en el orden ya dado por el servidor); grupo final "Fuera del recorrido".
- Switch "Agrupar por parada": estado `groupByStop` con `$persist(true).as('trip-passengers-group-by-stop')` (plugin persist de Alpine, ya incluido con Livewire). Se guarda en `localStorage`: es una preferencia de vista de cada usuario en su dispositivo, no un parámetro de la empresa. Apagado oculta solo las filas de encabezado (`x-show`), el orden no cambia. El PDF no puede leer esa preferencia y sigue separado por parada.
- El resumen de subidas/bajadas por parada se implementó y se quitó a pedido del usuario, para repensarlo en otro cambio.
- Las filas se renderizan en Blade (no con `x-for`) con `x-show` y atributos `data-*`, para que el HTML siga conteniendo los datos y los tests existentes (`assertSee`, orden de columnas) sigan valiendo sin JS.
- Contador "N de M" con `x-text`.

Alternativa descartada: tabla Filament/Livewire (`TableWidget`) dentro del modal: más pesado, requiere round-trips y no encaja con el patrón actual de vista Blade compartida con el PDF.

### 4. Diseño (filament-ui)
- Badges: N° boleto `gray`, asiento `primary` (`bg-primary-500/10 text-primary-700 dark:text-primary-400`), origen/destino `warning` (reemplaza el `orange` actual por `amber`, el tono del panel para recorrido).
- Vacíos con "—" (hoy "-" en DNI/teléfono; la observación vacía mantiene "-" porque está fijada por spec y test existentes).
- Acompañantes: fila con sangría y borde izquierdo, etiqueta "Menor" / "Mascota" en vez de "Viaja con".
- Celular: debajo de `md` cada pasajero se muestra como tarjeta (`rounded-lg bg-gray-50 p-2.5 dark:bg-white/5`) con nombre, asiento y recorrido; desde `md` se mantiene la tabla. Se elimina el `max-w-[83dvw]` con scroll forzado.
- Teléfono: `<a href="tel:...">` + botón icono WhatsApp (`https://wa.me/{phone_e164}`, `target="_blank" rel="noopener"`) con `title="Escribir por WhatsApp"`. No usar `WhatsAppShare::url()` porque precarga el mensaje del boleto.
- Encabezado del viaje: se mantiene; el recorrido usa `text-primary-600 dark:text-primary-400` (ya cumple la guardia `BrandColorUsageTest`).

### 5. PDF
Sin filtros. Cambios: "Viaja con: …" en la fila del titular, acompañantes sin "Viaja con: {titular}" (etiqueta "Menor"/"Mascota"), y una fila separadora por parada de subida ("1. Orán — suben 5"). Mismas reglas de columnas.

## Risks / Trade-offs

- [Tests existentes del modal/PDF (`TripPassengerListObservationTest`, `TripExportTest`) que dependan del HTML actual] → correrlos y actualizarlos; el Excel no cambia. Los "Viaja con un menor" de `CompanionParamEditTest` son labels del formulario de venta y no se tocan.
- [Clases Tailwind nuevas en la vista] → correr `npm run build`; la vista ya está bajo un `@source` existente.
- [JSON grande en viajes con muchos pasajeros] → acotado por la capacidad del colectivo (decenas de filas).
- [Teléfonos mal cargados] → `normalizePhone()` puede devolver un número inválido; el link igual se arma, como ya pasa en el modal de entrega.
- [Duplicación del `ViewAction`] → se refactoriza a un método estático; riesgo bajo, cubierto por un test que abre el modal desde la acción de la tabla.

## Migration Plan

Sin migraciones. Deploy: `git pull`, `npm run build`, `php artisan view:clear`. Rollback: revertir el commit.

## Propuestas a futuro (fuera de alcance)

- Check-in persistente por pasajero ("Subió" / "Bajó") con contador de faltantes, permiso `trips.board` y auditoría.
- Hora estimada de paso por cada parada en el encabezado del grupo (ya existe `Ticket::getBoardingDepartureTime()`).
- Vista "Hoja de control" imprimible con casilla para tildar a mano.
- Marcar ida y vuelta / tramo de vuelta en la fila.
