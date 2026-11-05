# Spec Delta

## Purpose

Define cómo se identifica el tramo de un boleto (ida, vuelta o sólo ida) en sus presentaciones: el resumen del boleto ("Ver boleto" del panel y página del link) y el PDF, para que el boleto de ida y el de vuelta de un diferido nunca se confundan.

## ADDED Requirements

### Requirement: Tramo del boleto según su marca
Toda presentación de un boleto SHALL decidir si es ida o vuelta sólo por la marca de tramo del boleto. MUST NOT deducirlo del precio, de la fecha ni de si tiene viaje asignado. Un boleto no marcado como vuelta es ida; si además no es diferido, es "sólo ida".

#### Scenario: Ida y vuelta sin fecha
- **GIVEN** una venta diferida con ambos tramos sin fecha
- **WHEN** se presenta cada uno de sus dos boletos
- **THEN** el boleto marcado como ida se presenta como ida y el marcado como vuelta se presenta como vuelta, aunque ninguno tenga viaje

#### Scenario: Ida con precio 0
- **GIVEN** un boleto de ida de un diferido con precio 0
- **WHEN** se presenta el boleto
- **THEN** se presenta como ida, no como vuelta

### Requirement: Resumen del boleto de ida
El resumen del boleto de ida SHALL mostrar su tramo rotulado "Ida", con fecha y horario (o "Fecha y horario a confirmar" si no tiene viaje) y "Asiento ida". En un diferido MUST mostrar además una sección "Vuelta" con los datos del boleto de vuelta: fecha, horario, colectivo, ruta y asiento si tiene viaje, o "Fecha y horario a confirmar" si no lo tiene, siempre con el N° del boleto de vuelta.

#### Scenario: Ida y vuelta sin fecha
- **GIVEN** un diferido con ambos tramos sin fecha
- **WHEN** alguien abre el resumen del boleto de ida
- **THEN** ve "Ida: Fecha y horario a confirmar", "Asiento ida: A asignar" y una sección "Vuelta" con "Fecha y horario a confirmar" y el N° del boleto de vuelta

#### Scenario: Ida con fecha y vuelta sin fecha
- **GIVEN** un diferido con la ida fechada y la vuelta sin fecha
- **WHEN** alguien abre el resumen del boleto de ida
- **THEN** ve la fecha, horario, colectivo y asiento de la ida, y la sección "Vuelta" con "Fecha y horario a confirmar" y el N° del boleto de vuelta

#### Scenario: Ida sin fecha y vuelta con fecha
- **GIVEN** un diferido con la ida sin fecha y la vuelta fechada
- **WHEN** alguien abre el resumen del boleto de ida
- **THEN** ve "Ida: Fecha y horario a confirmar" y la sección "Vuelta" con la fecha, horario, colectivo, ruta y asiento del boleto de vuelta

#### Scenario: Vuelta reprogramada
- **GIVEN** un diferido cuya vuelta sin fecha recibió fecha y asiento después de la venta
- **WHEN** alguien abre el resumen del boleto de ida
- **THEN** la sección "Vuelta" muestra la fecha, horario y asiento actuales del boleto de vuelta

### Requirement: Resumen del boleto de vuelta
El resumen del boleto de vuelta SHALL mostrar su tramo rotulado "Vuelta", con fecha y horario (o "Fecha y horario a confirmar" si no tiene viaje) y "Asiento vuelta". MUST NOT rotular ningún dato del boleto como "Ida" ni mostrar el recuadro de "Viaje de vuelta". En su lugar MUST mostrar una referencia al boleto de ida con su N°.

#### Scenario: Vuelta sin fecha
- **GIVEN** un diferido con la vuelta sin fecha
- **WHEN** alguien abre el resumen del boleto de vuelta
- **THEN** ve "Vuelta: Fecha y horario a confirmar", "Asiento vuelta: A asignar" y la referencia "Boleto de ida N°{id de la ida}", sin ningún rótulo "Ida:" ni "Asiento ida"

#### Scenario: Vuelta con fecha
- **GIVEN** un diferido con la vuelta fechada
- **WHEN** alguien abre el resumen del boleto de vuelta
- **THEN** ve "Vuelta" con su fecha, horario, colectivo y asiento, y la referencia al boleto de ida

#### Scenario: Vuelta sin boleto de ida
- **GIVEN** un boleto de vuelta cuyo boleto de ida fue borrado
- **WHEN** alguien abre el resumen del boleto de vuelta
- **THEN** ve el tramo rotulado "Vuelta" sin referencia a un N° de ida, y la página no falla

### Requirement: Resumen del boleto sólo de ida
El resumen de un boleto que no es diferido SHALL mostrar su tramo rotulado "Ida" y MUST NOT mostrar ninguna sección ni recuadro de vuelta.

#### Scenario: Venta sólo de ida sin fecha
- **GIVEN** un boleto sólo de ida vendido sin fecha
- **WHEN** alguien abre su resumen
- **THEN** ve "Ida: Fecha y horario a confirmar" y ninguna sección de vuelta

### Requirement: Etiqueta de tramo en el PDF
Cada boleto de un diferido en el PDF SHALL llevar una etiqueta visible con su tramo: "IDA" en el boleto de ida y "VUELTA de N°{id de la ida}" en el boleto de vuelta. Un boleto sólo de ida MUST NOT llevar etiqueta de diferido. La etiqueta MUST aparecer en todo PDF que incluya el boleto: el del boleto individual, el de la venta y el que se comparte o descarga desde el link.

#### Scenario: PDF de un diferido sin fecha
- **GIVEN** un diferido de un pasajero con ambos tramos sin fecha
- **WHEN** se genera el PDF del boleto
- **THEN** el PDF incluye dos boletos: uno con la etiqueta "IDA" y otro con "VUELTA de N°{id de la ida}"

#### Scenario: PDF de una venta con varios pasajeros
- **GIVEN** un diferido de dos pasajeros
- **WHEN** se genera el PDF de la venta
- **THEN** cada uno de los cuatro boletos lleva su etiqueta, y cada vuelta referencia el N° de la ida de su mismo pasajero

#### Scenario: PDF de un boleto sólo de ida
- **GIVEN** una venta sólo de ida
- **WHEN** se genera el PDF del boleto
- **THEN** el boleto no lleva etiqueta "IDA" ni "VUELTA"

### Requirement: Ida y vuelta en la misma hoja del PDF
En todo PDF con boletos de un diferido, el boleto de ida de cada pasajero SHALL ir en la misma hoja que su boleto de vuelta, con la ida primero. Las hojas MUST seguir el orden de emisión de las idas. Los boletos sin pareja (sólo ida, o un tramo cuyo hermano fue borrado) MUST ir de a dos por hoja, como hasta ahora.

#### Scenario: PDF de la venta diferida de dos pasajeros
- **GIVEN** un diferido de dos pasajeros, emitido como ida 1, ida 2, vuelta 1, vuelta 2
- **WHEN** se genera el PDF de la venta
- **THEN** la primera hoja tiene la ida y la vuelta del pasajero 1, y la segunda hoja la ida y la vuelta del pasajero 2

#### Scenario: PDF de una venta sólo de ida
- **GIVEN** una venta sólo de ida de cuatro pasajeros
- **WHEN** se genera el PDF de la venta
- **THEN** salen dos hojas con dos boletos cada una, en orden de emisión
