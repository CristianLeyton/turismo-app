## ADDED Requirements

### Requirement: Modo de precio registrado en la venta
Cada boleto de ida emitido SHALL registrar cómo se calculó su precio: "fijo" si se vendió con el precio fijo prendido, o "manual" si se cargó a mano. Con el modo "fijo" MUST registrar también el seguro para menores por tramo del período vigente al emitir, aunque el pasajero no viaje con un menor. El boleto de vuelta MUST NOT registrar nada propio: su modo y su seguro son siempre los de su boleto de ida.

#### Scenario: Venta con precio fijo
- **GIVEN** el precio fijo prendido con un período vigente de boleto 5000 y seguro 800
- **WHEN** se vende solo ida a un adulto sin menor
- **THEN** el boleto queda registrado como "fijo" con seguro 800

#### Scenario: Venta con precio a mano
- **GIVEN** el precio fijo apagado
- **WHEN** se vende solo ida a un adulto con precio 4500
- **THEN** el boleto queda registrado como "manual" y sin seguro

#### Scenario: Ida y vuelta
- **GIVEN** el precio fijo prendido con seguro 800
- **WHEN** se vende ida y vuelta a un adulto
- **THEN** la ida queda registrada como "fijo" con seguro 800 y la vuelta no registra modo ni seguro

#### Scenario: Ventas con tramos sin fecha
- **GIVEN** el precio fijo prendido con seguro 800
- **WHEN** se vende ida y vuelta con algún tramo sin fecha, o ambos
- **THEN** el boleto de ida queda registrado como "fijo" con seguro 800, igual que con las dos fechas

#### Scenario: El seguro registrado no cambia con la grilla
- **GIVEN** un boleto vendido como "fijo" con seguro 800
- **WHEN** un administrador cambia el seguro de ese período a 1000 o borra el período
- **THEN** el boleto sigue registrado con seguro 800

### Requirement: Boletos vendidos antes del registro del modo
Los boletos emitidos antes de este cambio SHALL quedar sin modo de precio registrado. La actualización del sistema MUST NOT modificar ningún dato de los boletos ni de las ventas existentes, incluido su precio.

#### Scenario: Actualización del sistema
- **GIVEN** boletos vendidos antes de la actualización, con su precio
- **WHEN** se actualiza el sistema
- **THEN** esos boletos conservan todos sus datos y su precio, y quedan sin modo de precio

## MODIFIED Requirements

### Requirement: Precio fijo apagado por parámetro
Con el parámetro "Precio fijo activo" apagado, que es el valor sin configurar, la venta y la edición de boletos SHALL funcionar como hasta ahora, con el precio cargado a mano por pasajero. Con el parámetro prendido, nadie MUST poder cargar ni cambiar a mano el precio de un boleto, cualquiera sea su permiso, salvo al editar un boleto cuya ida está registrada como "manual".

#### Scenario: Parámetro apagado
- **GIVEN** el precio fijo apagado
- **WHEN** un vendedor arma una venta
- **THEN** carga el precio de cada pasajero a mano, como hasta ahora

#### Scenario: Parámetro prendido
- **GIVEN** el precio fijo prendido
- **WHEN** un usuario con todos los permisos arma una venta
- **THEN** el campo "Precio" de cada pasajero muestra el monto calculado y no se puede editar

### Requirement: Precio no editable al editar un boleto
Con el precio fijo prendido, la edición de un boleto SHALL mostrar el campo "Precio" sin poder editarlo, y MUST ignorar un precio enviado en el pedido. La excepción son los boletos cuyo boleto de ida está registrado como "manual": en ellos el campo "Precio" MUST poder editarse a mano, como con el precio fijo apagado.

#### Scenario: Campo bloqueado
- **GIVEN** el precio fijo prendido
- **WHEN** un usuario con permiso de edición abre la edición de un boleto de ida registrado como "fijo" o sin modo de precio
- **THEN** ve el precio, pero no lo puede cambiar

#### Scenario: Pedido manipulado
- **GIVEN** el precio fijo prendido y un boleto registrado como "fijo" de 5000
- **WHEN** se guarda la edición del boleto enviando 1 como precio
- **THEN** el boleto sigue valiendo 5000

#### Scenario: Boleto vendido a mano
- **GIVEN** el precio fijo prendido y un boleto de ida registrado como "manual" de 4500
- **WHEN** un usuario con permiso de edición cambia el precio a 5000 y guarda
- **THEN** el boleto pasa a valer 5000 y el cambio queda en el "Historial de ediciones"

### Requirement: Ajuste del seguro al editar el menor
Con el precio fijo prendido, agregar el menor en un tramo SHALL sumar un seguro al boleto de ida de ese pasajero en esa venta, y quitarlo MUST restarlo. Si la ida está registrada como "fijo", el monto MUST ser el seguro registrado en ella. Si la ida está registrada como "manual", el precio MUST NOT cambiar solo. Si la ida no tiene modo de precio, el monto MUST ser el del período vigente en la fecha de la venta del boleto, y no el vigente el día de la edición; si la venta es anterior al primer período, se usa el período más antiguo. Vale igual si se edita la ida o la vuelta: la vuelta sigue en 0. Si el seguro es 0, el precio no cambia.

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
