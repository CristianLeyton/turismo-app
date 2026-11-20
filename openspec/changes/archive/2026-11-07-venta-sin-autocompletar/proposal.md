# Proposal

## Why

En la venta y en la edición de boletos, el navegador ofrece autocompletar nombre, DNI y teléfono con datos que guardó de ventas anteriores. Un vendedor puede elegir una sugerencia por error y emitir un boleto con los datos de otra persona, como pasó con el grupo vendido a nombre de un solo pasajero. Los datos de un pasajero tienen que escribirse a mano o traerse del padrón de clientes, que es la única fuente confiable.

## What Changes

- Todos los campos de texto del wizard de venta piden al navegador que no autocomplete: datos del pasajero (DNI, teléfono, apellido, nombre), del menor, de las mascotas, precio y observación.
- Lo mismo en los formularios que se abren desde el wizard: "Crear cliente" (apellido, nombre, DNI, teléfono) y "Nuevo método de pago".
- Lo mismo en "Editar boleto": pasajero, menor, mascotas, precio, observación y motivo de la edición.
- El autocompletado desde el padrón de clientes (al escribir el DNI) sigue funcionando igual.
- Verificación en Chrome y, si ignora la indicación estándar en algún campo, una alternativa que lo evite.

## Capabilities

### New Capabilities

- `ticket-form-autofill`: los formularios de venta y edición de boletos no aceptan datos autocompletados por el navegador; los datos se escriben a mano o vienen del padrón.

### Modified Capabilities

_Ninguna._ El requisito "Autocompletado desde el padrón de clientes" de `ticket-sale` no cambia.

## Impact

- `app/Filament/Resources/Tickets/Schemas/TicketForm.php` (wizard, modal "Crear cliente", "Nuevo método de pago" y el campo de observación compartido).
- `app/Filament/Resources/Tickets/Pages/EditTicket/EditTicketForm.php`.
- Tests nuevos que recorren los dos formularios y verifican que ningún campo de texto quede con autocompletado del navegador.
- Sin migraciones ni cambios de permisos.
