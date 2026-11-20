## ADDED Requirements

### Requirement: Bloqueo de asientos por reservas de clientes
Una reserva de cliente pendiente SHALL bloquear cada uno de sus asientos hasta su hora límite, con la misma regla de unicidad por asiento y viaje que el bloqueo de 5 minutos. Ese bloqueo MUST contar como "reservado por otro" para toda sesión de vendedor o cliente, MUST NOT renovarse por actividad de ninguna sesión ni liberarse al cancelar, terminar o cerrar una venta, y MUST liberarse al confirmar, rechazar o cancelar la reserva.

#### Scenario: Vendedor intenta reservar el asiento
- **GIVEN** el asiento 5 bloqueado por una reserva de cliente sin vencer
- **WHEN** un vendedor intenta reservarlo en la venta
- **THEN** se rechaza informando que el asiento 5 está reservado

#### Scenario: Cancelación de una venta en el mostrador
- **GIVEN** un asiento bloqueado por una reserva de cliente
- **WHEN** un vendedor cancela su venta en curso del mismo viaje
- **THEN** el bloqueo de la reserva se conserva

#### Scenario: Reserva vencida
- **GIVEN** la hora límite de la reserva pasó
- **WHEN** un vendedor reserva ese asiento
- **THEN** lo consigue, porque el bloqueo vencido no cuenta

### Requirement: La limpieza no toca reservas de clientes vigentes
El comando `app:cleanup-expired-reservations` SHALL eliminar también los bloqueos vencidos de reservas de clientes y MUST NOT eliminar los que siguen vigentes ni cambiar el estado de ninguna reserva.

#### Scenario: Limpieza
- **GIVEN** un bloqueo de reserva de cliente vigente y otro vencido
- **WHEN** se ejecuta la limpieza
- **THEN** se elimina solo el vencido y las dos reservas conservan su estado
