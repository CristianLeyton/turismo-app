# Spec Delta

## MODIFIED Requirements

### Requirement: Precio fijo apagado por parámetro
Con el parámetro "Precio fijo activo" apagado, que es el valor sin configurar, la venta y la edición de boletos SHALL funcionar como hasta ahora, con el precio cargado a mano por pasajero. Con el parámetro prendido, nadie MUST poder cargar ni cambiar a mano el precio de un boleto, cualquiera sea su permiso, salvo al editar un boleto cuya ida está registrada como "manual" o al editarlo con el permiso "Editar precio fijo".

#### Scenario: Parámetro apagado
- **GIVEN** el precio fijo apagado
- **WHEN** un vendedor arma una venta
- **THEN** carga el precio de cada pasajero a mano, como hasta ahora

#### Scenario: Parámetro prendido
- **GIVEN** el precio fijo prendido
- **WHEN** un usuario con todos los permisos arma una venta
- **THEN** el campo "Precio" de cada pasajero muestra el monto calculado y no se puede editar

### Requirement: Precio no editable al editar un boleto
Con el precio fijo prendido, la edición de un boleto SHALL mostrar el campo "Precio" sin poder editarlo, y MUST ignorar un precio enviado en el pedido. Hay dos excepciones, en las que el campo "Precio" MUST poder editarse a mano, como con el precio fijo apagado: los boletos cuyo boleto de ida está registrado como "manual", y cualquier boleto editado por un usuario con el permiso "Editar precio fijo". Para ese usuario, en un boleto que no es "manual", el campo MUST mostrar el aviso "Precio fijo. Si lo cambiás, el boleto pasa a precio manual." La vuelta de un ida y vuelta sigue sin precio propio.

#### Scenario: Campo bloqueado
- **GIVEN** el precio fijo prendido
- **WHEN** un usuario con permiso de edición y sin "Editar precio fijo" abre la edición de un boleto de ida registrado como "fijo" o sin modo de precio
- **THEN** ve el precio, pero no lo puede cambiar

#### Scenario: Pedido manipulado
- **GIVEN** el precio fijo prendido y un boleto registrado como "fijo" de 5000
- **WHEN** un usuario sin "Editar precio fijo" guarda la edición del boleto enviando 1 como precio
- **THEN** el boleto sigue valiendo 5000

#### Scenario: Boleto vendido a mano
- **GIVEN** el precio fijo prendido y un boleto de ida registrado como "manual" de 4500
- **WHEN** un usuario con permiso de edición cambia el precio a 5000 y guarda
- **THEN** el boleto pasa a valer 5000 y el cambio queda en el "Historial de ediciones"

#### Scenario: Con el permiso "Editar precio fijo"
- **GIVEN** el precio fijo prendido y un boleto solo ida registrado como "fijo" de 70000
- **WHEN** un usuario con permiso de edición y con "Editar precio fijo" abre la edición, cambia el precio a 65000 con el motivo "Descuento acordado" y guarda
- **THEN** el campo "Precio" se puede editar y muestra el aviso "Precio fijo. Si lo cambiás, el boleto pasa a precio manual."
- **AND** el boleto pasa a valer 65000 y el "Historial de ediciones" registra "Precio" de 70000 a 65000 con el motivo "Descuento acordado"

#### Scenario: Boleto sin modo de precio con el permiso
- **GIVEN** el precio fijo prendido y un boleto solo ida sin modo de precio de 70000
- **WHEN** un usuario con "Editar precio fijo" cambia el precio a 75000 y guarda
- **THEN** el boleto pasa a valer 75000

#### Scenario: Vuelta con el permiso
- **GIVEN** el precio fijo prendido y una ida y vuelta con la ida "fijo" a 140000 y la vuelta a 0
- **WHEN** un usuario con "Editar precio fijo" abre la edición de la vuelta
- **THEN** el campo "Precio" no se puede editar e indica que el precio viaja en el boleto de ida

#### Scenario: Ventas toma el precio editado
- **GIVEN** un boleto "fijo" de 70000 vendido el 10/11/2026 por Juan
- **WHEN** el 26/11/2026 un usuario con "Editar precio fijo" cambia el precio a 65000
- **THEN** las ventas de Juan del 10/11/2026 bajan 5000 y el 26/11/2026 no suma nada

### Requirement: Ajuste del seguro al editar el menor
Con el precio fijo prendido, agregar el menor en un tramo SHALL sumar un seguro al boleto de ida de ese pasajero en esa venta, y quitarlo MUST restarlo. Si la ida está registrada como "fijo", el monto MUST ser el seguro registrado en ella. Si la ida está registrada como "manual", el precio MUST NOT cambiar solo. Si la ida no tiene modo de precio, el monto MUST ser el del período vigente en la fecha de la venta del boleto, y no el vigente el día de la edición; si la venta es anterior al primer período, se usa el período más antiguo. Vale igual si se edita la ida o la vuelta: la vuelta sigue en 0. Si el seguro es 0, el precio no cambia. Si en la misma edición se cambia a mano el precio de la ida, el precio guardado MUST ser el cargado a mano, sin sumar ni restar el seguro.

#### Scenario: Agregar el menor en la ida
- **GIVEN** un boleto solo ida "fijo" de 5000 sin menor, con seguro registrado 800
- **WHEN** se edita el boleto y se agrega el menor
- **THEN** el boleto pasa a valer 5800

#### Scenario: Agregar el menor en la vuelta
- **GIVEN** una ida y vuelta sin menor, con la ida "fijo" a 10000 con seguro registrado 800 y la vuelta a 0
- **WHEN** se edita el boleto de vuelta y se agrega el menor
- **THEN** la ida pasa a valer 10800 y la vuelta sigue en 0

#### Scenario: Quitar el menor en la ida
- **GIVEN** una ida y vuelta con menor en los dos tramos, con la ida "fijo" a 11600 con seguro registrado 800
- **WHEN** se edita la ida y se quita el menor
- **THEN** la ida pasa a valer 10800

#### Scenario: Seguro de la grilla cambiado desde la venta
- **GIVEN** un boleto solo ida "fijo" vendido a 5800 con menor y seguro registrado 800, y el período de esa venta ahora tiene seguro 1000
- **WHEN** se edita el boleto y se quita el menor
- **THEN** el boleto pasa a valer 5000

#### Scenario: Período de la venta borrado
- **GIVEN** un boleto solo ida "fijo" vendido a 5000 sin menor con seguro registrado 800, y el período de esa venta fue borrado
- **WHEN** se edita el boleto y se agrega el menor
- **THEN** el boleto pasa a valer 5800

#### Scenario: Boleto vendido a mano
- **GIVEN** el precio fijo prendido y un boleto solo ida "manual" de 4500 sin menor
- **WHEN** se edita el boleto y se agrega el menor
- **THEN** el precio no cambia solo y se puede editar a mano

#### Scenario: Vuelta de un boleto vendido a mano
- **GIVEN** el precio fijo prendido y una ida y vuelta con la ida "manual" a 9000
- **WHEN** se edita la vuelta y se agrega el menor
- **THEN** la ida sigue valiendo 9000

#### Scenario: Seguro cambiado desde la venta
- **GIVEN** un período desde el 20/11/2026 con seguro 5000 y otro desde el 02/12/2026 con seguro 8000, y un boleto solo ida sin modo de precio vendido el 25/11/2026 a 75000 con menor
- **WHEN** el 05/12/2026 se edita el boleto y se quita el menor
- **THEN** el boleto pasa a valer 70000

#### Scenario: Seguro cambiado en el mismo período
- **GIVEN** un boleto solo ida sin modo de precio de 5800, vendido con menor cuando el período vigente tenía seguro 800, y ese mismo período ahora tiene seguro 1000
- **WHEN** se edita el boleto y se quita el menor
- **THEN** el boleto pasa a valer 4800

#### Scenario: Venta anterior al primer período
- **GIVEN** un boleto solo ida sin modo de precio de 5000, vendido el 10/10/2026, y el período más antiguo empieza el 20/11/2026 con seguro 5000
- **WHEN** con el precio fijo prendido se edita el boleto y se agrega el menor
- **THEN** el boleto pasa a valer 10000

#### Scenario: Precio fijo apagado
- **GIVEN** el precio fijo apagado
- **WHEN** se edita un boleto de cualquier modo y se agrega el menor
- **THEN** el precio no cambia solo y se puede editar a mano, como hasta ahora

#### Scenario: Precio y menor cambiados en la misma edición
- **GIVEN** el precio fijo prendido y un boleto solo ida "fijo" de 70000 sin menor, con seguro registrado 5000
- **WHEN** un usuario con "Editar precio fijo" agrega el menor, carga 72000 como precio y guarda
- **THEN** el boleto pasa a valer 72000, el "Historial de ediciones" registra un solo cambio de "Precio" de 70000 a 72000 y el resumen de confirmación muestra "Precio: $70.000 → $72.000" una sola vez

## ADDED Requirements

### Requirement: El precio editado a mano pasa a modo manual
Si al editar un boleto se guarda un precio distinto del que tenía, ese boleto de ida SHALL quedar registrado como "manual", con el precio fijo prendido o apagado y cualquiera sea el modo que tenía. Guardar el mismo precio, cambiar solo otros campos o cambiar el menor MUST NOT cambiar el modo. El seguro registrado en el boleto MUST NOT borrarse.

#### Scenario: Boleto "fijo" editado con el permiso
- **GIVEN** el precio fijo prendido y un boleto solo ida "fijo" de 70000 con seguro registrado 5000
- **WHEN** un usuario con "Editar precio fijo" cambia el precio a 65000 y guarda
- **THEN** el boleto queda registrado como "manual" con precio 65000

#### Scenario: Después cualquiera con permiso de edición lo puede editar
- **GIVEN** ese boleto, ya "manual"
- **WHEN** un usuario con permiso de edición y sin "Editar precio fijo" abre su edición
- **THEN** el campo "Precio" se puede editar

#### Scenario: Después agregar el menor no cambia el precio
- **GIVEN** ese boleto, ya "manual" a 65000
- **WHEN** se edita y se agrega el menor
- **THEN** el boleto sigue valiendo 65000

#### Scenario: Precio cambiado con el precio fijo apagado
- **GIVEN** el precio fijo apagado y un boleto de ida "fijo" de 140000
- **WHEN** un usuario con permiso de edición cambia el precio a 130000 y guarda
- **THEN** el boleto queda registrado como "manual" con precio 130000

#### Scenario: Boleto sin modo de precio
- **GIVEN** el precio fijo apagado y un boleto solo ida sin modo de precio de 50000
- **WHEN** se cambia el precio a 55000 y se guarda
- **THEN** el boleto queda registrado como "manual"

#### Scenario: Mismo precio
- **GIVEN** un boleto "fijo" de 70000 y un usuario con "Editar precio fijo"
- **WHEN** se edita, se deja 70000 como precio y se cambia el teléfono
- **THEN** el boleto sigue registrado como "fijo"

#### Scenario: Solo cambia el menor
- **GIVEN** el precio fijo prendido y un boleto solo ida "fijo" de 70000 sin menor, con seguro registrado 5000
- **WHEN** se edita y se agrega el menor sin tocar el precio
- **THEN** el boleto pasa a valer 75000 y sigue registrado como "fijo"
