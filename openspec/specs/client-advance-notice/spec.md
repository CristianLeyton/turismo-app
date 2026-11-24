# client-advance-notice Specification

## Purpose
Define la anticipación mínima con la que un cliente puede elegir, desde su panel, el horario de un boleto sin fecha, y cómo se le informa cuando un horario no la cumple.

## Requirements

### Requirement: Horarios con anticipación mínima en el panel de clientes
Al elegir fecha de un boleto sin fecha, el panel de clientes SHALL ofrecer solo los horarios cuya salida desde el origen del servicio queda al menos las horas configuradas en "Anticipación mínima para elegir fecha" después del momento actual. Sin configurar, la anticipación MUST ser de 24 horas. Con 0 horas, MUST NOT exigirse ninguna anticipación. La regla MUST aplicar a cada tramo por separado, ida o vuelta.

#### Scenario: Horario dentro de la anticipación
- **GIVEN** una anticipación de 24 horas y que ahora son las 10:00 del 10/11
- **WHEN** el cliente elige el 11/11 y la salida de un horario es a las 08:00
- **THEN** ese horario no se ofrece, porque sale con menos de 24 horas

#### Scenario: Horario con anticipación suficiente
- **GIVEN** una anticipación de 24 horas y que ahora son las 10:00 del 10/11
- **WHEN** el cliente elige el 11/11 y la salida de un horario es a las 10:00 o más tarde
- **THEN** ese horario se ofrece

#### Scenario: Salida desde el origen del servicio
- **GIVEN** un horario que sale del origen del servicio a las 06:00 y llega a la parada del pasajero a las 07:30
- **WHEN** se evalúa la anticipación
- **THEN** se usa la hora de las 06:00, la de salida del origen del servicio, y no la de la parada del pasajero

#### Scenario: Sin anticipación mínima
- **GIVEN** la anticipación configurada en 0
- **WHEN** el cliente elige el día de hoy
- **THEN** la anticipación no excluye ningún horario

#### Scenario: Valor por defecto
- **GIVEN** que nadie configuró la anticipación
- **WHEN** el cliente elige fecha
- **THEN** se exigen 24 horas

#### Scenario: Tramo de vuelta
- **WHEN** el cliente elige fecha del tramo de vuelta de un boleto de ida y vuelta
- **THEN** a ese tramo también se le exige la anticipación

### Requirement: Aviso cuando no hay horarios con anticipación
Cuando el día elegido no tiene ningún horario que cumpla la anticipación, el selector de horario SHALL indicar que no hay horarios disponibles con esas horas de anticipación y que elija otro día, en lugar de mostrarse vacío sin explicación.

#### Scenario: Ningún horario cumple
- **GIVEN** una anticipación de 24 horas
- **WHEN** el cliente elige un día en que todos los horarios salen con menos de 24 horas
- **THEN** ve que no hay horarios con 24hs de anticipación para ese día y puede elegir otra fecha

### Requirement: La anticipación se controla también en el servidor
Al confirmar "Elegir fecha", el sistema MUST rechazar un horario que no cumple la anticipación mínima, aunque el pedido lo incluya, con un mensaje que diga cuántas horas de anticipación se exigen y que se elija otro horario. El boleto MUST seguir sin fecha y el cliente MUST ver el motivo.

#### Scenario: Pedido con un horario fuera de plazo
- **GIVEN** una anticipación de 24 horas
- **WHEN** se confirma un horario que sale en menos de 24 horas, saltando el selector
- **THEN** no se asigna la fecha, el boleto sigue sin fecha y se avisa "Elegí un horario con al menos 24hs de anticipación."

#### Scenario: Cambio de la configuración
- **GIVEN** un cliente con el selector abierto y la anticipación en 24 horas
- **WHEN** la empresa sube la anticipación a 48 horas y el cliente confirma un horario de entre 24 y 48 horas
- **THEN** se rechaza con el aviso de 48hs

### Requirement: La anticipación solo afecta al panel de clientes
La anticipación mínima MUST NOT aplicarse a la venta, a la reprogramación ni a la creación de viajes desde el panel de administración, ni a los boletos que ya tienen fecha. La regla de los días de servicio MUST seguir aplicándose al cliente además de esta.

#### Scenario: Reprogramación desde administración
- **GIVEN** una anticipación de 24 horas
- **WHEN** un vendedor reprograma un boleto a un horario que sale en dos horas
- **THEN** la reprogramación se permite como hasta ahora

#### Scenario: Día sin servicio
- **WHEN** el cliente elige un día sin servicio
- **THEN** el día sigue deshabilitado o rechazado como hasta ahora, además de la anticipación
