# Spec Delta

## ADDED Requirements

### Requirement: Ayuda de la venta sin fecha según el panel de clientes
La ayuda de los interruptores "Viaje de ida sin fecha" y "Viaje de vuelta sin fecha" SHALL indicar que la fecha y el horario se asignan después con "reprogramar boleto". Solo con el panel de clientes activado MUST agregar que el pasajero también puede elegirlos desde el panel de clientes.

#### Scenario: Ayuda con el panel activado
- **WHEN** el panel de clientes está activado y el vendedor ve el interruptor "Viaje de ida sin fecha"
- **THEN** la ayuda indica que la fecha y el horario se asignan después con "reprogramar boleto" o que el pasajero los elige desde el panel de clientes

#### Scenario: Ayuda con el panel desactivado
- **WHEN** el panel de clientes está desactivado y el vendedor ve el interruptor "Viaje de vuelta sin fecha"
- **THEN** la ayuda indica que la fecha y el horario se asignan después con "reprogramar boleto" y no menciona el panel de clientes

### Requirement: Rechazo por padrón según el panel de clientes
El rechazo de una venta sin fecha por DNIs fuera del padrón SHALL mencionar que el pasajero no podría ver su boleto desde el panel de clientes solo cuando el panel está activado. La exigencia del padrón MUST aplicarse siempre, con el panel activado o no.

#### Scenario: Rechazo con el panel desactivado
- **WHEN** el panel de clientes está desactivado y se emite una venta sin fecha con un DNI fuera del padrón
- **THEN** la venta se rechaza indicando los DNIs sin registrar, sin mencionar el panel de clientes

#### Scenario: Rechazo con el panel activado
- **WHEN** el panel de clientes está activado y se emite una venta sin fecha con un DNI fuera del padrón
- **THEN** la venta se rechaza indicando los DNIs sin registrar y que, sin registro, el pasajero no podría ver su boleto desde el panel de clientes
