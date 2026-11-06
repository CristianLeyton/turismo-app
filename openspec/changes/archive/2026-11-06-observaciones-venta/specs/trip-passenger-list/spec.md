# Spec Delta

## Purpose

Define qué columnas y cómo se presentan los pasajeros de un viaje en el modal "Ver detalles" de la lista de viajes, en el PDF y en el Excel del viaje.

## ADDED Requirements

### Requirement: Columnas del modal y del PDF del viaje
Con el parámetro "Observaciones en la venta" prendido, la lista de pasajeros del modal "Ver detalles" y del PDF del viaje SHALL mostrar, en este orden, las columnas Boleto, Nombre, DNI, Asiento, Origen, Destino y Observación, sin la columna Teléfono. Con el parámetro apagado, MUST mostrar Boleto, Nombre, DNI, Teléfono, Asiento, Origen y Destino, sin Observación.

#### Scenario: Modal de detalles
- **GIVEN** un viaje con pasajeros
- **WHEN** con el parámetro prendido, el usuario abre "Ver detalles" del viaje
- **THEN** la tabla de pasajeros tiene las columnas Boleto, Nombre, DNI, Asiento, Origen, Destino y Observación, y no tiene Teléfono

#### Scenario: PDF del viaje
- **GIVEN** un viaje con pasajeros
- **WHEN** con el parámetro prendido, el usuario descarga el PDF del viaje
- **THEN** la tabla de pasajeros tiene las columnas Boleto, Nombre, DNI, Asiento, Origen, Destino y Observación, y no tiene Teléfono

#### Scenario: Parámetro apagado
- **GIVEN** el parámetro apagado y un viaje con un pasajero con observación
- **WHEN** el usuario abre "Ver detalles" o descarga el PDF del viaje
- **THEN** la tabla tiene Teléfono, no tiene Observación y no muestra el texto de la observación

### Requirement: Columnas del Excel del viaje
El Excel del viaje SHALL mantener sus columnas actuales, incluida Teléfono, y, con el parámetro "Observaciones en la venta" prendido, sumar la columna "Observaciones" al final. Con el parámetro apagado, MUST NOT tener esa columna. El texto de la observación MUST verse completo con ajuste de línea en la celda.

#### Scenario: Excel del viaje
- **GIVEN** un viaje con un pasajero que tiene observación
- **WHEN** el usuario descarga el Excel del viaje
- **THEN** la planilla tiene la columna Teléfono y, como última columna, "Observaciones" con el texto completo de la observación

### Requirement: Observación en la fila del pasajero
En el modal, el PDF y el Excel del viaje, la observación de un boleto SHALL mostrarse completa en la fila del pasajero adulto de ese boleto. Las filas de menores acompañantes y de mascotas MUST NOT repetirla. Un pasajero sin observación MUST mostrar un guion en el modal y en el PDF, y la celda vacía en el Excel.

#### Scenario: Pasajero con menor acompañante
- **GIVEN** un boleto con observación cuyo pasajero viaja con un menor
- **WHEN** el usuario abre "Ver detalles" del viaje
- **THEN** la observación aparece en la fila del adulto y la fila del menor no la muestra

#### Scenario: Pasajero sin observación
- **WHEN** el usuario abre "Ver detalles" o el PDF de un viaje con un pasajero sin observación
- **THEN** la celda de observación de ese pasajero muestra un guion

#### Scenario: Observación larga
- **GIVEN** un boleto con una observación de 280 caracteres
- **WHEN** el usuario descarga el PDF del viaje
- **THEN** la observación se lee completa, repartida en varias líneas dentro de su celda, sin salirse de la tabla

### Requirement: Observación destacada
En el modal y en el PDF del viaje, la observación de un pasajero SHALL mostrarse dentro de un recuadro de esquinas redondeadas, con fondo y borde izquierdo de color, el mismo estilo del recuadro "Observación" del detalle del boleto. El recuadro MUST distinguirse también en el PDF impreso en blanco y negro, y el texto MUST tener un tamaño legible, no menor que el del resto de la fila.

#### Scenario: Modal con pasajeros con y sin observación
- **GIVEN** un viaje con un pasajero con observación y otro sin
- **WHEN** el usuario abre "Ver detalles"
- **THEN** la observación aparece en un recuadro redondeado con fondo destacado y el otro pasajero no tiene recuadro

#### Scenario: PDF impreso
- **WHEN** el usuario imprime en blanco y negro el PDF de un viaje con un pasajero con observación
- **THEN** la observación aparece en un recuadro redondeado con fondo gris que la distingue del resto de la fila
