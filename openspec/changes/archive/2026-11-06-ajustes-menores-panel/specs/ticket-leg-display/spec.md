## MODIFIED Requirements

### Requirement: Etiqueta de tramo en el PDF
Cada boleto de un diferido en el PDF SHALL llevar una etiqueta visible con su tramo: "IDA" en el boleto de ida y "VUELTA" en el boleto de vuelta. La etiqueta de la vuelta MUST NOT incluir el número del boleto de ida. Un boleto sólo de ida MUST NOT llevar etiqueta de diferido. La etiqueta MUST aparecer en todo PDF que incluya el boleto: el del boleto individual, el de la venta y el que se comparte o descarga desde el link.

#### Scenario: PDF de un diferido sin fecha
- **GIVEN** un diferido de un pasajero con ambos tramos sin fecha
- **WHEN** se genera el PDF del boleto
- **THEN** el PDF incluye dos boletos: uno con la etiqueta "IDA" y otro con "VUELTA"

#### Scenario: La vuelta no muestra el número de la ida
- **GIVEN** un diferido cuyo boleto de ida es el N°15
- **WHEN** se genera el PDF del boleto de vuelta
- **THEN** la etiqueta dice "VUELTA" y el PDF no contiene "VUELTA de N°15"

#### Scenario: PDF de una venta con varios pasajeros
- **GIVEN** un diferido de dos pasajeros
- **WHEN** se genera el PDF de la venta
- **THEN** cada uno de los cuatro boletos lleva su etiqueta: "IDA" en las dos idas y "VUELTA" en las dos vueltas

#### Scenario: PDF de un boleto sólo de ida
- **GIVEN** una venta sólo de ida
- **WHEN** se genera el PDF del boleto
- **THEN** el boleto no lleva etiqueta "IDA" ni "VUELTA"
