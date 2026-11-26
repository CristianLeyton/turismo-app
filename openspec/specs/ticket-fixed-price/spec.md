# ticket-fixed-price Specification

## Purpose

Define cómo se calcula el precio de los boletos a partir de los parámetros de precio fijo, en la venta y al editar un boleto, para que el vendedor no cargue el precio a mano.

## Requirements

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
Si al emitir el precio calculado de algún pasajero no coincide con el que mostraba la venta, o el precio fijo se prendió o se apagó desde que se armó la venta, la emisión SHALL rechazarse sin crear ningún boleto y MUST avisar "Cambió la configuración de precios. Revisá los precios de la venta y volvé a emitir." Esto incluye una venta que se armó un día y se emite otro con distinto período vigente. Al revisar, la venta MUST mostrar los precios recalculados.

#### Scenario: Cambió el precio del boleto
- **GIVEN** una venta armada con precio del boleto 5000
- **WHEN** un administrador cambia el precio del período vigente a 6000 y el vendedor emite
- **THEN** no se crea ningún boleto, aparece el aviso y el campo "Precio" pasa a mostrar 6000

#### Scenario: Se prendió el precio fijo
- **GIVEN** una venta armada con el precio fijo apagado y un precio cargado a mano
- **WHEN** un administrador prende el precio fijo y el vendedor emite
- **THEN** no se crea ningún boleto y aparece el aviso

#### Scenario: Se apagó el precio fijo
- **GIVEN** una venta armada con el precio fijo prendido
- **WHEN** un administrador lo apaga y el vendedor emite
- **THEN** no se crea ningún boleto, aparece el aviso y el campo "Precio" vuelve a poder editarse

#### Scenario: La venta cruzó a otro período
- **GIVEN** un período desde el 20/11/2026 con boleto 70000 y otro desde el 02/12/2026 con boleto 160000
- **WHEN** una venta se arma el 01/12/2026 a las 23:55 y se emite el 02/12/2026 a las 00:05
- **THEN** no se crea ningún boleto, aparece el aviso y el campo "Precio" pasa a mostrar 160000

#### Scenario: La venta cruzó el día sin cambiar de período
- **GIVEN** un único período desde el 20/11/2026 con boleto 70000
- **WHEN** una venta se arma el 25/11/2026 a las 23:55 y se emite el 26/11/2026 a las 00:05
- **THEN** la venta se emite con el boleto a 70000

### Requirement: Los boletos vendidos no se recalculan
Cambiar los montos del precio fijo, o prenderlo o apagarlo, MUST NOT cambiar el precio de los boletos ya emitidos.

#### Scenario: Cambio de precio posterior
- **GIVEN** un boleto vendido a 5000 con el precio fijo prendido
- **WHEN** un administrador cambia el precio del boleto a 6000
- **THEN** el boleto sigue valiendo 5000

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

### Requirement: Precio vigente según la fecha de venta
Con el precio fijo prendido, los montos del boleto y del seguro de una venta SHALL salir del período de precio vigente el día de la emisión: el período más reciente cuyo "Desde" es ese día o anterior. Después del último período MUST seguir rigiendo su monto. La fecha del viaje, que un tramo no tenga fecha y la reprogramación MUST NOT influir en el precio.

#### Scenario: Venta dentro de un período
- **GIVEN** un período desde el 20/11/2026 con boleto 70000 y seguro 5000, y otro desde el 02/12/2026 con boleto 160000 y seguro 8000
- **WHEN** el 25/11/2026 se vende solo ida a un adulto sin menor
- **THEN** el boleto vale 70000

#### Scenario: Primer día del período siguiente
- **GIVEN** los mismos períodos
- **WHEN** el 02/12/2026 se vende solo ida a un adulto con menor
- **THEN** el boleto vale 168000

#### Scenario: Después del último período
- **GIVEN** los mismos períodos
- **WHEN** el 15/03/2027 se vende solo ida a un adulto sin menor
- **THEN** el boleto vale 160000

#### Scenario: Viaje en otro período
- **GIVEN** los mismos períodos
- **WHEN** el 25/11/2026 se vende solo ida a un adulto sin menor para un viaje del 20/12/2026
- **THEN** el boleto vale 70000

#### Scenario: Ida y vuelta con tramo sin fecha
- **GIVEN** los mismos períodos
- **WHEN** el 25/11/2026 se vende ida con fecha y vuelta sin fecha a un adulto sin menor
- **THEN** el boleto de ida vale 140000 y el de vuelta vale 0

#### Scenario: Reprogramación a otro período
- **GIVEN** un boleto vendido el 25/11/2026 a 70000 para el 28/11/2026
- **WHEN** se lo reprograma al 20/12/2026
- **THEN** el boleto sigue valiendo 70000

### Requirement: Venta sin período vigente
Con el precio fijo prendido, si al armar o emitir una venta no hay ningún período con "Desde" igual al día de hoy o anterior, la emisión SHALL rechazarse sin crear ningún boleto y MUST avisar "No hay un precio vigente para hoy. Pedile a un administrador que cargue los precios." La configuración normal no permite llegar a este estado. Este requisito cubre datos dañados o cargados por fuera de la página.

#### Scenario: Sin períodos vigentes
- **GIVEN** el precio fijo prendido y un único período que empieza el 01/12/2026, guardado por fuera de la página
- **WHEN** el 25/11/2026 un vendedor intenta emitir una venta
- **THEN** no se crea ningún boleto y aparece el aviso

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
