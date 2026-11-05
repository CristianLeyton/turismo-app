# Proposal

## Why

En una venta diferida (ida y vuelta), sobre todo sin fecha, el boleto de ida y el de vuelta se ven casi iguales en "Ver boleto", en el link que se abre al cerrar la venta y en el PDF. Los dos dicen "Ida:" y "Asiento ida", y los dos muestran un recuadro "Viaje de vuelta". El vendedor no puede distinguir cuál es la ida y cuál la vuelta, aunque los datos están bien guardados (cada boleto está marcado como ida o como vuelta).

## What Changes

- El resumen del boleto ("Ver boleto" del panel y la página del link) identifica el tramo según la marca del boleto:
  - La ida muestra "Ida", su asiento de ida y, en un diferido, la información de su vuelta: fecha, horario, colectivo y asiento si la vuelta tiene viaje, o "Fecha y horario a confirmar" y el N° del boleto de vuelta si no lo tiene.
  - La vuelta muestra "Vuelta" y "Asiento vuelta" con sus propios datos, y una referencia al boleto de ida (N°) en lugar del recuadro "Viaje de vuelta".
  - Un boleto sólo de ida no muestra ninguna sección de vuelta.
- El link de una venta con varios pasajeros muestra en pantalla un bloque por cada boleto de ida (que ya incluye la vuelta), no por cada boleto. El PDF que se comparte y descarga sigue incluyendo todos los boletos de la venta, idas y vueltas.
- El link de una venta con un solo pasajero apunta siempre al boleto de ida, y su PDF sigue incluyendo la ida y la vuelta.
- Cada boleto del PDF lleva una etiqueta de tramo: "IDA" o "VUELTA de N°{id de la ida}". Un boleto sólo de ida no lleva etiqueta de diferido.
- La tabla de boletos del panel admin no cambia.

## Capabilities

### New Capabilities
- `ticket-leg-display`: cómo se identifica el tramo (ida o vuelta) de un boleto en sus presentaciones: el resumen del boleto ("Ver boleto" y link) y el PDF.

### Modified Capabilities
- `ticket-share-page`: el link de una venta con varios pasajeros muestra en pantalla sólo los boletos de ida, mientras el PDF compartido y descargado conserva todos los boletos. El link de un pasajero en un diferido es el de la ida.

## Impact

- Vistas: `resources/views/tickets/infolist-summary-header.blade.php` (usada por "Ver boleto" y por `boletos/share.blade.php`), `resources/views/boletos/share.blade.php`, `resources/views/tickets/pdf/passenger-tickets.blade.php`.
- `App\Http\Controllers\TicketShareController` (carga de los boletos del link de venta) y `CreateTicket::finishSaleWithShareLink` (boleto elegido para el link de un pasajero).
- Sin cambios de base de datos, rutas ni dependencias. Se reutilizan `Ticket::isReturnLeg()` y `Ticket::roundTripOutboundTicket()`.
- Tests: `tests/Feature/TicketShareLinkModeTest.php`, `tests/Feature/PendingTicketPdfTest.php` y tests nuevos para el resumen del boleto.
