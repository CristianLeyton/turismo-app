# Proposal

## Why

Los clientes no tienen en el boleto un número al que llamar ante una duda o un cambio. Hoy agregar uno obligaría a editar la plantilla del PDF; se quiere que boletería lo configure desde los parámetros y que aparezca en todos los boletos sin ocupar lugar.

## What Changes

- Nuevo parámetro "Líneas de contacto" en la página de configuración de boletos: una lista de hasta 3 líneas, cada una con un nombre opcional (por ejemplo "Orán") y un teléfono.
- El máximo vive en una sola constante, para poder subirlo en el futuro sin tocar la página ni el PDF.
- Todos los PDF del boleto (venta, reprogramación, link compartido y panel de clientes) muestran las líneas configuradas en un bloque compacto debajo del asiento: un título pequeño "Líneas de contacto" y las líneas en una sola fila, por ejemplo "Orán: 3878-804506 · Tartagal: 3873-309893".
- Cada teléfono del PDF es un enlace `tel:`: al tocarlo desde un celular se abre el marcador con ese número.
- Sin líneas configuradas, el boleto no muestra el bloque y queda igual que hoy.

## Capabilities

### New Capabilities
- `ticket-contact-lines`: configuración de las líneas de contacto (cantidad máxima, datos de cada línea, validación) y su presentación en el PDF del boleto, con los teléfonos como enlaces para llamar.

### Modified Capabilities

## Impact

- **Configuración:** `app/Models/Setting.php`, con la clave nueva, el máximo y la lectura normalizada. `app/Filament/Pages/TicketSettings.php`, con la sección y su guardado.
- **Boleto:** `resources/views/tickets/pdf/passenger-tickets.blade.php`, la única plantilla que usan todos los PDF del boleto.
- **Tests:** feature tests de la página de parámetros y del contenido del PDF.
- **Sin migraciones ni dependencias nuevas:** el parámetro se guarda como JSON en la tabla de configuración existente.
