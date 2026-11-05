# Proposal

## Why

`routes/web.php` publica `GET /trips/{trip}/pdf` y `GET /trips/{trip}/excel` sin ningún middleware. Cualquiera que pruebe números de viaje correlativos (`/trips/1/pdf`, `/trips/2/pdf`…) puede bajarse, sin iniciar sesión, el listado de pasajeros de cada viaje, con nombres, DNI, teléfonos y asientos. Es una exposición de datos personales.

Además, ninguna pantalla usa esas rutas. Las exportaciones del panel salen de las acciones de `TripResource` ("PDF" y "Excel" del detalle del viaje y de la tabla), que generan el archivo dentro de la sesión del panel con los métodos `generateTripDetailsPdf` / `generateTripDetailsExcel`.

## Verificación de uso

Búsqueda en todo el repositorio, sin contar `vendor`, `node_modules` ni `.git`:

| Qué se buscó | Resultado |
|---|---|
| Nombres `trips.pdf.download` / `trips.excel.download` | Solo su definición en `routes/web.php` |
| URLs literales `/trips/{id}/pdf` y `/trips/{id}/excel` | Solo `routes/web.php` |
| `route()` / `url()` dinámicos, JS y assets compilados | Sin coincidencias |
| `downloadTripPdf` / `downloadTripExcel` | Solo los llaman esas dos rutas |
| Tests, `config/`, `.env.example` | Sin coincidencias |
| `storage/logs` locales | Ningún pedido registrado a esas URLs |

Historial de git:
- `8cca45a` ("Cree informes en pdf y excel") creó las rutas, y `TripResource` las usaba con `->url(route(...))` en cuatro botones.
- `566518a` ("Mejoras visuales que me pidió Leo", 20/01/2026) pasó esos botones a `->action()` dentro del panel y quitó los cuatro usos. Desde entonces las rutas siguen definidas, pero nadie las llama.

Conclusión: dentro del código no se usan. Lo que no se puede descartar desde el repositorio es el uso **externo**: hasta enero de 2026 los botones abrían esas URLs en el navegador, así que alguien pudo guardar o reenviar un enlace `/trips/{id}/pdf` (por ejemplo, la lista de pasajeros para un chofer).

## What Changes

- **Decisión tomada (2026-11-04)**: no hay logs de acceso de producción disponibles, así que se aplica la protección y no la eliminación (design.md §1). Las rutas siguen existiendo.
- Las rutas `trips.pdf.download` (`/trips/{trip}/pdf`) y `trips.excel.download` (`/trips/{trip}/excel`) pasan a exigir sesión del panel (`auth`) y permiso `trips.view` (`can:view,trip`). Mantienen URL, nombre y servicio, así que los enlaces viejos siguen funcionando para quien tenga cuenta con ese permiso. Un invitado va al login del panel y un usuario sin permiso recibe 403.
- `TripPdfService::downloadTripPdf()` y `TripExcelService::downloadTripExcel()` se mantienen: los usan esas rutas.
- Las acciones "PDF" y "Excel" del panel no cambian.
- Tests que fijan:
  - el invitado y el usuario sin permiso no reciben el archivo por URL
  - el usuario con `trips.view` sí lo recibe
  - las acciones del panel siguen descargando, y quedan deshabilitadas sin boletos

**Alternativa considerada**: eliminar las rutas y sus métodos, ya que nada en la app las usa desde `566518a`. Queda descartada mientras no se pueda descartar el uso externo con logs de producción: protegerlas cierra igual la exposición sin romper enlaces guardados. Si más adelante se confirma que nadie las usa, se pueden eliminar en otro cambio.

**Fuera de alcance**:
- `tickets.download` / `tickets.download.multiple` también están sin `auth`, pero solo muestran lo que quedó en la sesión de quien las abre, y la consumen y borran al leerla. Un invitado recibe 404.
- Revisar si las acciones de exportación de `TripResource` deberían exigir además un permiso específico.

## Capabilities

### New Capabilities
- `trip-export`: cómo se obtienen el PDF y el Excel con el detalle y los pasajeros de un viaje, y quién puede obtenerlos.

### Modified Capabilities
<!-- Ninguna: `trip-list` describe qué viajes muestra la lista, no sus exportaciones. -->

## Impact

- **Rutas**: las dos rutas de `routes/web.php` suman `auth` + `can:view,trip`. Los imports no cambian.
- **Servicios**: sin cambios.
- **Filament**: sin cambios en `TripResource`.
- **Tests**: `tests/Feature/TripExportTest.php` (10 tests).
- **Usuarios**: dentro del panel, ninguno. Quien abra un enlace `/trips/{id}/pdf|excel` guardado tiene que iniciar sesión y tener `trips.view`.
