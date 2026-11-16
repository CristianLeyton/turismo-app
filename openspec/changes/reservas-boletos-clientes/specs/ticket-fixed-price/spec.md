## ADDED Requirements

### Requirement: Precio de los boletos de una reserva confirmada
El boleto que nace de confirmar una reserva de cliente SHALL llevar el precio guardado en la reserva para ese pasajero, aunque el precio fijo haya cambiado o se haya apagado desde que se reservó. Como en la venta, el monto completo de un pasajero de ida y vuelta va en su boleto de ida y la vuelta vale 0. La emisión desde el mostrador MUST seguir calculando el precio con los parámetros vigentes.

#### Scenario: Precio subió después de reservar
- **GIVEN** una reserva de $5.000 por pasajero y el precio fijo ahora en $6.000
- **WHEN** el personal la confirma
- **THEN** los boletos se emiten a $5.000

#### Scenario: Precio fijo apagado al confirmar
- **GIVEN** una reserva de $5.000 y el precio fijo apagado
- **WHEN** el personal la confirma
- **THEN** los boletos se emiten a $5.000
