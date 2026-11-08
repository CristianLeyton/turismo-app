# Proposal

## Why

Los botones de envío por WhatsApp dicen "Enviar por WhatsApp" y llevan un avión de papel genérico, mientras que en el resto de la aplicación WhatsApp se reconoce por su propio ícono (en la lista de pasajeros del viaje y en los contactos del panel de clientes). El texto largo ocupa lugar, sobre todo en el celular, y el ícono genérico no dice que el envío es por WhatsApp.

## What Changes

Todos los botones de envío por WhatsApp pasan a usar el **ícono de WhatsApp de la aplicación** (`<x-icons.whatsapp>`) y a decir solo **"Enviar"**. Los lugares donde cambia:

| # | Dónde | Hoy | Después |
|---|---|---|---|
| 1 | Modal de entrega, una fila por boleto (al vender y al reprogramar): `delivery-modal.blade.php`, botón de cada fila | avión + "Enviar por WhatsApp" | ícono de WhatsApp + "Enviar" |
| 2 | Modal de entrega, bloque "Enviar todos a un número" (venta con más de un boleto): `delivery-modal.blade.php` | avión + "Enviar todos por WhatsApp" | ícono de WhatsApp + "Enviar todos" |
| 3 | Detalle del boleto en el panel admin, acción del encabezado `enviar_whatsapp` (con el parámetro de descarga automática apagado): `ViewTicket.php` | avión + "Enviar por WhatsApp" | ícono de WhatsApp + "Enviar" |

Los lugares 1 y 2 comparten el botón de `delivery-modal-phone.blade.php`, que es donde se cambia el ícono; el texto de cada uno viene de `delivery-modal.blade.php`.

Los tres llevan el **texto y el ícono en blanco** sobre el verde.

Se conserva el nombre accesible "Enviar por WhatsApp" (título y lector de pantalla) en los tres botones, para que sin el texto completo sigan siendo claros.

**Botones de contacto del panel de clientes** (cambio hecho a mano, ahora documentado): los botones de teléfono y de WhatsApp de cada línea de contacto muestran solo su ícono, con el ícono de WhatsApp en blanco y los nombres "Llamar" y "WhatsApp" como título. Aplica en dos lugares:

| # | Dónde | Hoy | Después |
|---|---|---|---|
| 4 | "¿Necesitás ayuda?" en "Mis boletos": `help-contact-lines.blade.php` | botones "Llamar" y "WhatsApp" con texto | botones solo con ícono |
| 5 | Contactos del ingreso de clientes: `login-contact-lines.blade.php` | botón "WhatsApp" con texto | botón solo con ícono, y el teléfono con título "Llamar" |

**Lo que no cambia:** los enlaces, los números, el mensaje, la normalización de teléfonos ni cuándo se muestran los botones. Tampoco hace falta tocar el de cada pasajero en la lista del viaje (`trip-details.blade.php`), que ya es solo ícono. El campo "Texto del mensaje de WhatsApp" de Parámetros es un campo de texto, no un botón.

## Capabilities

### New Capabilities
<!-- Ninguna. -->

### Modified Capabilities
- `ticket-delivery-modal`: los requisitos que nombran el botón ("Acciones de cada boleto", "Venta sin teléfonos", "Envío por WhatsApp de un boleto", "Enviar todos los boletos a un número" y "Modal de entrega al reprogramar") pasan a decir "Enviar" y "Enviar todos" con el ícono de WhatsApp.
- `ticket-detail`: "Enviar por WhatsApp desde el detalle del boleto" pasa a llamar a la acción "Enviar", con el ícono de WhatsApp.
- `client-tickets-panel`: "Ayuda con las líneas de contacto" pasa a botones solo con ícono.
- `ticket-contact-lines`: nuevo requisito para los contactos del ingreso de clientes, con el botón de WhatsApp solo con ícono.

## Impact

- Código: `resources/views/filament/tickets/delivery-modal-phone.blade.php`, `resources/views/filament/tickets/delivery-modal.blade.php` y `app/Filament/Resources/Tickets/Pages/ViewTicket.php`, `resources/views/filament/client/help-contact-lines.blade.php` y `resources/views/filament/client/login-contact-lines.blade.php`.
- Tests que hoy buscan el texto viejo y hay que actualizar: `RescheduleTicketPageTest` (línea 307), `TicketShareLinkModeTest` (líneas 282, 332-333 y 347), `ClientPanelTicketsTest` y `ClientPanelToggleTest` (botones de contacto solo con ícono), y los de `ViewTicket` que ya usan el nombre interno `enviar_whatsapp` no cambian.
- Sin migraciones, dependencias ni permisos. Tras tocar clases Tailwind hay que correr `npm run build`.
