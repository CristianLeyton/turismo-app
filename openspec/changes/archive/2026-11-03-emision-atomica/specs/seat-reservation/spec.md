# Spec Delta

## MODIFIED Requirements

### Requirement: Verificación final de disponibilidad al emitir
Al emitir, el sistema MUST verificar, con los viajes de la venta bloqueados hasta que la emisión se confirme, que cada asiento siga activo, sin boleto vigente y sin reserva vigente de otra sesión. Los boletos se crean bajo ese mismo bloqueo. Las reservas de la propia sesión no lo impiden.

#### Scenario: Vendido en el último momento
- **WHEN** entre la selección y la emisión otra venta tomó uno de los asientos
- **THEN** la emisión se rechaza informando el número de asiento y se liberan las reservas de la sesión

#### Scenario: Reserva propia
- **WHEN** los asientos están reservados por la misma sesión que emite
- **THEN** la emisión continúa

#### Scenario: Dos emisiones simultáneas del mismo asiento
- **WHEN** dos vendedores emiten al mismo tiempo ventas que incluyen el mismo asiento del mismo viaje
- **THEN** solo una emisión se confirma, y la otra se rechaza informando el número de asiento sin crear nada

## ADDED Requirements

### Requirement: Un solo boleto vigente por asiento y viaje
La base de datos MUST rechazar un segundo boleto vigente para el mismo viaje y asiento, venga de la venta, de la reprogramación, de la restauración de un boleto o de cualquier otro camino de escritura. Los boletos dados de baja no ocupan el asiento, y los boletos sin fecha (sin viaje ni asiento) no participan de la restricción.

#### Scenario: Escritura directa duplicada
- **WHEN** se intenta guardar un boleto vigente en un viaje y asiento que ya tienen otro boleto vigente
- **THEN** la base rechaza la escritura

#### Scenario: Asiento de un boleto dado de baja
- **WHEN** el único boleto de un asiento en un viaje está dado de baja y se emite un boleto nuevo para ese asiento
- **THEN** el boleto nuevo se crea

#### Scenario: Varios boletos sin fecha
- **WHEN** existen varios boletos vigentes sin viaje ni asiento
- **THEN** la restricción no los afecta

### Requirement: Migración protegida ante duplicados existentes
Al incorporar la restricción, el sistema MUST verificar primero que no existan boletos vigentes duplicados por viaje y asiento. Si existen, la migración se detiene sin modificar la base y lista cada viaje, asiento y boleto en conflicto.

#### Scenario: Base con duplicados
- **WHEN** se ejecuta la migración y hay dos boletos vigentes en el mismo viaje y asiento
- **THEN** la migración falla, informa el viaje, el asiento y los identificadores de ambos boletos, y la tabla queda como estaba

### Requirement: Emisión atómica
La emisión de una venta SHALL ejecutarse como una unidad: la venta, los pasajeros, los boletos de ida y los de vuelta se confirman juntos o no se crea ninguno. Si cualquier parte falla, el vendedor ve el motivo y no queda ningún dato de esa venta.

#### Scenario: Falla la vuelta después de la ida
- **WHEN** los boletos de ida se pudieron crear pero la creación de los de vuelta falla por un asiento ocupado
- **THEN** no queda la venta, ni los pasajeros, ni los boletos de ida, y el vendedor ve el error de la vuelta

#### Scenario: Venta mixta con tramo sin fecha
- **WHEN** en una venta con la ida fechada y la vuelta sin fecha falla la creación de los boletos de ida
- **THEN** no queda ningún boleto, tampoco el de la vuelta pendiente

### Requirement: Restauración de un boleto con el asiento ocupado
Al restaurar un boleto dado de baja cuyo asiento ya tiene otro boleto vigente en el mismo viaje, el sistema MUST rechazar la restauración con un mensaje que indique el asiento ocupado, sin error interno y sin modificar el boleto.

#### Scenario: Asiento revendido
- **WHEN** se restaura un boleto dado de baja del asiento 5 y ese asiento ya se vendió a otro pasajero en el mismo viaje
- **THEN** la restauración se rechaza informando que el asiento 5 está ocupado, y el boleto sigue dado de baja

## REMOVED Requirements

### Requirement: Unicidad de boletos vigentes solo por control de la aplicación
**Reason**: La base de datos pasa a garantizar un solo boleto vigente por asiento y viaje (hallazgo H9).
**Migration**: Lo reemplaza el requisito "Un solo boleto vigente por asiento y viaje". Antes de migrar en producción hay que corregir los duplicados que informe la migración.

### Requirement: Emisión no atómica ante fallos tardíos
**Reason**: La emisión pasa a ser todo o nada (hallazgo H4).
**Migration**: Lo reemplaza el requisito "Emisión atómica". Ya no quedan ventas parciales que haya que corregir a mano.
