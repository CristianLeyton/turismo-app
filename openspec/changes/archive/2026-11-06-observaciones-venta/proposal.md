# Proposal

## Why

La oficina necesita dejar notas internas sobre una venta (por ejemplo "baja en el peaje de Metán" o "paga el saldo al subir") y que el chofer y el personal las vean al revisar los pasajeros de un viaje. Hoy no hay dónde escribirlas, así que se pierden o se anotan aparte.

## What Changes

- **Parámetro "Observaciones"** (Parámetros > Venta): prende o apaga toda la funcionalidad y arranca apagado. Solo lo ve y lo cambia quien tiene el permiso nuevo `settings.sale_observations`, que no se asigna por defecto a ningún rol. Apagado, no se cargan ni se muestran observaciones en ninguna pantalla ni archivo, y la lista de pasajeros del viaje queda como antes (con Teléfono y sin Observación). Las observaciones ya cargadas se conservan y reaparecen al prenderlo.
- **Venta**: el paso "Resumen" suma un campo opcional "Observaciones" (hasta 280 caracteres, con contador), ubicado entre el resumen del viaje y los pasajeros. Al emitir, la observación se copia a todos los boletos de la venta (ida, vuelta y boletos sin fecha). Un campo vacío o con solo espacios no guarda nada.
- **Editar boleto**: se puede cambiar o borrar la observación de un boleto. El cambio afecta solo a ese boleto y queda en el "Historial de ediciones".
- **Pasajeros del viaje** (modal "Ver detalles", PDF y Excel): nueva columna "Observación" al final, en la fila del pasajero adulto (no se repite en las filas de menor o mascota). La observación se muestra en un recuadro redondeado, con el mismo estilo que el detalle del boleto.
- **Teléfono**: con el parámetro prendido, la columna se oculta en el modal y en el PDF del viaje, pero no se borra de las vistas, para poder mostrarla de nuevo cuando la lista permita elegir columnas. En el Excel se mantiene.
- **Detalle del boleto y auditoría**: muestran la observación del boleto.
- **Solo interna**: la observación nunca aparece en el PDF del pasajero, en la vista previa de ese PDF, en la página pública del boleto ni en "Mis boletos" del panel de clientes.

## Capabilities

### New Capabilities
- `ticket-observation`: observación interna de un boleto: carga en la venta, edición por boleto, límite de largo, dónde se muestra y dónde nunca se muestra.
- `trip-passenger-list`: columnas y presentación de la lista de pasajeros de un viaje en el modal "Ver detalles", el PDF y el Excel del viaje.

### Modified Capabilities
- `settings-page`: la pestaña Venta suma, con su permiso, el interruptor "Observaciones".
- `ticket-detail`: el "Ver boleto" muestra la observación en los detalles de venta, fuera de la vista previa del PDF.

## Impact

- Parámetros: `app/Models/Setting.php` (constante y helper), `app/Filament/Pages/TicketSettings.php` (sección, `TAB_FIELDS` y guardado), `app/Support/Permissions.php` (permiso nuevo; requiere `php artisan permissions:sync`).
- Base de datos: nueva columna nullable `observation` en `tickets` (migración nueva).
- `app/Models/Ticket.php` (fillable), `app/Models/Trip.php` (`getPassengersWithDetails`).
- Venta: `app/Filament/Resources/Tickets/Schemas/TicketForm.php` (paso "Resumen"), `resources/views/tickets/summary.blade.php` (se divide en dos partes), `app/Filament/Resources/Tickets/Pages/CreateTicket.php` (creación de boletos con y sin fecha).
- Edición: `app/Filament/Resources/Tickets/Pages/EditTicket/EditTicketForm.php` y `EditTicket.php`.
- Vistas: `resources/views/filament/trips/trip-details.blade.php`, `resources/views/pdf/trip-details.blade.php`, `resources/views/excel/trip-details.blade.php`, `resources/views/tickets/infolist-sale-details.blade.php`, `resources/views/filament/resources/ticket-audit/ticket-audit-detail.blade.php`.
- Reprogramación (`app/Services/TicketRescheduleService.php`): debe conservar la observación.
- Requiere `npm run build` por las clases Tailwind nuevas.
- Tests en `tests/Feature/`. Sin dependencias nuevas.
