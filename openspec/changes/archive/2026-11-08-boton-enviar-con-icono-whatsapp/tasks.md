# Tasks

## 1. Modal de entrega

- [x] 1.1 En `delivery-modal-phone.blade.php`, reemplazar el ícono del avión por el componente `<x-icons.whatsapp>` dentro del botón, con `title` y `aria-label` "Enviar por WhatsApp" (o "Enviar todos por WhatsApp" para el bloque de todos), y en `delivery-modal.blade.php` cambiar `buttonLabel` a "Enviar" y "Enviar todos". Verificar con tests de que el modal de una venta y el de una reprogramación muestran el botón "Enviar" con `data-icon="whatsapp"` y sin el avión, que el bloque de varios boletos dice "Enviar todos", y que el enlace, el deshabilitado con un número incompleto y el orden de los botones no cambian. Actualizar `RescheduleTicketPageTest` y `TicketShareLinkModeTest` donde buscan el texto viejo.

## 2. Detalle del boleto

- [x] 2.1 En `ViewTicket`, cambiar la acción `enviar_whatsapp` a la etiqueta "Enviar" con el ícono de WhatsApp como HTML y el `title` "Enviar por WhatsApp". Verificar con tests de que la acción sigue visible solo con enlace y teléfono, con la misma URL y abriendo en pestaña nueva, y que su ícono es el de WhatsApp y su etiqueta es "Enviar".

## 3. Botones de contacto del panel de clientes

- [x] 3.1 Dejar los botones de teléfono y de WhatsApp de `help-contact-lines.blade.php` y `login-contact-lines.blade.php` solo con ícono (el de WhatsApp en blanco), con los títulos "Llamar" y "WhatsApp". Verificar en `ClientPanelTicketsTest` y `ClientPanelToggleTest` con: los enlaces `tel:` y `wa.me` no cambian, los botones llevan `data-icon="whatsapp"` y su título, y no hay texto visible "Llamar" ni "WhatsApp".

## 4. Cierre

- [x] 4.1 Correr `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter="TicketShareLinkMode|RescheduleTicketPage|AdminTicketPdf|ClientPanel|BrandColorUsage"`; verificar que pasan y avisar que hay que correr `npm run build` para ver las clases nuevas.

## Workflow follow-up

- Archivar el cambio cuando se haya revisado en el navegador.
