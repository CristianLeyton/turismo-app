# Tasks

## 1. Link público como PDF

- [x] 1.1 Hacer que `TicketShareController::show` responda con el PDF inline del boleto. Quitar las ramas de venta (`saleShow`, `salePdf`, `saleFilename`) para que un token `s_` dé 404 en `boletos.share` y en `boletos.share.pdf`. Verificar con tests en `TicketShareLinkModeTest`:
  - el link de un boleto devuelve `application/pdf` inline con su nombre de archivo;
  - un diferido incluye la ida y la vuelta;
  - un boleto borrado o un token inválido da 404;
  - un token de venta da 404;
  - la descarga devuelve `attachment`.
- [x] 1.2 Eliminar `resources/views/boletos/share.blade.php`, los tests de la página HTML (tarjetas, acciones, tema, vista previa, solo idas, logo, líneas de contacto como `tel:`) y los helpers del controlador que solo usaba la vista. Pedirle aprobación al usuario antes de quitar tests, según CLAUDE.md. Verificar que `php artisan test --compact tests/Feature/TicketShareLinkModeTest.php` pase.
- [x] 1.3 Ajustar `ClientPanelTicketsTest` y `ClientPanelToggleTest` para que "Ver boleto" con el parámetro desactivado y `/boletos/{token}` con el panel desactivado esperen el PDF y no la página. Verificar que los dos archivos de test pasen.
- [x] 1.4 Ajustar la prueba de color de la página pública (`test_la_pagina_del_boleto_usa_el_color_de_la_empresa`) para que verifique los colores de marca en el PDF del link, y revisar que `BrandColorUsageTest` no referencie la vista eliminada. Verificar que los dos tests pasen.

## 2. Mensaje y teléfonos de WhatsApp

- [x] 2.1 Cambiar `Setting::WHATSAPP_MENSAJE_DEFAULT` a "Podés acceder a tu(s) boleto(s) desde aquí: {link}" y actualizar los textos o tests de `TicketSettings` que lo citan. Verificar con `php artisan test --compact --filter=whatsapp`.
- [x] 2.2 Refactorizar `WhatsAppShare` para exponer los candidatos de la venta con el `passenger_id` de origen, normalizados y sin repetir (`candidatesForSale()`). Quitar `forSale()` cuando quede sin uso. Verificar con un test de deduplicación (pasajero y padrón con el mismo número) y de normalización (0 inicial, sin código de país, ya con 54).

## 3. Datos y acción del modal de entrega

- [x] 3.1 Crear `App\Support\TicketDelivery` (con `php artisan make:class`), que a partir de los boletos arme las filas (título "Boleto N°id — Apellido Nombre", origen → destino, link, descarga, teléfono preseleccionado), los teléfonos de la venta y la plantilla. Incluir el selector de boletos de ida por pasajero para una venta. Verificar con un test feature:
  - un diferido de dos pasajeros da dos filas de ida;
  - se preselecciona el teléfono del pasajero, después el del padrón y después el primero de la venta;
  - una venta sin teléfonos da `phones` vacío.
- [x] 3.2 Crear la vista `resources/views/filament/tickets/delivery-modal.blade.php` con el componente Alpine:
  - filas con "Enviar por WhatsApp", "Ver PDF", "Copiar link" y "Descargar PDF";
  - selector "Enviar a" con "Otro número…" y el campo directo cuando no hay teléfonos;
  - botón deshabilitado con menos de 8 dígitos;
  - bloque "Enviar todos a un número" solo con más de una fila;
  - soporte `dark:` y colores `primary`, siguiendo la skill filament-ui.

  Verificar con `BrandColorUsageTest` y con un test que renderice la vista y encuentre los links `wa.me` esperados, el texto con `{link}` reemplazado y la ausencia del bloque "Enviar todos" con una sola fila.
- [x] 3.3 Crear `App\Filament\Actions\TicketDeliveryAction` con las variantes:
  - venta: título "Venta N°X realizada correctamente", texto "Se vendió 1 boleto." / "Se vendieron N boletos.", "Nueva venta" e "Ir al inicio";
  - reprogramación: título "Boleto N°X reprogramado correctamente" y "Cerrar".

  La acción autoriza cada boleto con `can('view')`. Verificar con tests Livewire de los títulos, los textos, las acciones del pie y que un id no autorizado no se muestre.

## 4. Integración en la venta

- [x] 4.1 En `CreateTicket`, modo link:
  - `finishSaleWithShareLink` deja de enviar la notificación y de usar `auto_download_url`, y escribe el flash `ticket_delivery`;
  - `getRedirectUrl()` lleva a la página de venta;
  - `mount()` abre `entregaBoletos` como `defaultAction` cuando hay flash;
  - si falla al preparar los links, se mantiene la notificación de advertencia sin flash.

  Verificar con tests:
  - al vender con el parámetro desactivado se redirige a crear y el modal se monta con los ids de las idas;
  - no se ejecuta `window.open`;
  - recargar sin flash no abre el modal;
  - con el parámetro activado o sin configurar, el flujo de descarga no cambia.
- [x] 4.2 Reescribir en `TicketShareLinkModeTest` los tests de la notificación (`test_la_notificacion_*`, `test_con_modo_link_*`, `test_un_diferido_de_un_pasajero_entrega_el_link_de_la_ida`) para que verifiquen el modal. Verificar que el archivo pase.

## 5. Integración en la reprogramación

- [x] 5.1 En `RescheduleTicket`, modo link: quitar la notificación y el `window.open`, escribir el flash `ticket_delivery` (kind `reschedule`) y redirigir a `view`. En `ViewTicket::mount()`, abrir la acción como `defaultAction` cuando hay flash. Verificar en `RescheduleTicketPageTest`:
  - con el parámetro desactivado se llega al detalle con el modal de reprogramación de una fila y sin "Enviar todos";
  - con el parámetro activado se entrega el PDF como hasta ahora.

## 6. Limpieza e integración

- [x] 6.1 Eliminar `App\Support\ShareLinkNotification` y su render hook en `AdminPanelProvider`, junto con `test_el_panel_escucha_el_evento_de_copiar_link`, con aprobación del usuario. Verificar con `grep` que no quedan referencias y que `php artisan test --compact tests/Feature/TicketShareLinkModeTest.php` pase.
- [x] 6.2 Correr `vendor/bin/pint --dirty --format agent` y la suite de los archivos tocados: `TicketShareLinkModeTest`, `RescheduleTicketPageTest`, `ClientPanelTicketsTest`, `ClientPanelToggleTest`, `TicketSettingsTest`, `BrandColorUsageTest`, `AdminTicketPdfTest`, `TicketPdfConsistencyTest` y `TicketContactLinesPdfTest`. Verificar que todo pase.
- [x] 6.3 Prueba manual en el navegador con `npm run build`, con la descarga automática desactivada:
  - vender a dos pasajeros, uno sin teléfono, y comprobar el modal, la preselección, "Otro número…", el envío de todos, copiar, ver y descargar, "Nueva venta" e "Ir al inicio", y que al recargar no se reabre;
  - reprogramar un boleto y comprobar su modal;
  - abrir el link y comprobar que se ve el PDF.

## 7. Detalle del boleto

- [x] 7.1 Agregar `TicketDelivery::phoneForTicket()` (pasajero y después padrón) y volver a sumar `WhatsAppShare::url()`. En `ViewTicket`, reemplazar "Ver link" por "Enviar por WhatsApp" (modo link, vigente, con teléfono) y generar el token en `mount()` en modo link con el boleto vigente. Verificar con tests: acción visible con teléfono propio y del padrón, oculta sin teléfono, con el parámetro activado y con el boleto eliminado; URL `wa.me` con el link; token generado solo en modo link.
- [x] 7.2 En `tickets/infolist-pdf-preview`, sumar "Copiar link" en el encabezado (visor) y en la tarjeta en lugar de "Descargar PDF" (modo link y boleto vigente). Verificar en `AdminTicketPdfTest` que se ve el link público con el parámetro desactivado y que con el parámetro activado sigue "Descargar PDF", sin token.

## Workflow follow-up

- Actualizar el `## Purpose` de `openspec/specs/ticket-share-page/spec.md` después del archive, porque deja de ser una página: "Link público de un boleto (`/boletos/{token}`) que entrega su PDF".
- Archivar el cambio con `/opsx:archive` cuando esté implementado y probado.
