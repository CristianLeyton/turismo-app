## ADDED Requirements

### Requirement: Precio vigente según la fecha de venta
Con el precio fijo prendido, los montos del boleto y del seguro de una venta SHALL salir del período de precio vigente el día de la emisión: el período más reciente cuyo "Desde" es ese día o anterior. Después del último período MUST seguir rigiendo su monto. La fecha del viaje, que un tramo no tenga fecha y la reprogramación MUST NOT influir en el precio.

#### Scenario: Venta dentro de un período
- **GIVEN** un período desde el 20/11/2026 con boleto 70000 y seguro 5000, y otro desde el 02/12/2026 con boleto 160000 y seguro 8000
- **WHEN** el 25/11/2026 se vende solo ida a un adulto sin menor
- **THEN** el boleto vale 70000

#### Scenario: Primer día del período siguiente
- **GIVEN** los mismos períodos
- **WHEN** el 02/12/2026 se vende solo ida a un adulto con menor
- **THEN** el boleto vale 168000

#### Scenario: Después del último período
- **GIVEN** los mismos períodos
- **WHEN** el 15/03/2027 se vende solo ida a un adulto sin menor
- **THEN** el boleto vale 160000

#### Scenario: Viaje en otro período
- **GIVEN** los mismos períodos
- **WHEN** el 25/11/2026 se vende solo ida a un adulto sin menor para un viaje del 20/12/2026
- **THEN** el boleto vale 70000

#### Scenario: Ida y vuelta con tramo sin fecha
- **GIVEN** los mismos períodos
- **WHEN** el 25/11/2026 se vende ida con fecha y vuelta sin fecha a un adulto sin menor
- **THEN** el boleto de ida vale 140000 y el de vuelta vale 0

#### Scenario: Reprogramación a otro período
- **GIVEN** un boleto vendido el 25/11/2026 a 70000 para el 28/11/2026
- **WHEN** se lo reprograma al 20/12/2026
- **THEN** el boleto sigue valiendo 70000

### Requirement: Venta sin período vigente
Con el precio fijo prendido, si al armar o emitir una venta no hay ningún período con "Desde" igual al día de hoy o anterior, la emisión SHALL rechazarse sin crear ningún boleto y MUST avisar "No hay un precio vigente para hoy. Pedile a un administrador que cargue los precios." La configuración normal no permite llegar a este estado. Este requisito cubre datos dañados o cargados por fuera de la página.

#### Scenario: Sin períodos vigentes
- **GIVEN** el precio fijo prendido y un único período que empieza el 01/12/2026, guardado por fuera de la página
- **WHEN** el 25/11/2026 un vendedor intenta emitir una venta
- **THEN** no se crea ningún boleto y aparece el aviso

## MODIFIED Requirements

### Requirement: Rechazo si cambió el precio durante la venta
Si al emitir el precio calculado de algún pasajero no coincide con el que mostraba la venta, o el precio fijo se prendió o se apagó desde que se armó la venta, la emisión SHALL rechazarse sin crear ningún boleto y MUST avisar "Cambió la configuración de precios. Revisá los precios de la venta y volvé a emitir." Esto incluye una venta que se armó un día y se emite otro con distinto período vigente. Al revisar, la venta MUST mostrar los precios recalculados.

#### Scenario: Cambió el precio del boleto
- **GIVEN** una venta armada con precio del boleto 5000
- **WHEN** un administrador cambia el precio del período vigente a 6000 y el vendedor emite
- **THEN** no se crea ningún boleto, aparece el aviso y el campo "Precio" pasa a mostrar 6000

#### Scenario: Se prendió el precio fijo
- **GIVEN** una venta armada con el precio fijo apagado y un precio cargado a mano
- **WHEN** un administrador prende el precio fijo y el vendedor emite
- **THEN** no se crea ningún boleto y aparece el aviso

#### Scenario: Se apagó el precio fijo
- **GIVEN** una venta armada con el precio fijo prendido
- **WHEN** un administrador lo apaga y el vendedor emite
- **THEN** no se crea ningún boleto, aparece el aviso y el campo "Precio" vuelve a poder editarse

#### Scenario: La venta cruzó a otro período
- **GIVEN** un período desde el 20/11/2026 con boleto 70000 y otro desde el 02/12/2026 con boleto 160000
- **WHEN** una venta se arma el 01/12/2026 a las 23:55 y se emite el 02/12/2026 a las 00:05
- **THEN** no se crea ningún boleto, aparece el aviso y el campo "Precio" pasa a mostrar 160000

#### Scenario: La venta cruzó el día sin cambiar de período
- **GIVEN** un único período desde el 20/11/2026 con boleto 70000
- **WHEN** una venta se arma el 25/11/2026 a las 23:55 y se emite el 26/11/2026 a las 00:05
- **THEN** la venta se emite con el boleto a 70000

### Requirement: Ajuste del seguro al editar el menor
Con el precio fijo prendido, agregar el menor en un tramo SHALL sumar un seguro al boleto de ida de ese pasajero en esa venta, y quitarlo MUST restarlo. El monto del seguro MUST ser el del período vigente en la fecha de la venta del boleto, y no el vigente el día de la edición. Si la venta es anterior al primer período, se usa el período más antiguo. Vale igual si se edita la ida o la vuelta: la vuelta sigue en 0. Si el seguro es 0, el precio no cambia.

#### Scenario: Agregar el menor en la ida
- **GIVEN** seguro 800 y un boleto solo ida de 5000 sin menor
- **WHEN** se edita el boleto y se agrega el menor
- **THEN** el boleto pasa a valer 5800

#### Scenario: Agregar el menor en la vuelta
- **GIVEN** seguro 800 y una ida y vuelta sin menor, con la ida a 10000 y la vuelta a 0
- **WHEN** se edita el boleto de vuelta y se agrega el menor
- **THEN** la ida pasa a valer 10800 y la vuelta sigue en 0

#### Scenario: Quitar el menor en la ida
- **GIVEN** seguro 800 y una ida y vuelta con menor en los dos tramos, con la ida a 11600
- **WHEN** se edita la ida y se quita el menor
- **THEN** la ida pasa a valer 10800

#### Scenario: Seguro cambiado desde la venta
- **GIVEN** un período desde el 20/11/2026 con seguro 5000 y otro desde el 02/12/2026 con seguro 8000, y un boleto solo ida vendido el 25/11/2026 a 75000 con menor
- **WHEN** el 05/12/2026 se edita el boleto y se quita el menor
- **THEN** el boleto pasa a valer 70000

#### Scenario: Seguro cambiado en el mismo período
- **GIVEN** un boleto solo ida de 5800 vendido con menor cuando el período vigente tenía seguro 800, y ese mismo período ahora tiene seguro 1000
- **WHEN** se edita el boleto y se quita el menor
- **THEN** el boleto pasa a valer 4800

#### Scenario: Venta anterior al primer período
- **GIVEN** un boleto solo ida de 5000 vendido a mano el 10/10/2026, y el período más antiguo empieza el 20/11/2026 con seguro 5000
- **WHEN** con el precio fijo prendido se edita el boleto y se agrega el menor
- **THEN** el boleto pasa a valer 10000

#### Scenario: Precio fijo apagado
- **GIVEN** el precio fijo apagado
- **WHEN** se edita un boleto y se agrega el menor
- **THEN** el precio no cambia solo y se puede editar a mano, como hasta ahora
