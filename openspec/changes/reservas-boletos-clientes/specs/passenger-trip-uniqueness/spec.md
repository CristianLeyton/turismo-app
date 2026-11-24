## ADDED Requirements

### Requirement: Reservas de clientes sin duplicados
Al crear una reserva, el sistema MUST rechazarla si el DNI de algún pasajero o menor ya tiene un boleto activo en alguno de sus viajes, o está en otra reserva pendiente sin vencer de esos viajes. El cliente MUST ver qué DNI ya tiene lugar en el viaje, sin datos de otros boletos ni enlaces al panel de administración. Al confirmar, la regla MUST volver a controlarse contra los boletos activos.

#### Scenario: DNI con boleto en el viaje
- **GIVEN** un DNI con boleto activo en el viaje del 20/11 a las 08:00
- **WHEN** un cliente reserva ese viaje con ese DNI
- **THEN** se rechaza indicando que ese DNI ya tiene lugar en el viaje

#### Scenario: DNI en otra reserva pendiente
- **GIVEN** un DNI en una reserva pendiente sin vencer del mismo viaje
- **WHEN** otro cliente reserva ese viaje con ese DNI
- **THEN** se rechaza

#### Scenario: Reserva pendiente no bloquea el mostrador
- **GIVEN** un DNI en una reserva pendiente
- **WHEN** un vendedor le vende un boleto del mismo viaje en el mostrador
- **THEN** la venta se permite, y la reserva no podrá confirmarse con ese pasajero
