# Proposal

## Why

Con la descarga automática desactivada, hoy el link público del boleto abre una página HTML con tarjetas, botones y un visor. El pasajero solo necesita el PDF. Además, al vender, la entrega llega en una notificación que se va sola. Esa notificación no deja compartir cada boleto por separado, manda al vendedor a otra pestaña para elegir el destinatario y no deja escribir un número que no esté en la venta. Ninguna venta real usa todavía esta función, así que es buen momento para simplificarla sin cuidar los links viejos.

## What Changes

- **BREAKING:** `/boletos/{token}` responde directamente con el PDF del boleto en línea, igual que el PDF del panel. Desaparecen la página HTML del link, el selector de tema, el logo con enlace, los botones de la página y la vista previa embebida. En Android el PDF puede descargarse en lugar de verse, y está aceptado.
- **BREAKING:** al vender ya no se genera el link de la venta (PDF combinado, token `s_`). Cada pasajero recibe el link de su boleto de ida, cuyo PDF incluye la vuelta si es diferido.
- Con la descarga automática desactivada, al terminar una venta o una reprogramación ya no se abre una pestaña ni aparece la notificación con acciones. Se abre un modal que:
  - confirma que los boletos se vendieron o que el boleto se reprogramó correctamente;
  - muestra una fila por boleto entregado, con "Enviar por WhatsApp", "Ver PDF", "Copiar link" y "Descargar PDF";
  - en cada fila deja elegir el número de WhatsApp entre los teléfonos de la venta, con el del pasajero preseleccionado, o escribir otro;
  - con más de un boleto, ofrece "Enviar todos a un número": un solo mensaje de WhatsApp con los links de todos los boletos;
  - cierra con "Nueva venta" o "Ir al inicio" al vender, y con "Cerrar" al reprogramar.
- Si la venta no tiene teléfonos, el modal muestra directamente el campo para escribir el número.
- El mensaje de WhatsApp por defecto pasa a ser "Podés acceder a tu(s) boleto(s) desde aquí: {link}". Al enviar todos, `{link}` se reemplaza por una línea por boleto: "Apellido Nombre (N°id): link".
- En el detalle del boleto ("Ver boleto" del panel), con la descarga automática desactivada y el boleto vigente:
  - "Ver link" se reemplaza por "Enviar por WhatsApp", visible solo si el pasajero tiene teléfono (propio o del padrón);
  - la vista previa suma "Copiar link" (en el encabezado con el visor, en la tarjeta del celular en lugar de "Descargar PDF");
  - el link público se genera al abrir el detalle.
- "Ver boleto" del panel de clientes sigue apuntando al link del boleto, que ahora abre el PDF.
- Con la descarga automática activada no cambia nada.

## Capabilities

### New Capabilities
- `ticket-delivery-modal`: el modal de entrega de boletos por link al cerrar una venta o una reprogramación. Cubre la confirmación, las acciones por boleto, la elección o carga del número de WhatsApp, el envío de todos a un número y las salidas del modal.

### Modified Capabilities
- `ticket-share-page`: el link público pasa a ser el PDF del boleto en línea. Se quitan los requisitos de la página HTML (presentación, acciones, tema, vista previa, solo idas en el link de venta, logo). Se mantienen el 404 para tokens inválidos o boletos borrados y el link de la ida en un diferido. "Ver link" en el detalle se quita.
- `ticket-sale`: la entrega con la descarga automática desactivada abre el modal de entrega en lugar de la pestaña y la notificación. Se elimina el requisito "Notificación de la entrega por link".
- `settings-page`: cambia el texto por defecto del mensaje de WhatsApp.
- `ticket-detail`: "Enviar por WhatsApp" y "Copiar link" en el detalle; la tarjeta del celular cambia "Descargar PDF" por "Copiar link" en modo link; el detalle genera el link público en modo link.
- `client-access`: "Ver boleto" con el parámetro desactivado abre el PDF del link del boleto en lugar de la página del link.
- `brand-color`: deja de existir la página pública del boleto como lugar donde se aplica el color. El color sigue en el PDF.

## Impact

- **Código:**
  - `TicketShareController`: `show` pasa a devolver el PDF; se eliminan `ticketShow` y `saleShow`.
  - Se elimina la vista `resources/views/boletos/share.blade.php`.
  - `CreateTicket::finishSaleWithShareLink` deja de redirigir con `window.open` y abre el modal en la página de venta.
  - `RescheduleTicket` hace lo mismo en el detalle del boleto.
  - `ShareLinkNotification` se reemplaza por una acción o modal compartido.
  - `WhatsAppShare` gana candidatos por pasajero y armado del mensaje múltiple.
  - `Setting::WHATSAPP_MENSAJE_DEFAULT` cambia de texto.
- **Rutas:** `boletos.share` mantiene la URL y cambia la respuesta. `boletos.share.pdf` se conserva para la descarga (`disposition=attachment`).
- **Tests:** `TicketShareLinkModeTest` se reescribe en buena parte. Hay que ajustar `ClientPanelTicketsTest`, `ClientPanelToggleTest`, `AdminTicketPdfTest` y los tests de reprogramación que verifican la notificación.
- **Sin cambios de dependencias ni migraciones.** La columna `share_token` de `sales` queda sin uso nuevo.
