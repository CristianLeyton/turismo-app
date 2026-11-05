# Spec Delta

## MODIFIED Requirements

### Requirement: Presentación igual al "Ver boleto" del panel
La página pública del link (`/boletos/{token}`) SHALL presentar el boleto con la misma estructura y estilo visual que el "Ver boleto" del panel admin. Arriba va un encabezado de página con el título y las acciones, y debajo las mismas tarjetas de resumen del viaje, pasajero y detalles de venta. La página MUST mostrar los mismos datos que hoy: ruta, fechas y horarios, colectivo, asiento, pasajero y acompañantes, N° de venta, fecha de venta, precio, método de pago y vendedor.

#### Scenario: Link de un boleto
- **WHEN** alguien abre el link de un boleto individual
- **THEN** ve el título "Boleto N°{id}", el origen → destino del boleto y las tarjetas de resumen, pasajero y detalles de venta con el mismo diseño que en el panel

#### Scenario: Link de una venta con varios pasajeros
- **WHEN** alguien abre el link de una venta con varios pasajeros
- **THEN** ve el título "Boletos de la venta N°{venta}" y un bloque por cada boleto de ida, cada uno con el subtítulo "Boleto N°{id} — {pasajero}" y sus tarjetas de resumen y pasajero, más una única tarjeta de detalles de venta

#### Scenario: Diferido con vuelta asignada
- **WHEN** alguien abre el link de un boleto de ida y vuelta con la vuelta asignada
- **THEN** la tarjeta de resumen muestra la ida y la vuelta con su colectivo, ruta y asiento, igual que en el panel

#### Scenario: Link inválido o boleto borrado
- **WHEN** alguien abre un link cuyo token no existe o cuyo boleto fue borrado
- **THEN** recibe un 404

## ADDED Requirements

### Requirement: Sólo las idas en pantalla en el link de una venta
En el link de una venta, la página SHALL mostrar en pantalla un bloque por cada boleto de ida y MUST NOT mostrar bloques propios para los boletos de vuelta, porque cada ida ya muestra su vuelta. El PDF de la vista previa, el que se descarga y el que se comparte MUST seguir incluyendo todos los boletos de la venta, idas y vueltas.

#### Scenario: Diferido de dos pasajeros sin fecha
- **GIVEN** una venta diferida de dos pasajeros con ambos tramos sin fecha
- **WHEN** alguien abre el link de la venta
- **THEN** ve dos bloques, uno por cada boleto de ida, cada uno con su sección de vuelta, y ningún bloque de un boleto de vuelta

#### Scenario: PDF del link de la venta
- **GIVEN** una venta diferida de dos pasajeros
- **WHEN** alguien descarga el PDF desde el link de la venta, o lo abre desde la vista previa
- **THEN** el PDF contiene los cuatro boletos: las dos idas y las dos vueltas

#### Scenario: Venta sólo de ida
- **GIVEN** una venta sólo de ida de dos pasajeros
- **WHEN** alguien abre el link de la venta
- **THEN** ve un bloque por cada boleto, como antes

### Requirement: Link de un pasajero en un diferido
Al cerrar una venta diferida de un solo pasajero, el link entregado SHALL ser el del boleto de ida. El PDF de ese link MUST incluir el boleto de ida y el de vuelta.

#### Scenario: Diferido de un pasajero sin fecha
- **GIVEN** el parámetro de descarga automática desactivado
- **WHEN** se cierra una venta diferida de un pasajero con ambos tramos sin fecha
- **THEN** se abre el link del boleto de ida, su resumen muestra la ida con su sección de vuelta, y su PDF contiene la ida y la vuelta
