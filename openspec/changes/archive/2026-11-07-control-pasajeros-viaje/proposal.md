# Proposal

## Why

El modal "Ver detalles" del viaje (y su PDF impreso) es la herramienta de quien controla la subida y bajada de pasajeros, pero hoy obliga a recorrer toda la lista: no se puede filtrar por parada, no se ve cuántos suben o bajan en cada una, el asiento se ordena como texto ("10" antes que "2") y el aviso "Viaja con…" está en la fila del acompañante en lugar de en la del titular, que es a quien se le pregunta. Además, un boleto cuyo origen ya no es una parada activa de la ruta desaparece de la lista sin aviso.

## What Changes

- **Orden por parada de subida**: la lista (modal, PDF y Excel, que comparten los datos) se ordena por el orden de la parada de origen dentro del recorrido y, dentro de cada parada, por número de asiento numérico. Los acompañantes van inmediatamente debajo de su titular. Los boletos cuyo origen no es una parada vigente de la ruta MUST seguir apareciendo, al final.
- **Agrupado por parada en el modal, opcional**: switch "Agrupar por parada" (encendido por defecto, recordado en el navegador de cada usuario) que muestra un encabezado por cada parada de subida, numerado según el recorrido ("1. Orán"), con cuántos suben. El resumen de subidas y bajadas por parada queda fuera de este cambio, para repensarlo más adelante.
- **Filtros en el modal**: filtro por origen y por destino, cuyas opciones son las paradas del viaje numeradas en el orden del recorrido, más un buscador por nombre, DNI, asiento o N° de boleto. Se combinan entre sí y actualizan el contador de pasajeros.
- **"Viaja con…" en el titular**: en el modal y en el PDF, la fila del adulto indica con quién viaja (menores y mascotas); las filas de acompañantes ya no repiten el nombre del titular, se muestran como acompañantes de la fila anterior. El Excel mantiene su columna "Viaja con" actual.
- **Teléfono clickeable**: cuando la columna Teléfono se ve (regla actual: parámetro "Observaciones en la venta" apagado), el número es un link `tel:` para llamar y lleva al lado un botón de WhatsApp (`wa.me`). Con observaciones prendidas el teléfono sigue oculto, como hoy.
- **Diseño acorde a filament-ui**: badges con los colores semánticos del panel (origen/destino en `warning`, asiento en `primary`, N° en gris), vacíos con "—", soporte dark, y vista legible en celular sin depender del scroll horizontal.

## Capabilities

### New Capabilities

_Ninguna._

### Modified Capabilities

- `trip-passenger-list`: se agregan requisitos de orden por parada, agrupado opcional por parada, filtros y buscador del modal, ubicación del "Viaja con…" en el titular y teléfono clickeable con WhatsApp. Las columnas y la regla Teléfono/Observación existentes no cambian.

## Impact

- `app/Models/Trip.php` (`getPassengersWithDetails()`): orden, `leftJoin` contra paradas no eliminadas, eager loading de `passenger.children`, datos nuevos por fila (orden de parada, acompañantes del titular, teléfono normalizado).
- `resources/views/filament/trips/trip-details.blade.php`: rediseño con Alpine (filtros, buscador, agrupado) — requiere `npm run build`.
- `resources/views/pdf/trip-details.blade.php`: "Viaja con…" en el titular, encabezado por parada.
- `app/Filament/Resources/Trips/TripResource.php`: pasar las paradas del viaje a la vista (sin consultas en la vista).
- `app/Support/WhatsAppShare.php`: se reutiliza `normalizePhone()`.
- Tests: `tests/Feature/TripPassengerListObservationTest.php` y un test nuevo para orden, filtros y teléfono.
- Sin migraciones ni dependencias nuevas.
