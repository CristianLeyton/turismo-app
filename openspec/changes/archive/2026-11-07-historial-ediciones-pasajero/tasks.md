# Tasks

## 1. Ediciones visibles de un boleto

- [x] 1.1 Crear `tests/Feature/TicketEditHistorySharedTest.php` (`php artisan make:test --phpunit TicketEditHistorySharedTest --no-interaction`) con una venta de ida y vuelta (mismo pasajero) y otra venta aparte del mismo DNI. Casos: edición de nombre/DNI desde la ida visible en la vuelta; precio de la ida no visible en la vuelta; ediciones de otra venta no visibles; hermano eliminado sí cuenta. Verificar que fallan.
- [x] 1.2 Agregar `TicketEdit::PASSENGER_FIELDS` y la consulta de ediciones visibles (propias + campos del pasajero desde boletos con el mismo `passenger_id` y la misma `sale_id`, incluidos eliminados) en `TicketEditHistory`/`TicketEdit`. Verificar que pasan los casos de 1.1.
- [x] 1.3 `TicketEditHistory::groups()`: agregar el boleto de origen a la clave de agrupado y exponer `from_ticket_id`. Test: una edición propia y una compartida en el mismo segundo quedan en grupos distintos y solo la compartida tiene `from_ticket_id`.

## 2. Historial en pantalla

- [x] 2.1 `edit-history.blade.php`, el infolist de "Ver boleto" y `ticket-audit-detail.blade.php`: usar las ediciones visibles (contador, resumen y visibilidad de la sección incluidos) y mostrar "Editado desde el boleto N° X" con link en las compartidas. Tests: el historial de la vuelta contiene "Editado desde el boleto N°" + número de la ida y el cambio de DNI; el de la ida no muestra esa leyenda; la sección aparece en la vuelta aunque no tenga ediciones propias. Soporte dark y celular según filament-ui.

## 3. Auditoría

- [x] 3.1 Scope `Ticket::scopeWithVisibleEdits()` en SQL (`whereExists`) y usarlo en `TicketAuditResource::getEloquentQuery()` y en el filtro de estado "Editados". Test: la vuelta de un pasajero editado desde la ida aparece en la lista y con "Editados".
- [x] 3.2 Filtro "Campo cambiado" (multiple, opciones de `EditTicketForm::fieldLabel()`). Test: elegir DNI muestra el boleto con cambio de DNI (y su hermano) y no uno con solo cambio de precio.
- [x] 3.3 Filtro "Editado por" (usuarios, searchable). Test: un boleto vendido por un usuario y editado por otro aparece al elegir al que editó y no al que vendió.
- [x] 3.4 Filtro "Fecha de edición" desde/hasta, inclusivo, `d/m/Y`. Test con `Carbon::setTestNow()`: ediciones del 03, 04 y 05/11; elegir 04/11–04/11 muestra solo la del 04.
- [x] 3.5 Los tres filtros en una sola subconsulta sobre la misma edición, con indicadores y persistencia en sesión; el filtro ocupa su propia fila (4 columnas desde `lg`) y `filtersFormColumns` queda en 3. Test del escenario "DNI cambiado por Julian el 4/11" (no aparece si ninguna edición cumple todo) y del caso que sí cumple.

## 4. Cierre

- [x] 4.1 `vendor/bin/pint --dirty --format agent`, `npm run build` y `php artisan test --compact --filter="TicketAudit|TicketEditHistory|EditTicket"`; todo en verde.
- [x] 4.2 Revisión en el navegador con la base local: buscar en Auditoría los cambios de DNI de un usuario en una fecha, abrir la vuelta de un pasajero editado desde la ida (escritorio y celular, claro y oscuro).

## Workflow follow-up

- Preguntar si correr la suite completa.
- Archivar con `/opsx:archive`.
