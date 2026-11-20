# Spec Delta

## MODIFIED Requirements

### Requirement: Selectores de origen y destino detallados
Con el parámetro "Selectores de origen y destino detallados" prendido, Origen y Destino SHALL ser selects con buscador cuando tienen más de 10 opciones, y mostrar cada parada con su posición en la ruta ("3 · Tolosa"). Los destinos MUST agruparse por sentido, con el encabezado "Hacia" y la última parada de la ruta. En Destino el umbral MUST contar las paradas de todos los grupos, no la cantidad de grupos.

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

#### Scenario: Buscador en Destino con muchas paradas
- **GIVEN** el parámetro prendido y una ruta de 12 paradas
- **WHEN** el vendedor elige la primera parada como origen y abre Destino
- **THEN** el select ofrece un buscador, aunque las 11 paradas estén en un solo grupo

#### Scenario: Destino suma las paradas de los dos sentidos
- **GIVEN** el parámetro prendido y un origen con 6 destinos en un sentido y 5 en el otro
- **WHEN** el vendedor abre Destino
- **THEN** el select ofrece un buscador

#### Scenario: Destino sin buscador con pocas paradas
- **GIVEN** el parámetro prendido y un origen con 5 destinos en cada sentido
- **WHEN** el vendedor abre Destino
- **THEN** el select no ofrece buscador
