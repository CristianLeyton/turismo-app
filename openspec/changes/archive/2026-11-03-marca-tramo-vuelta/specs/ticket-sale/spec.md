# Spec Delta

## MODIFIED Requirements

### Requirement: Identificación del tramo de vuelta
Un boleto se considera tramo de vuelta solo si está marcado explícitamente como tal. La emisión MUST marcar cada boleto como ida o como vuelta, en la venta con fecha, sin fecha y mixta. Si quien crea un boleto no indica la marca, el sistema la completa con la convención histórica (ida y vuelta, sin viaje de vuelta y precio 0). Una marca explícita de ida MUST NOT cambiarse por esa convención, y el precio no interviene al identificar la vuelta de un boleto existente.

#### Scenario: Boleto histórico sin marca
- **WHEN** un boleto anterior a la marca explícita es de ida y vuelta, sin viaje de vuelta y con precio 0, vigente o dado de baja
- **THEN** después de la migración de datos queda marcado como tramo de vuelta y el sistema lo trata como tal

#### Scenario: Creación con precio 0 sin viaje de vuelta
- **WHEN** se crea un boleto de ida y vuelta, sin viaje de vuelta y con precio 0, sin indicar si es tramo de vuelta
- **THEN** queda marcado como tramo de vuelta
- **AND** si se indica explícitamente que no es tramo de vuelta, queda como ida

#### Scenario: Ida a precio 0 en venta mixta
- **WHEN** se emite una venta de ida y vuelta con la ida con fecha, la vuelta sin fecha y precio 0
- **THEN** el boleto de ida queda como ida, con viaje y asiento, y solo el boleto pendiente queda como tramo de vuelta

#### Scenario: Ambos tramos sin fecha a precio 0
- **WHEN** se emite una venta de ida y vuelta con ambos tramos sin fecha y precio 0
- **THEN** cada pasajero tiene un boleto de ida y uno de vuelta, y solo el de vuelta queda marcado como tramo de vuelta

#### Scenario: Venta con fecha a precio 0
- **WHEN** se emite una venta de ida y vuelta con ambos tramos con fecha y precio 0
- **THEN** el boleto de ida queda como ida y el boleto de vuelta queda marcado como tramo de vuelta

#### Scenario: La ida a precio 0 se trata como ida después de la venta
- **WHEN** se consulta, reprograma o edita la ida a precio 0 de una venta de ida y vuelta
- **THEN** se muestra y se reprograma como ida, se identifica su vuelta por la marca, y su precio se puede editar como el de cualquier ida

#### Scenario: Corrección de ventas ya afectadas
- **WHEN** antes del cambio quedó una venta donde un pasajero tiene dos boletos de ida y vuelta marcados como tramo de vuelta y ninguno como ida
- **THEN** la migración de datos deja como ida el boleto creado primero y como vuelta el otro
