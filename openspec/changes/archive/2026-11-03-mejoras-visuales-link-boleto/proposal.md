# Proposal

## Why

Con el parámetro "Descargar PDF automáticamente al vender" desactivado, al cerrar la venta se abre la página pública del boleto (`/boletos/{token}`). Esa página reutiliza las tarjetas del "Ver boleto" del panel, pero está fuera de Filament y se nota. Las acciones (WhatsApp, descargar, copiar) quedan debajo de todo el detalle y el ícono de WhatsApp es un parlante. La vista previa del PDF en `<iframe>` queda en blanco en la mayoría de los celulares. En modo oscuro los títulos ("Pasajero", "Detalles de venta") salen negros sobre fondo oscuro, y el tema solo se lee al cargar la página. Además, el cliente que revisa su boleto en "Mis boletos" ve el PDF crudo y no esta página, aunque el negocio eligió entregar por link.

## What Changes

- La página pública del link del boleto se rediseña para parecerse lo más posible al "Ver boleto" del panel admin:
  - Encabezado de página como el de Filament: título "Boleto N°X" (o "Boletos de la venta N°X"), subtítulo con origen → destino y las acciones arriba a la derecha (en mobile, debajo del título y a todo el ancho).
  - Las tarjetas de resumen, pasajero y detalles de venta son las mismas del infolist, con el texto base del panel, así que el modo oscuro se ve bien.
  - En una venta con varios pasajeros, cada boleto tiene su propio subtítulo ("Boleto N°X — Apellido Nombre").
  - Ícono correcto de WhatsApp y orden fijo de las acciones: WhatsApp, Descargar PDF, Copiar link.
- Tema claro/oscuro: por defecto sigue el tema del dispositivo y cambia en vivo si el dispositivo cambia. Un selector (claro / oscuro / sistema), igual al del panel, deja elegir otro y la elección se recuerda.
- Vista previa del PDF: en escritorio sigue el `<iframe>`. En pantallas chicas o navegadores sin visor de PDF integrado se muestra una tarjeta con "Abrir PDF" y "Descargar PDF" en vez del iframe en blanco.
- Notificación del panel al cerrar la venta (y al reprogramar) en modo link: se agrega la acción "Copiar link", se agrega "Abrir link" y el texto queda más claro.
- Panel de clientes ("Mis boletos" y la notificación de "Elegir fecha"): con el parámetro desactivado, "Ver boleto" abre la página del link del boleto. Con el parámetro activado sigue abriendo el PDF como hoy.
- Panel admin, detalle del boleto: nueva acción "Ver link" que abre la página del link de ese boleto. Se ve solo con el parámetro desactivado y si el boleto no está eliminado.
- No cambian los datos mostrados en la página pública (siguen el vendedor, el método de pago y el precio).

## Capabilities

### New Capabilities
- `ticket-share-page`: página pública del link de un boleto o de una venta (`/boletos/{token}`). Cubre qué muestra, sus acciones, el tema claro/oscuro, la vista previa del PDF y el acceso desde el detalle del boleto en el panel admin.

### Modified Capabilities
- `client-access`: "Ver boleto" del panel de clientes depende del parámetro de descarga automática (página del link o PDF).
- `ticket-sale`: la entrega por link al cerrar la venta ofrece en la notificación abrir y copiar el link, además de descargar y compartir por WhatsApp.

## Impact

- Vistas: `resources/views/boletos/share.blade.php` (rediseño), `resources/views/tickets/infolist-summary-header.blade.php` / `infolist-sale-details.blade.php` (solo si hace falta ajustar colores heredados, sin cambiar cómo se ven en el panel).
- `app/Http/Controllers/TicketShareController.php` (datos para el encabezado).
- `app/Support/ShareLinkNotification.php` (acciones Abrir/Copiar link), `CreateTicket::finishSaleWithShareLink()` y `RescheduleTicket` (texto de la notificación).
- `app/Filament/Client/Pages/MyTickets.php` y una ruta nueva del panel de clientes que valida la propiedad del boleto y redirige al link (`routes/web.php`, controlador de cliente).
- `app/Filament/Resources/Tickets/Pages/ViewTicket.php` (acción "Ver link" en el encabezado).
- Tests: `tests/Feature/TicketShareLinkModeTest.php`, `tests/Feature/ClientPanelTicketsTest.php`.
- Sin dependencias nuevas ni migraciones.
