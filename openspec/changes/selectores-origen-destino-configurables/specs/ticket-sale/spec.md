# Spec Delta

## ADDED Requirements

### Requirement: Selectores de origen y destino simples
Con el parámetro "Selectores de origen y destino detallados" apagado, que es su valor por defecto, Origen y Destino del paso "Buscar viaje" SHALL ser selects nativos del navegador, sin buscador, con una lista plana de localidades, solo con su nombre. Los destinos MUST ordenarse por la posición de la parada en su ruta y MUST NOT repetir localidades ni agruparse por sentido.

#### Scenario: Origen con el parámetro apagado
- **GIVEN** el parámetro apagado y una línea con la ruta Orán → Pichanal → Salta
- **WHEN** el vendedor elige el colectivo de esa línea y abre Origen
- **THEN** ve un select nativo con "Orán", "Pichanal" y "Salta", sin números ni buscador

#### Scenario: Destino con ida y vuelta en la línea
- **GIVEN** el parámetro apagado y una línea con las rutas Orán → Pichanal → Salta y Salta → Pichanal → Orán
- **WHEN** el vendedor elige "Pichanal" como origen y abre Destino
- **THEN** ve "Salta" y "Orán" en una sola lista, sin encabezados de grupo y sin repetir localidades

#### Scenario: Valor inicial
- **WHEN** nadie configuró el parámetro y el vendedor abre la venta
- **THEN** Origen y Destino se muestran en el estilo simple

### Requirement: Selectores de origen y destino detallados
Con el parámetro "Selectores de origen y destino detallados" prendido, Origen y Destino SHALL ser selects con buscador cuando tienen más de 10 opciones, y mostrar cada parada con su posición en la ruta ("3 · Tolosa"). Los destinos MUST agruparse por sentido, con el encabezado "Hacia" y la última parada de la ruta.

#### Scenario: Destino agrupado por sentido
- **GIVEN** el parámetro prendido y una línea con las rutas Orán → Pichanal → Salta y Salta → Pichanal → Orán
- **WHEN** el vendedor elige "Pichanal" como origen y abre Destino
- **THEN** ve el grupo "Hacia Salta" con "3 · Salta" y el grupo "Hacia Orán" con "3 · Orán"

#### Scenario: Buscador con muchas paradas
- **GIVEN** el parámetro prendido y un colectivo cuyas rutas tienen 12 paradas distintas
- **WHEN** el vendedor abre Origen
- **THEN** el select ofrece un buscador

#### Scenario: Sin buscador con pocas paradas
- **GIVEN** el parámetro prendido y un colectivo cuyas rutas tienen 5 paradas distintas
- **WHEN** el vendedor abre Origen
- **THEN** el select no ofrece buscador

### Requirement: Mismas opciones válidas en los dos estilos
El parámetro de los selectores SHALL cambiar solo la presentación. En los dos estilos, Origen y Destino MUST ofrecer las mismas localidades: las paradas de las rutas activas de todos los colectivos de la línea del colectivo elegido, sin localidades inactivas y, en Destino, solo paradas posteriores al origen. La preselección del horario único MUST funcionar igual.

#### Scenario: Refuerzo con el parámetro apagado
- **GIVEN** el parámetro apagado y una línea cuyo colectivo de refuerzo tiene una ruta con una parada que la ruta del regular no tiene
- **WHEN** el vendedor elige el colectivo regular y abre Origen
- **THEN** esa parada aparece entre las opciones

#### Scenario: Mismas localidades al cambiar el parámetro
- **WHEN** se comparan las opciones de Destino para el mismo colectivo y el mismo origen, con el parámetro prendido y apagado
- **THEN** las dos listas contienen las mismas localidades

#### Scenario: Horario único con el parámetro apagado
- **GIVEN** el parámetro apagado
- **WHEN** el vendedor elige un origen y un destino que tienen un solo horario ofrecible
- **THEN** ese horario queda preseleccionado
