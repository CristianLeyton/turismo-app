# Spec Delta

## ADDED Requirements

### Requirement: Observación en los detalles de venta
Con el parámetro "Observaciones en la venta" prendido, el "Ver boleto" del panel admin SHALL mostrar la observación del boleto dentro de "Detalles de venta", con el texto completo, cuando el boleto la tiene. Con el parámetro apagado, MUST NOT mostrarla. Cuando no la tiene, MUST NOT mostrar un recuadro vacío. La sección "Vista previa" MUST NOT incluir la observación, porque muestra el PDF del pasajero.

#### Scenario: Boleto con observación
- **GIVEN** un boleto con la observación "Baja en el peaje de Metán"
- **WHEN** un usuario con permiso para ver boletos abre su detalle
- **THEN** ve "Baja en el peaje de Metán" en "Detalles de venta"

#### Scenario: Boleto sin observación
- **WHEN** un usuario abre el detalle de un boleto sin observación
- **THEN** "Detalles de venta" no muestra la observación ni un recuadro vacío

#### Scenario: Vista previa
- **GIVEN** un boleto con observación
- **WHEN** el usuario mira la sección "Vista previa" del detalle
- **THEN** el PDF mostrado no incluye la observación

#### Scenario: Parámetro apagado
- **GIVEN** el parámetro apagado y un boleto con observación
- **WHEN** un usuario abre su detalle
- **THEN** "Detalles de venta" no muestra la observación
