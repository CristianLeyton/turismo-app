# Proposal

## Why

El PDF de un boleto sin fecha dice, con el número fijo en el texto, que el pasajero debe elegir fecha y horario "con al menos 24hs de anticipación". Hoy esa regla no se controla en ningún lado: el cliente puede elegir desde su panel un horario que sale dentro de una hora, y la empresa no puede cambiar las 24hs sin tocar el código. La empresa necesita decidir cuántas horas de anticipación exige, que el panel de clientes las respete y que el PDF diga el mismo número.

## What Changes

- Un parámetro nuevo en Parámetros > Clientes: **anticipación mínima para elegir fecha**, en horas (por defecto 24; 0 significa sin mínimo). Solo aparece cuando "Panel de clientes activo" está encendido.
- En el panel de clientes, "Elegir fecha" **solo ofrece horarios que salen con al menos esas horas de anticipación**, y el servidor lo vuelve a comprobar al confirmar. No cambia nada para quien reprograma desde administración.
- El PDF de un boleto sin fecha **muestra las horas configuradas** en el aviso "Al adquirir el pasaje queda en responsabilidad del pasajero elegir fecha y horario con al menos Nhs de anticipación…". Con 0, el aviso omite esa condición.
- Respuesta a la duda sobre el calendario: **sí**, las excepciones por fecha y los días de la semana de Parámetros ya aplican al selector del cliente (el calendario deshabilita las fechas sin servicio y el servidor las rechaza, sin el permiso de vender en días sin servicio). Este cambio no toca esa regla, y la nueva se suma a ella.
- Las otras menciones de "24hs" del PDF (política de cambios y devoluciones) **no cambian**: son otra regla.

## Capabilities

### New Capabilities
- `client-advance-notice`: la anticipación mínima con la que un cliente puede elegir el horario de su boleto sin fecha desde su panel, y cómo se informa cuando no hay horarios que la cumplan.

### Modified Capabilities
- `settings-page`: suma el parámetro de anticipación mínima a la pestaña Clientes, visible solo con el panel de clientes activo, con su validación y su guardado.
- `ticket-pdf`: suma el aviso de los boletos sin fecha, que muestra las horas de anticipación configuradas.

## Impact

- Código: `app/Models/Setting.php` (parámetro y valor por defecto), `app/Filament/Pages/TicketSettings.php` (campo, `TAB_FIELDS` y guardado), `app/Filament/Client/Schemas/ClientTicketAssignForm.php` (horarios ofrecidos), `app/Services/ClientTicketAssignmentService.php` (control en el servidor), el servicio de PDF y `resources/views/tickets/pdf/passenger-tickets.blade.php`.
- Tests: `tests/Feature/TicketSettingsTest.php`, `ClientPanelTicketsTest.php` y el test del PDF de boletos sin fecha (`PendingTicketPdfTest.php`).
- Sin migraciones: el parámetro se guarda en la tabla de configuración existente. Sin dependencias nuevas ni cambios de permisos; usa el permiso actual de la pestaña Clientes.
