# Design

## Context

Motivación: ver proposal.md, sección Why. Requisitos: `specs/ticket-detail` y `specs/ticket-pdf`.

Estado actual relevante:

- El "Ver boleto" (`ViewTicket` + `TicketInfolist`) arma su cuerpo con dos `ViewEntry` (`tickets.infolist-summary-header`, `tickets.infolist-sale-details`) y una sección colapsable de historial. El link público (`resources/views/boletos/share.blade.php`) reusa esos mismos blades y agrega la "Vista previa" con un `<script>` suelto, que decide entre iframe y tarjeta según `navigator.pdfViewerEnabled` y `min-width: 768px`.
- `HasShareToken::sharePdfUrl()` llama a `shareToken()`, que **persiste** un token si no existe. `findByShareToken()` no incluye eliminados.
- La regla del PDF de un boleto está copiada en seis lugares:
  - `ViewTicket` (descarga)
  - `TicketsTable` (dos descargas: celular y escritorio)
  - `RescheduleTicket` (a sesión, hacia `tickets.download`)
  - `TicketShareController::ticketPdf`
  - `ClientTicketPdfController::download`

  Todas hacen `is_round_trip ? generateRoundTripTicket(boletos del pasajero en la venta) : generatePassengerTickets([boleto])` con el nombre `Boleto_N°{id}_{colectivo}.pdf`. `TicketShareController::ticketFilename()` ya existe como helper público, pero los demás lugares no lo usan.
- `routes/web.php` no tiene rutas del panel admin propias: todo lo autenticado pasa por Filament (panel `admin`, guard `web`). `bootstrap/app.php` no configura `redirectGuestsTo`.
- `TicketPolicy::view` se basa en el permiso `tickets.view`.

## Goals / Non-Goals

**Goals:**
- Vista previa en el detalle sin crear tokens y funcionando con boletos eliminados.
- Una sola fuente para "contenido + nombre del PDF de un boleto". Las seis entradas quedan con el mismo comportamiento observable que hoy.
- Fijar con tests el comportamiento actual **antes** de unificar.

**Non-Goals:**
- No se toca el PDF combinado de la venta (`generateSaleCombinedTickets`, `saleFilename`) ni las descargas múltiples de la creación de venta.
- No se cambia el contenido visual de los PDF ni el diseño del resto del detalle.
- No se extrae el bloque de vista previa del link público a un componente compartido. El link público es una página fuera de Filament con su propio JS, y queda como está.
- No se revisa la protección de otras rutas sueltas de `web.php`, como `tickets.download` o el PDF/Excel de viajes. Si hace falta, va en otro cambio.

## Decisions

### 1. Un método en `TicketPdfService` que devuelve contenido + nombre
Se agrega un método de instancia (por ejemplo `forTicket(Ticket $ticket): array{content: string, filename: string}`) más un helper estático para el nombre (`filenameFor(Ticket)`). El método centraliza:
- la carga de los boletos del pasajero en la venta, con el mismo `with([...])` de hoy
- la elección de variante según `is_round_trip`
- el nombre del archivo

Cada entrada sigue armando su propia respuesta: `streamDownload`, sesión con base64 o `Response::make` inline.

- *Alternativa*: un trait o una clase `TicketPdf` nueva. La descartamos porque el servicio ya es el dueño de la generación y ya se instancia en todos esos lugares.
- `TicketShareController::ticketFilename()` queda como un alias que delega en el helper, para no romper llamadas existentes (incluido `saleShow`, que no lo usa, pero puede haber tests). Se busca cada uso antes de decidir si se elimina.

### 2. Ruta del panel en `routes/web.php` con controlador propio
`GET admin/boletos/{ticket}/pdf?disposition=inline|attachment`, con nombre `admin.boletos.pdf`, que apunta a un controlador nuevo (por ejemplo `AdminTicketPdfController`). La ruta lleva:
- el middleware `auth` (guard `web`, el mismo del panel)
- el `{ticket}` resuelto con `->withTrashed()`
- `can:view,ticket` (o `Gate::authorize('view', $ticket)` en el controlador), que da 403 al usuario sin permiso
- una `disposition` inválida que da 404, igual que en el link público

Respuesta: `Content-Type: application/pdf`, `Content-Disposition` según `disposition` con el nombre del helper, y `Cache-Control: private, no-cache, must-revalidate`, igual que `TicketShareController::pdfResponse`.

- *Alternativa*: una página/acción de Filament que haga stream. La descartamos porque un iframe necesita una URL GET estable que devuelva el PDF crudo, y las acciones de Livewire no la dan.
- *Alternativa*: reusar `sharePdfUrl()`. La descartamos porque crea tokens y no anda con eliminados (ver proposal.md).
- **Sin sesión**: como no hay `redirectGuestsTo`, `auth` intentaría redirigir a `route('login')`, que puede no existir y dar un 500. Se configura en `bootstrap/app.php` `redirectGuestsTo(fn () => filament()->getPanel('admin')->getLoginUrl())`, o la ruta responde 401/403 directo. Antes de elegir hay que verificar que el panel de clientes no dependa de ese redirect: hoy usa su propio guard `client` y sus rutas validan con `ClientSession`, no con `auth`.

### 3. Vista previa como `ViewEntry` con Alpine
Se agrega `ViewEntry::make('pdf_preview')->view('tickets.infolist-pdf-preview')->columnSpanFull()` entre `sale_details` y la sección de historial. El blade:
- copia el marcado de la tarjeta "Vista previa" del link público (mismas clases, título, tarjeta de fallback con "Abrir PDF" / "Descargar PDF")
- reemplaza el `<script>` por `x-data` / `x-init`: `canEmbed = navigator.pdfViewerEnabled === true && matchMedia('(min-width: 768px)').matches`, con un `<template x-if>` para el iframe, de modo que el PDF solo se pida si se va a mostrar
- arma las URLs con `route('admin.boletos.pdf', ...)` y el nombre con `TicketPdfService::filenameFor($record)`

Alpine se inicializa en cada render de Livewire y en `wire:navigate`; un `<script>` inline no se vuelve a ejecutar al navegar dentro del panel (escenario "Navegación dentro del panel").

### 4. Orden de trabajo: tests de caracterización primero
Antes de tocar las seis entradas se agregan tests que fijan, para un boleto de ida y vuelta, uno solo de ida y uno sin fecha:
- qué método del servicio se usa: se espía el servicio con un mock parcial, o se compara el PDF/nombre con lo que devuelven directamente `generateRoundTripTicket` / `generatePassengerTickets`
- el nombre del archivo y el `Content-Disposition` de cada entrada

Recién después se refactoriza, y esos tests no cambian.

## Risks / Trade-offs

- [Generar el PDF en cada apertura del detalle carga el servidor (dompdf)] → El iframe solo se crea en escritorio y bajo demanda (`x-if`). En celular no se pide hasta tocar "Abrir PDF". El link público ya funciona así.
- [Comparar PDFs byte a byte puede fallar por metadatos de fecha] → Los tests verifican variante y nombre (mock parcial del servicio, o cabeceras + `assertDownload`), no bytes.
- [Configurar `redirectGuestsTo` cambia a dónde van los invitados en otras rutas con `auth`] → Hoy no hay rutas `auth` fuera de Filament. Si se descubre alguna, se usa la opción que responde 401/403 en la ruta nueva en lugar del cambio global.
- [Un `iframe` dentro del panel hereda el tema del visor del navegador, no el oscuro del panel] → Se usan las mismas clases de borde/fondo con `dark:` que en el link público. El contenido del PDF no tiene tema, igual que hoy.
- [El refactor de `RescheduleTicket` toca el flujo de reprogramación] → Se reemplaza solo el bloque de generación. Los tests existentes de `RescheduleTicketPageTest` se corren junto con los nuevos.

## Migration Plan

Sin migraciones de datos. El deploy es normal (no se compilan assets nuevos si las clases ya existen en el tema; si no, `npm run build`). Rollback: revertir el commit; la ruta nueva no deja estado.
