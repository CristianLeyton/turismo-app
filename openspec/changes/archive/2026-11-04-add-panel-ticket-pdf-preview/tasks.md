# Tasks

## 1. Tests de caracterización del PDF de un boleto (antes de refactorizar)

- [x] 1.1 Crear `tests/Feature/TicketPdfConsistencyTest.php` (PHPUnit) con tres fixtures: un boleto de ida y vuelta de un pasajero, un boleto solo de ida en una venta de dos pasajeros y un boleto sin fecha. Agregar los casos de nombre de archivo (`Boleto_N°{id}_{colectivo}.pdf`, espacios cambiados a `_`, `Sin_fecha`) para cada fixture. Verificar con `php artisan test --compact tests/Feature/TicketPdfConsistencyTest.php`: tiene que pasar con el código actual.
- [x] 1.2 Agregar a ese test la variante y el nombre que entrega cada acceso actual, sin cambiar código de producción:
  - descarga de `ViewTicket` (`callAction('download_pdf')` + nombre de la descarga)
  - descargas de `TicketsTable` (`callTableAction('download_pdf', ...)`)
  - link público (`boletos.share.pdf`, cabecera `Content-Disposition`)
  - panel de clientes (`clientes.boletos.pdf`)
  - reprogramación con descarga automática (`session('ticket_pdf_filename')`)

  Para la variante, espiar el servicio con un mock parcial de `TicketPdfService` ligado al contenedor, o, si hoy se instancia con `new`, comparar las páginas o la variante por otra señal observable, sin comparar bytes. Verificar que el archivo completo pasa en verde.

## 2. Fuente única en `TicketPdfService`

- [x] 2.1 Agregar a `App\Services\TicketPdfService`:
  - `forTicket(Ticket $ticket): array{content: string, filename: string}`, que carga los boletos del pasajero con el mismo `with([...])` de hoy y elige la variante según `is_round_trip`
  - `static filenameFor(Ticket $ticket): string`

  Con PHPDoc del array shape. Agregar tests unitarios de los dos métodos a `TicketPdfConsistencyTest` y verificar que pasan.
- [x] 2.2 Reemplazar la lógica duplicada en `TicketShareController::ticketPdf` y `ClientTicketPdfController::download` por `forTicket()`. Hacer que `TicketShareController::ticketFilename()` delegue en `filenameFor()`, después de buscar con grep sus usos. Verificar con `php artisan test --compact --filter="TicketPdfConsistencyTest|TicketShareLinkModeTest|ClientPanelTicketsTest|PendingTicketPdfTest"`.
- [x] 2.3 Reemplazar la lógica duplicada en `ViewTicket` (`download_pdf`), en las dos `download_pdf` de `TicketsTable` y en `RescheduleTicket`, manteniendo en cada una su forma de entrega (`streamDownload` / sesión base64). Quitar los `use` que queden sin uso. Verificar con `php artisan test --compact --filter="TicketPdfConsistencyTest|RescheduleTicketPageTest|TableRowClickTest|TicketLegDisplayTest"`.
- [x] 2.4 Confirmar con grep que no quedan copias de `generateRoundTripTicket(`/`generatePassengerTickets(` fuera de `TicketPdfService`, salvo los flujos de venta combinada o múltiple que están fuera de alcance. Si queda alguna copia de "PDF de un boleto" que no esté en la lista, anotarla y migrarla con su test.

## 3. Ruta autenticada del PDF en el panel

- [x] 3.1 Revisar cómo responde hoy `auth` a un invitado fuera de Filament: si existe `route('login')` y si alguna ruta del panel de clientes depende de ese redirect. Elegir según design.md §2: `redirectGuestsTo` al login del panel admin en `bootstrap/app.php` o una respuesta 401/403 en la ruta. Verificar con un test de invitado que no dé 500.
- [x] 3.2 Crear el controlador (`php artisan make:controller AdminTicketPdfController --no-interaction`) y la ruta `GET admin/boletos/{ticket}/pdf`, con nombre `admin.boletos.pdf`:
  - `auth` + `withTrashed()` + autorización `view`
  - `disposition` `inline|attachment` (otra da 404)
  - mismas cabeceras que el link público
  - usa `TicketPdfService::forTicket()`

  Revisar con `list-routes` que la ruta no choque con las de Filament bajo `/admin`.
- [x] 3.3 Crear `tests/Feature/AdminTicketPdfTest.php` con estos casos:
  - usuario con `tickets.view` recibe `application/pdf` inline
  - `attachment` descarga con `filenameFor()`
  - invitado no recibe el PDF
  - usuario sin permiso recibe 403
  - boleto eliminado devuelve 200
  - boleto inexistente devuelve 404
  - `disposition` inválida devuelve 404
  - no se persiste `share_token` en el boleto ni en la venta

  Verificar con `php artisan test --compact tests/Feature/AdminTicketPdfTest.php`.

## 4. Vista previa en el "Ver boleto"

- [x] 4.1 Crear `resources/views/tickets/infolist-pdf-preview.blade.php`. Lleva el mismo marcado y las mismas clases que la sección "Vista previa" de `boletos/share.blade.php` (título, tarjeta de fallback con nombre de archivo, "Abrir PDF" en pestaña nueva y "Descargar PDF"), con la lógica escritorio/celular en Alpine (`x-data`/`x-init` + `<template x-if>` para el iframe) y las URLs de `route('admin.boletos.pdf', ...)`. Antes de escribirlo, seguir la skill `filament-ui`. Verificar con `php artisan test --compact tests/Feature/AdminTicketPdfTest.php` (los casos de la task 4.3).
- [x] 4.2 Agregar a `TicketInfolist` el `ViewEntry::make('pdf_preview')`, entre `sale_details` y la sección "Historial de ediciones", con `label('')` y `columnSpanFull()`. No cambiar ningún otro componente. Verificar con la task 4.3.
- [x] 4.3 Agregar tests de `ViewTicket` (Livewire) en `AdminTicketPdfTest` o en un test propio:
  - boleto vigente: muestra "Vista previa" y la URL de `admin.boletos.pdf`, y no muestra URLs `boletos/{token}`
  - boleto eliminado: también la muestra
  - con el parámetro de descarga automática activado y desactivado
  - después de renderizar, el boleto y la venta siguen con `share_token` nulo

  Verificar que pasan.
- [x] 4.4 Revisar en el navegador, con la URL de `get-absolute-url`, en escritorio (iframe visible) y en ancho de celular (tarjeta, sin iframe), en tema claro y oscuro, y llegando al detalle por navegación interna del panel desde la tabla. Si las clases nuevas no aparecen, pedir `npm run build`. Revisar con `browser-logs` que no haya errores de JS.

## 5. Cierre

- [x] 5.1 Correr `vendor/bin/pint --dirty --format agent` y verificar que no deja cambios pendientes.
- [x] 5.2 Correr juntos los tests relacionados (`TicketPdfConsistencyTest`, `AdminTicketPdfTest`, `TicketShareLinkModeTest`, `ClientPanelTicketsTest`, `PendingTicketPdfTest`, `RescheduleTicketPageTest`, `TicketLegDisplayTest`, `TicketContactLinesPdfTest`) y verificar que pasan todos. Después preguntarle al usuario si quiere correr la suite completa.

## Workflow follow-up

- Archivar el cambio (`/opsx:archive`) cuando esté revisado e implementado. Eso crea las specs `ticket-detail` y `ticket-pdf`.
