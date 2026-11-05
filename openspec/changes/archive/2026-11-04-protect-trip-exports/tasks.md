# Tasks

## 1. Decidir la rama con datos de producción

- [x] 1.1 Pedirle al usuario el access log del servidor de producción, o revisarlo con él (hPanel o SSH, `access_log` del dominio), y buscar pedidos `GET /trips/<n>/pdf` y `GET /trips/<n>/excel` de los últimos 90 días o de todo el período disponible. Anotar acá el resultado (cantidad de pedidos, fechas, si hubo 200) y la rama elegida según design.md §1:
  - Rama A (eliminar) si no hay pedidos
  - Rama B (proteger) si los hay o si no hay logs

  Verificar que el usuario confirmó la rama antes de seguir.

  **Resultado (2026-11-04):** según el usuario, no hay logs de acceso de producción disponibles. Por design.md §1, sin logs va la Rama B, y el usuario la confirmó explícitamente ("no hay logs, así que usá la opción B").

## 2. Tests de exportación de viajes (antes de tocar las rutas)

- [x] 2.1 Crear `tests/Feature/TripExportTest.php` con `php artisan make:test --phpunit TripExportTest --no-interaction`, con fixtures como las de `TripListEmptyTripsTest`: un viaje con un boleto y otro sin boletos. Agregar los tests de las acciones del panel:
  - `callTableAction('download_pdf', $trip)` en `ManageTrips` descarga `Viaje_N°{id}_{colectivo}_{dd-mm-aaaa}.pdf`
  - `download_excel` con `Excel::fake()` + `Excel::assertDownloaded('Viaje_N°…xlsx')`
  - en el viaje sin boletos, las dos acciones están deshabilitadas

  Verificar con `php artisan test --compact tests/Feature/TripExportTest.php` que pasan con el código actual.
- [x] 2.2 Agregar los tests de acceso por URL comunes a las dos ramas:
  - un invitado que pide `/trips/{id}/pdf` y `/trips/{id}/excel` no recibe el archivo (ni `Content-Type` PDF/xlsx ni `attachment`)
  - un usuario sin `trips.view` tampoco lo recibe

  Verificar que **fallan** con el código actual. Eso confirma la exposición.

## 3. Aplicar la rama elegida

- [x] 3.1 Hacer solo una de las dos ramas.
  - **Rama A**: quitar `trips.pdf.download` y `trips.excel.download` de `routes/web.php` y los `use` de `Trip` / `TripPdfService` / `TripExcelService` que queden sin uso. Confirmar con grep que `downloadTripPdf` / `downloadTripExcel` no tienen otros usos y quitarlos de los servicios. Agregar al test: un usuario con permiso recibe 404 en las URLs viejas.
  - **Rama B**: agregar `->middleware(['auth', 'can:view,trip'])` a las dos rutas, sin cambiar URL, nombre ni servicio. Agregar al test: un usuario con `trips.view` recibe el archivo.

  Verificar con `php artisan test --compact tests/Feature/TripExportTest.php` que todo el archivo pasa, incluidos los tests de la 2.2.
- [x] 3.2 Verificar con `php artisan route:list --path=trips` que las rutas desaparecieron (Rama A) o muestran `auth` + `can:view,trip` (Rama B).

## 4. Cierre

- [x] 4.1 Correr `vendor/bin/pint --dirty --format agent` y verificar que no deja cambios pendientes.
- [x] 4.2 Correr `php artisan test --compact --filter="TripExportTest|TripListEmptyTripsTest|TripPassengerConflictsTest|RolesAndPermissionsTest"` y verificar que todo pasa. Después preguntarle al usuario si quiere correr la suite completa. Antes de commitear, revisar `git status` por archivos locales que no deban entrar al repo.

## Workflow follow-up

- Se tomó la Rama B: proposal.md y design.md ya están actualizados.
- En producción, después del deploy, correr `php artisan route:clear` (o regenerar `route:cache`).
- Archivar con `/opsx:archive`, que crea la spec `trip-export`.
