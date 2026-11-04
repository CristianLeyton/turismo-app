# Design

## Context

Motivación y verificación de uso: ver proposal.md (secciones "Why" y "Verificación de uso"). Requisitos: `specs/trip-export`.

Estado actual relevante:

- `routes/web.php` define dos closures sin middleware: `GET /trips/{trip}/pdf` (`trips.pdf.download`), que llama a `TripPdfService::downloadTripPdf()`, y `GET /trips/{trip}/excel` (`trips.excel.download`), que llama a `TripExcelService::downloadTripExcel()`. `Trip`, `TripPdfService` y `TripExcelService` se importan en `web.php` solo para esas dos rutas.
- `TripResource` (página `ManageTrips`) tiene ocho acciones de exportación:
  - `download_pdf` / `download_excel` en la fila, en sus variantes de escritorio y celular
  - `download_pdf_modal` / `download_excel_modal` en el pie del modal "Ver detalles", también por duplicado

  Todas usan `generateTripDetailsPdf` / `generateTripDetailsExcel` y quedan deshabilitadas cuando el viaje no tiene boletos.
- Los nombres de archivo no son idénticos entre variantes. El PDF de la acción limpia el nombre del colectivo con `preg_replace('/[^a-zA-Z0-9_-]/', '_', ...)`, el Excel con `str_replace(' ', '_', ...)`, y `downloadTripPdf` (la ruta) con `str_replace`.
- `bootstrap/app.php` ya redirige a los invitados al login del panel admin (commit `151eafd`). `TripPolicy` usa los permisos `trips.*`.
- `maatwebsite/excel` 3.1 está instalado y no hay tests de exportación de viajes.

## Goals / Non-Goals

**Goals:**
- Que ninguna URL entregue el PDF o el Excel de un viaje sin sesión del panel y permiso.
- Tomar la decisión entre eliminar o proteger con un dato de producción, no con una suposición.
- Fijar con tests que las acciones del panel siguen descargando.

**Non-Goals:**
- No se unifican ni se cambian los nombres de archivo ni el código de las ocho acciones de `TripResource`. La diferencia `preg_replace` / `str_replace` queda como está.
- No se agrega un permiso específico de exportación a las acciones del panel (fuera de alcance según proposal.md).
- No se tocan `tickets.download` / `tickets.download.multiple`.

## Decisions

### 1. Revisar los logs de producción antes de decidir
La primera tarea es revisar el access log del servidor de producción de los últimos 90 días (o todo lo disponible) en busca de pedidos `GET /trips/<n>/pdf` o `GET /trips/<n>/excel` con respuesta 200. El resultado define la rama:

```
  logs de produccion
        |
        +-- sin pedidos ------------> Rama A: eliminar rutas + metodos
        |
        +-- con pedidos ------------> Rama B: mantener rutas con auth + permiso
        |
        +-- logs no disponibles ----> preguntar al usuario; por defecto Rama B
```

Si no hay logs, la opción por defecto es la Rama B, porque no rompe enlaces y cierra igual la exposición. Se anota en tasks.md qué rama se tomó y por qué.

**Resultado:** sin logs de producción disponibles -> Rama B, confirmada por el usuario el 2026-11-04. La Rama A (§2) queda documentada como opción futura si algún día se confirma que nadie usa las URLs.

- *Alternativa*: eliminar directamente sin mirar logs. La descartamos porque el usuario pidió verificar que de verdad no se usen, y el repositorio no alcanza para descartar el uso externo.

### 2. Rama A: eliminar
- Quitar las dos rutas de `web.php` y los `use` que queden sin uso.
- Quitar `TripPdfService::downloadTripPdf()` y `TripExcelService::downloadTripExcel()`, después de confirmar con grep que siguen sin otros usos.
- Las URLs viejas pasan a devolver 404 para cualquiera, con o sin sesión. Eso cumple el requisito "Sin exportaciones de viajes por dirección pública".

### 3. Rama B: proteger
- Agregar a las dos rutas `->middleware(['auth', 'can:view,trip'])`, con el mismo estilo que `admin.boletos.pdf`. El invitado va al login del panel por `redirectGuestsTo`, y el usuario sin `trips.view` recibe 403.
- Mantener nombres de ruta y métodos de servicio.
- No se mueven bajo `admin/`: cambiar la URL rompería justo los enlaces que motivan esta rama.

### 4. Tests
Un archivo nuevo `tests/Feature/TripExportTest.php` (PHPUnit), con fixtures como las de `TripListEmptyTripsTest` (bus, ruta, horario, viaje con un boleto).
- **Comunes a las dos ramas**:
  - Un invitado que pide `/trips/{id}/pdf` y `/trips/{id}/excel` no recibe el archivo: no hay `Content-Type` PDF/xlsx ni `Content-Disposition: attachment`. La respuesta es una redirección o un 404.
  - Un usuario sin `trips.view` no recibe el archivo.
  - `Livewire::test(ManageTrips::class)->callTableAction('download_pdf', $trip)` devuelve una descarga con el nombre esperado.
  - `download_excel` se verifica con `Excel::fake()` + `Excel::assertDownloaded(...)`.
  - Con un viaje sin boletos, las acciones están deshabilitadas (`assertTableActionDisabled`).
- **Rama A**: el usuario con permiso también recibe 404 en las URLs viejas.
- **Rama B**: el usuario con `trips.view` recibe el archivo por la URL.

Los tests comunes se escriben **antes** de tocar las rutas. Los de las acciones del panel tienen que pasar con el código actual, y el del invitado tiene que fallar hasta aplicar la rama.

## Risks / Trade-offs

- [Que el log de producción no cubra suficiente tiempo o no esté disponible] → Se toma la Rama B, que no rompe nada y cierra la exposición.
- [Rama A: un chofer o vendedor con un enlace guardado recibe 404 sin explicación] → Es aceptable solo si los logs no muestran uso. Además, el PDF se baja desde el panel con dos clics.
- [Rama B: los enlaces viejos ahora piden login] → Quien tenga cuenta en el panel sigue accediendo. Quien no la tiene, por ejemplo un chofer sin usuario, pierde el acceso, y eso es justamente lo que se busca.
- [`callTableAction` sobre las variantes duplicadas (`download_pdf` existe en escritorio y en el grupo de celular)] → Si Filament resuelve la acción ambiguamente, se testea el nombre que esté a nivel fila y se acepta cubrir una sola variante, porque comparten el mismo closure de generación.

## Migration Plan

Sin migraciones de datos ni assets. Deploy normal; en producción, `php artisan route:clear` (o `route:cache`) si las rutas están cacheadas. Rollback: revertir el commit.
