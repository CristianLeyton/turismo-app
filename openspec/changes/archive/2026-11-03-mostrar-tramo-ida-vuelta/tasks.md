# Tasks

Referencias: `specs/ticket-leg-display/spec.md` (qué muestra cada tramo) y `specs/ticket-share-page/spec.md` (qué boletos se ven en el link). Sin design.md: es un cambio sólo de presentación, sin cambios de datos ni de arquitectura.

Nota técnica: la sección "Vuelta" de la ida se arma desde el boleto de vuelta hermano (misma venta y pasajero, marcado como vuelta), no desde el `return_trip_id` de la ida. Así refleja la vuelta aunque se haya reprogramado después de la venta, y funciona igual si la vuelta tiene fecha o no.

## 1. Boleto hermano en el modelo

- [x] 1.1 Agregar a `Ticket` un helper `roundTripReturnTicket(): ?self`, simétrico a `roundTripOutboundTicket()` (misma venta y pasajero, `is_return_leg = true`, sin borrar), y verificarlo con un test unitario o de feature que cubra: ida con vuelta, ida sin vuelta (sólo ida) y vuelta borrada

## 2. Resumen del boleto ("Ver boleto" y link)

- [x] 2.1 En `resources/views/tickets/infolist-summary-header.blade.php`, rotular la primera fila según el tramo: "Ida:"/"Asiento ida" para la ida, "Vuelta:"/"Asiento vuelta" para la vuelta (con "Fecha y horario a confirmar" y "A asignar" cuando no tiene viaje), verificado con los tests de 2.4
- [x] 2.2 Para la ida de un diferido, reemplazar el bloque actual de vuelta (basado en `returnTrip` y el recuadro "Viaje de vuelta") por una sección "Vuelta" armada con el boleto hermano: fecha, horario, colectivo, ruta y asiento si tiene viaje, o "Fecha y horario a confirmar" y siempre el N° del boleto de vuelta, verificado con los tests de 2.4
- [x] 2.3 Para la vuelta, quitar el recuadro "Viaje de vuelta" y mostrar la referencia "Ida: boleto N°{id}" desde `roundTripOutboundTicket()`, sin fallar si la ida no existe; para un boleto sólo de ida, no mostrar ninguna sección de vuelta. Verificado con los tests de 2.4
- [x] 2.4 Agregar tests de feature (p. ej. `tests/Feature/TicketLegDisplayTest.php`, creado con `php artisan make:test --phpunit`) que rendericen "Ver boleto" o la página del link para cada escenario de `ticket-leg-display`: ida y vuelta sin fecha, ida con fecha + vuelta sin fecha, ida sin fecha + vuelta con fecha, vuelta reprogramada, vuelta sin ida, sólo ida sin fecha. Verificar con `php artisan test --compact --filter=TicketLegDisplayTest`
- [x] 2.5 Confirmar que `test_un_diferido_con_vuelta_asignada_muestra_ida_y_vuelta` de `tests/Feature/TicketShareLinkModeTest.php` sigue pasando (ajustar sólo los textos esperados si cambió el rótulo) con `php artisan test --compact --filter=test_un_diferido_con_vuelta_asignada_muestra_ida_y_vuelta`

## 3. Link de la venta: sólo idas en pantalla

- [x] 3.1 En `resources/views/boletos/share.blade.php` (o en la carga de `TicketShareController` para el link de venta), iterar sólo los boletos que no son vuelta para los bloques en pantalla, sin tocar la generación del PDF de vista previa, descarga y compartir (sigue usando todos los boletos de la venta). Verificar con los tests de 3.3
- [x] 3.2 En `CreateTicket::finishSaleWithShareLink`, elegir para el link de un pasajero el boleto de ida de forma explícita (no `tickets()->first()` sin orden). Verificar con el test de 3.3
- [x] 3.3 Actualizar y agregar tests en `tests/Feature/TicketShareLinkModeTest.php`: link de venta diferida de dos pasajeros muestra sólo los dos bloques de ida; el PDF del link de esa venta contiene los cuatro boletos; venta sólo de ida sigue con un bloque por boleto (`test_la_pagina_de_una_venta_muestra_un_bloque_por_boleto`); diferido de un pasajero entrega el link del boleto de ida. Verificar con `php artisan test --compact tests/Feature/TicketShareLinkModeTest.php`

## 4. Etiqueta de tramo en el PDF

- [x] 4.1 En `resources/views/tickets/pdf/passenger-tickets.blade.php`, agregar una etiqueta visible junto a la ruta del encabezado de cada boleto: "IDA" para la ida de un diferido, "VUELTA de N°{id de la ida}" para la vuelta, y nada para un boleto sólo de ida. Verificar con los tests de 4.2
- [x] 4.2 Agregar tests en `tests/Feature/PendingTicketPdfTest.php` (renderizando la vista del PDF, como hacen los tests existentes) para: diferido sin fecha de un pasajero (ambas etiquetas), venta de dos pasajeros (cada vuelta referencia la ida de su pasajero) y sólo ida (sin etiqueta). Verificar con `php artisan test --compact tests/Feature/PendingTicketPdfTest.php`

- [x] 4.3 Armar las hojas del PDF en un único helper de `TicketPdfService` que use `passenger-tickets.blade.php`: cada ida con su vuelta en la misma hoja (ida primero, en orden de emisión de las idas) y los boletos sin pareja de a dos por hoja. Verificar con tests en `tests/Feature/PendingTicketPdfTest.php` (diferido de dos pasajeros emitido ida, ida, vuelta, vuelta; venta sólo de ida de cuatro) con `php artisan test --compact tests/Feature/PendingTicketPdfTest.php`

## 5. Cierre

- [x] 5.1 Correr `vendor/bin/pint --dirty --format agent` y los tests de los grupos 1 a 4 juntos (`php artisan test --compact --filter="TicketLegDisplayTest|TicketShareLinkModeTest|PendingTicketPdfTest"`), y verificar a mano en el navegador una venta diferida sin fecha de 1 y de 2 pasajeros: "Ver boleto" de la ida y de la vuelta, el link que se abre al vender y el PDF descargado

## Workflow follow-up

- Archivar el cambio con `/opsx:archive` una vez implementado y verificado.
