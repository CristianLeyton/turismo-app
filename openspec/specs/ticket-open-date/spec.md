# ticket-open-date Specification

## Purpose

Define cómo el personal deja un boleto con fecha abierta desde la reprogramación: quién puede hacerlo, sobre qué tramos, qué condiciones exige, qué le pasa al boleto y a su asiento, y cómo se confirma y se entrega.

## Requirements

### Requirement: Opción "Dejar con fecha abierta" en la reprogramación
La pantalla "Reprogramar boleto" SHALL ofrecer, además de elegir una fecha nueva, la opción "Dejar con fecha abierta". Al elegirla MUST ocultar la fecha, el horario y el asiento, y mostrar un campo de motivo opcional. La opción MUST NOT mostrarse en un boleto que ya está sin fecha.

#### Scenario: Elegir fecha abierta
- **GIVEN** un boleto con fecha y un usuario con los permisos necesarios
- **WHEN** el usuario elige "Dejar con fecha abierta" en "Reprogramar boleto"
- **THEN** dejan de pedirse fecha, horario y asiento, y aparece el campo "Motivo" opcional

#### Scenario: Boleto ya sin fecha
- **GIVEN** un boleto sin fecha
- **WHEN** el usuario abre "Reprogramar boleto"
- **THEN** no ve la opción "Dejar con fecha abierta"

### Requirement: Permisos para dejar con fecha abierta
Dejar un boleto con fecha abierta SHALL requerir los permisos "Reprogramar" y "Vender sin fecha/horario" de Boletos a la vez. Sin alguno de los dos, la opción MUST NOT mostrarse, y si se fuerza la operación MUST rechazarse sin modificar el boleto. No se agrega ningún permiso nuevo al catálogo.

#### Scenario: Con los dos permisos
- **WHEN** un usuario con "Reprogramar" y "Vender sin fecha/horario" abre "Reprogramar boleto" de un boleto con fecha
- **THEN** ve la opción "Dejar con fecha abierta"

#### Scenario: Solo con "Reprogramar"
- **WHEN** un usuario con "Reprogramar" y sin "Vender sin fecha/horario" abre "Reprogramar boleto"
- **THEN** no ve la opción "Dejar con fecha abierta" y puede reprogramar a una fecha como hasta ahora

#### Scenario: Operación forzada sin permiso
- **WHEN** un usuario sin "Vender sin fecha/horario" fuerza la operación de dejar con fecha abierta
- **THEN** se rechaza indicando que no tiene permiso y el boleto conserva su viaje y su asiento

### Requirement: Alcance por tramo
Dejar con fecha abierta SHALL usar el mismo alcance que la reprogramación: en el boleto de ida de un ida y vuelta se puede abrir solo la ida o ambos tramos; en cualquier otro boleto, solo ese boleto. Un tramo que ya está sin fecha MUST quedar como está. Abrir solo un tramo MUST NOT cambiar el otro.

#### Scenario: Ambos tramos
- **GIVEN** el boleto de ida de un ida y vuelta con los dos tramos fechados
- **WHEN** el usuario lo deja con fecha abierta con alcance "ambos"
- **THEN** la ida y la vuelta quedan sin viaje ni asiento

#### Scenario: Solo la ida
- **GIVEN** el boleto de ida de un ida y vuelta con los dos tramos fechados
- **WHEN** el usuario deja solo la ida con fecha abierta
- **THEN** la ida queda sin viaje ni asiento y la vuelta conserva su viaje y su asiento

#### Scenario: Solo la vuelta
- **WHEN** el usuario deja con fecha abierta el boleto de vuelta de un ida y vuelta
- **THEN** la vuelta queda sin viaje ni asiento, la ida conserva los suyos y deja de figurar ligada a un viaje de vuelta

#### Scenario: Ambos con la vuelta ya sin fecha
- **GIVEN** un ida y vuelta con la ida fechada y la vuelta sin fecha
- **WHEN** el usuario lo deja con fecha abierta con alcance "ambos"
- **THEN** la ida queda sin fecha y la vuelta sigue sin fecha, con un solo registro, el de la ida

### Requirement: Qué conserva y qué libera el boleto
Al dejar un tramo con fecha abierta, el boleto SHALL quedar sin viaje ni asiento y MUST conservar su número, su venta, su pasajero, su precio, su origen, su destino y su colectivo. El asiento MUST quedar libre para venderse en el viaje original, y el boleto MUST dejar de figurar en la lista de pasajeros de ese viaje.

#### Scenario: Asiento liberado
- **GIVEN** el boleto N°500 en el asiento 12 del viaje del 20/11 a las 08:00
- **WHEN** el usuario lo deja con fecha abierta
- **THEN** el asiento 12 de ese viaje queda disponible para una venta nueva

#### Scenario: Datos conservados
- **WHEN** el usuario deja con fecha abierta el boleto N°500
- **THEN** sigue siendo el N°500, con el mismo pasajero, venta, precio, tramo y colectivo
- **AND** la venta y los saldos no cambian

#### Scenario: Fuera de la lista de pasajeros
- **WHEN** el usuario deja con fecha abierta un boleto del viaje del 20/11
- **THEN** el pasajero ya no figura en la lista de pasajeros de ese viaje

### Requirement: Viajes que ya salieron
Dejar con fecha abierta SHALL permitirse aunque el viaje del boleto ya haya salido o sea de una fecha pasada, igual que la reprogramación.

#### Scenario: Pasajero que perdió el colectivo
- **GIVEN** un boleto de un viaje de ayer
- **WHEN** el usuario lo deja con fecha abierta
- **THEN** la operación se realiza y el boleto queda sin fecha

### Requirement: Pasajero registrado en el padrón
Dejar un boleto con fecha abierta MUST exigir que el DNI del pasajero esté registrado en el padrón de clientes. Si no lo está, la operación MUST rechazarse sin modificar el boleto, con un mensaje que indique que el pasajero no está en el padrón y que lo agregue desde Clientes antes de volver a intentarlo.

#### Scenario: Pasajero fuera del padrón
- **GIVEN** un boleto cuyo pasajero tiene un DNI que no está en el padrón de clientes
- **WHEN** el usuario intenta dejarlo con fecha abierta
- **THEN** se rechaza con un mensaje que pide agregar al pasajero al padrón desde Clientes
- **AND** el boleto conserva su viaje y su asiento

#### Scenario: Pasajero en el padrón
- **GIVEN** un boleto cuyo pasajero está en el padrón de clientes
- **WHEN** el usuario lo deja con fecha abierta
- **THEN** la operación se realiza

### Requirement: Confirmación y contraseña
Dejar con fecha abierta SHALL pedir la misma confirmación que la reprogramación, incluida la contraseña del usuario cuando la configuración la exige para reprogramar. Si la contraseña es incorrecta, MUST rechazarse sin modificar el boleto.

#### Scenario: Contraseña exigida
- **GIVEN** la configuración exige la contraseña para reprogramar
- **WHEN** el usuario deja un boleto con fecha abierta con una contraseña incorrecta
- **THEN** se rechaza y el boleto no cambia

### Requirement: Motivo opcional
El motivo SHALL ser opcional y de texto libre. Si se completa, MUST guardarse junto al registro del cambio. Si se deja vacío, la operación MUST realizarse igual, sin motivo.

#### Scenario: Con motivo
- **WHEN** el usuario deja un boleto con fecha abierta con el motivo "No sabe cuándo vuelve"
- **THEN** el registro del cambio guarda ese motivo

#### Scenario: Sin motivo
- **WHEN** el usuario deja un boleto con fecha abierta sin completar el motivo
- **THEN** la operación se realiza y el registro queda sin motivo

### Requirement: Entrega al dejar con fecha abierta
Al dejar un boleto con fecha abierta correctamente, el sistema SHALL entregar el boleto igual que al reprogramar: con la descarga automática activada, el PDF; con la descarga desactivada, el detalle del boleto con el modal de entrega. El boleto entregado MUST mostrarse sin fecha, como un boleto vendido sin fecha.

#### Scenario: Descarga automática activada
- **GIVEN** la descarga automática del PDF activada
- **WHEN** el usuario deja un boleto con fecha abierta
- **THEN** se entrega el PDF del boleto, que lo muestra sin fecha

#### Scenario: Descarga automática desactivada
- **GIVEN** la descarga automática del PDF desactivada
- **WHEN** el usuario deja un boleto con fecha abierta
- **THEN** llega al detalle del boleto con el modal de entrega abierto

### Requirement: Reasignar la fecha después
Un boleto que quedó con fecha abierta SHALL comportarse como cualquier boleto sin fecha: el personal puede asignarle una fecha nueva con "Reprogramar boleto", con las mismas validaciones de una reprogramación, incluido el orden entre ida y vuelta.

#### Scenario: Personal asigna la fecha nueva
- **GIVEN** un boleto que quedó con fecha abierta
- **WHEN** el personal lo reprograma al viaje del 25/11 con el asiento 4
- **THEN** el boleto queda en ese viaje y asiento, con el mismo número

#### Scenario: Ida abierta con la vuelta fechada
- **GIVEN** un ida y vuelta con la ida abierta y la vuelta fechada para el 25/11
- **WHEN** se intenta asignar a la ida una fecha posterior a la vuelta
- **THEN** se rechaza indicando que la vuelta debe ser posterior a la ida
