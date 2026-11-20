# ticket-fixed-price Specification

## Purpose

Define cómo se calcula el precio de los boletos a partir de los parámetros de precio fijo, en la venta y al editar un boleto, para que el vendedor no cargue el precio a mano.

## Requirements

### Requirement: Precio fijo apagado por parámetro
Con el parámetro "Precio fijo activo" apagado, que es el valor sin configurar, la venta y la edición de boletos SHALL funcionar como hasta ahora, con el precio cargado a mano por pasajero. Con el parámetro prendido, nadie MUST poder cargar ni cambiar a mano el precio de un boleto, cualquiera sea su permiso.

#### Scenario: Parámetro apagado
- **GIVEN** el precio fijo apagado
- **WHEN** un vendedor arma una venta
- **THEN** carga el precio de cada pasajero a mano, como hasta ahora

#### Scenario: Parámetro prendido
- **GIVEN** el precio fijo prendido
- **WHEN** un usuario con todos los permisos arma una venta
- **THEN** el campo "Precio" de cada pasajero muestra el monto calculado y no se puede editar

### Requirement: Cálculo del precio en la venta
Con el precio fijo prendido, el precio de cada pasajero SHALL ser el precio del boleto más el seguro para menores si viaja con un menor, multiplicado por 2 si la venta es de ida y vuelta. Las mascotas MUST NOT sumar al precio. El campo "Precio" MUST actualizarse al marcar o desmarcar el menor y al elegir solo ida o ida y vuelta.

#### Scenario: Solo ida sin menor
- **GIVEN** precio del boleto 5000 y seguro 800
- **WHEN** se vende solo ida a un adulto sin menor
- **THEN** el boleto vale 5000

#### Scenario: Solo ida con menor
- **GIVEN** precio del boleto 5000 y seguro 800
- **WHEN** se vende solo ida a un adulto con menor
- **THEN** el boleto vale 5800

#### Scenario: Ida y vuelta con menor
- **GIVEN** precio del boleto 5000 y seguro 800
- **WHEN** se vende ida y vuelta a un adulto con menor
- **THEN** el boleto de ida vale 11600 y el de vuelta vale 0

#### Scenario: Con mascota
- **GIVEN** precio del boleto 5000 y seguro 800
- **WHEN** se vende solo ida a un adulto con mascota y sin menor
- **THEN** el boleto vale 5000

#### Scenario: Actualización al marcar el menor
- **GIVEN** precio del boleto 5000 y seguro 800 y una venta solo ida
- **WHEN** el vendedor marca "¿Viaja con un menor?" en un pasajero
- **THEN** el campo "Precio" de ese pasajero pasa de 5000 a 5800 antes de emitir

### Requirement: Precio fijo en ventas con tramos sin fecha
En una venta de ida y vuelta con algún tramo sin fecha, el precio SHALL calcularse igual que con las dos fechas: el monto completo queda en el boleto de ida del pasajero y el boleto de vuelta vale 0.

#### Scenario: Vuelta sin fecha
- **GIVEN** precio del boleto 5000 y seguro 800
- **WHEN** se vende ida con fecha y vuelta sin fecha a un adulto sin menor
- **THEN** el boleto de ida vale 10000 y el boleto pendiente de vuelta vale 0

#### Scenario: Ambos tramos sin fecha
- **GIVEN** precio del boleto 5000 y seguro 800
- **WHEN** se vende ida y vuelta sin fechas a un adulto con menor
- **THEN** el boleto de ida vale 11600 y el de vuelta vale 0

### Requirement: El precio se calcula al emitir
Al emitir con el precio fijo prendido, el sistema SHALL calcular el precio de cada boleto con los parámetros vigentes en ese momento y MUST ignorar el precio que envíe el formulario.

#### Scenario: Pedido manipulado
- **GIVEN** precio del boleto 5000, seguro 800 y el precio fijo prendido
- **WHEN** se emite una venta solo ida sin menor enviando 1 como precio del pasajero
- **THEN** el boleto se guarda con precio 5000

### Requirement: Rechazo si cambió el precio durante la venta
Si al emitir el precio calculado de algún pasajero no coincide con el que mostraba la venta, o el precio fijo se prendió o se apagó desde que se armó la venta, la emisión SHALL rechazarse sin crear ningún boleto y MUST avisar "Cambió la configuración de precios. Revisá los precios de la venta y volvé a emitir." Al revisar, la venta MUST mostrar los precios recalculados.

#### Scenario: Cambió el precio del boleto
- **GIVEN** una venta armada con precio del boleto 5000
- **WHEN** un administrador cambia el precio a 6000 y el vendedor emite
- **THEN** no se crea ningún boleto, aparece el aviso y el campo "Precio" pasa a mostrar 6000

#### Scenario: Se prendió el precio fijo
- **GIVEN** una venta armada con el precio fijo apagado y un precio cargado a mano
- **WHEN** un administrador prende el precio fijo y el vendedor emite
- **THEN** no se crea ningún boleto y aparece el aviso

#### Scenario: Se apagó el precio fijo
- **GIVEN** una venta armada con el precio fijo prendido
- **WHEN** un administrador lo apaga y el vendedor emite
- **THEN** no se crea ningún boleto, aparece el aviso y el campo "Precio" vuelve a poder editarse

### Requirement: Los boletos vendidos no se recalculan
Cambiar los montos del precio fijo, o prenderlo o apagarlo, MUST NOT cambiar el precio de los boletos ya emitidos.

#### Scenario: Cambio de precio posterior
- **GIVEN** un boleto vendido a 5000 con el precio fijo prendido
- **WHEN** un administrador cambia el precio del boleto a 6000
- **THEN** el boleto sigue valiendo 5000

### Requirement: Precio no editable al editar un boleto
Con el precio fijo prendido, la edición de un boleto SHALL mostrar el campo "Precio" sin poder editarlo, y MUST ignorar un precio enviado en el pedido.

#### Scenario: Campo bloqueado
- **GIVEN** el precio fijo prendido
- **WHEN** un usuario con permiso de edición abre la edición de un boleto de ida
- **THEN** ve el precio, pero no lo puede cambiar

#### Scenario: Pedido manipulado
- **GIVEN** el precio fijo prendido y un boleto de 5000
- **WHEN** se guarda la edición del boleto enviando 1 como precio
- **THEN** el boleto sigue valiendo 5000

### Requirement: Ajuste del seguro al editar el menor
Con el precio fijo prendido, agregar el menor en un tramo SHALL sumar un seguro, con el monto vigente, al boleto de ida de ese pasajero en esa venta, y quitarlo MUST restarlo. Vale igual si se edita la ida o la vuelta: la vuelta sigue en 0. Si el seguro es 0, el precio no cambia.

#### Scenario: Agregar el menor en la ida
- **GIVEN** seguro 800 y un boleto solo ida de 5000 sin menor
- **WHEN** se edita el boleto y se agrega el menor
- **THEN** el boleto pasa a valer 5800

#### Scenario: Agregar el menor en la vuelta
- **GIVEN** seguro 800 y una ida y vuelta sin menor, con la ida a 10000 y la vuelta a 0
- **WHEN** se edita el boleto de vuelta y se agrega el menor
- **THEN** la ida pasa a valer 10800 y la vuelta sigue en 0

#### Scenario: Quitar el menor en la ida
- **GIVEN** seguro 800 y una ida y vuelta con menor en los dos tramos, con la ida a 11600
- **WHEN** se edita la ida y se quita el menor
- **THEN** la ida pasa a valer 10800

#### Scenario: Seguro cambiado desde la venta
- **GIVEN** un boleto solo ida de 5800 vendido con menor y seguro 800, y el seguro ahora es 1000
- **WHEN** se edita el boleto y se quita el menor
- **THEN** el boleto pasa a valer 4800

#### Scenario: Precio fijo apagado
- **GIVEN** el precio fijo apagado
- **WHEN** se edita un boleto y se agrega el menor
- **THEN** el precio no cambia solo y se puede editar a mano, como hasta ahora

### Requirement: Vuelta sin boleto de ida activo
Con el precio fijo prendido, si se agrega o quita el menor en un boleto de vuelta cuyo boleto de ida no está vigente, la edición SHALL rechazarse sin guardar nada y MUST avisar que no se encontró el boleto de ida donde ajustar el seguro.

#### Scenario: Ida eliminada
- **GIVEN** el precio fijo prendido y una ida y vuelta cuyo boleto de ida fue eliminado
- **WHEN** se edita la vuelta y se agrega el menor
- **THEN** no se guarda ningún cambio y aparece el aviso

### Requirement: Ajuste del seguro en el historial
El ajuste del seguro SHALL quedar en el "Historial de ediciones" del boleto de ida como cambio de "Precio", con el valor anterior, el nuevo y el motivo de la edición. Si el menor se cambió desde la vuelta, el registro MUST indicar que se cambió el menor en la vuelta. El resumen de confirmación de la edición MUST mostrar el cambio de precio antes de guardar.

#### Scenario: Ajuste desde la vuelta
- **GIVEN** seguro 800 y una ida y vuelta con la ida a 10000
- **WHEN** se edita la vuelta, se agrega el menor con el motivo "Avisó por teléfono" y se confirma
- **THEN** el historial del boleto de ida registra "Precio" de 10000 a 10800, con el motivo "Avisó por teléfono" y la aclaración de que se cambió el menor en la vuelta

#### Scenario: Resumen antes de guardar
- **GIVEN** seguro 800 y un boleto solo ida de 5000
- **WHEN** se agrega el menor y se toca guardar
- **THEN** el resumen de confirmación muestra "Precio: $5.000 → $5.800"
