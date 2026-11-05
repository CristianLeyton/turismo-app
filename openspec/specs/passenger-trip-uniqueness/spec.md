# passenger-trip-uniqueness Specification

## Purpose
Garantizar que una persona, identificada por su DNI, tenga a lo sumo un boleto activo por viaje, ya sea como pasajero o como menor acompañante, en toda operación que asigne un viaje o un DNI a un boleto.

## Requirements

### Requirement: Una persona por viaje
Una persona MUST NOT tener más de un boleto activo en el mismo viaje, entendido como mismo colectivo, horario y fecha. Está en un viaje si su DNI es el del pasajero de un boleto activo de ese viaje o el de un menor acompañante de ese boleto. La comparación cruza ambos roles. El tramo vendido no importa. Ningún permiso exceptúa esta regla.

#### Scenario: Mismo adulto en el mismo viaje
- **WHEN** el DNI 30123456 ya tiene un boleto activo en el viaje y se intenta emitir otro boleto para ese DNI en ese viaje
- **THEN** la operación se rechaza

#### Scenario: Adulto que ya viaja como menor
- **WHEN** el DNI 45123456 viaja como menor acompañante de un boleto del viaje y se intenta emitirle un boleto propio en ese viaje
- **THEN** la operación se rechaza

#### Scenario: Menor que ya viaja como adulto
- **WHEN** el DNI 45123456 tiene un boleto propio en el viaje y se intenta cargarlo como menor acompañante de otro boleto de ese viaje
- **THEN** la operación se rechaza

#### Scenario: Tramos que no se superponen
- **WHEN** el DNI tiene un boleto del tramo A a B del viaje y se intenta emitirle otro del tramo C a D del mismo viaje
- **THEN** la operación se rechaza

#### Scenario: Usuario con todos los permisos
- **WHEN** un usuario con todos los permisos intenta emitir un boleto duplicado en un viaje
- **THEN** la operación se rechaza igual que para cualquier otro usuario

#### Scenario: Otro viaje del mismo día
- **WHEN** el DNI tiene un boleto en el colectivo regular de una línea y se le vende uno en el refuerzo de esa línea el mismo día
- **THEN** la venta se permite, porque son viajes distintos

### Requirement: Boletos que cuentan para la regla
Solo cuentan los boletos activos con viaje asignado. Un boleto borrado MUST NOT contar, y tampoco un boleto sin fecha mientras no tenga viaje. Un DNI vacío MUST NOT compararse. La regla se evalúa en cada operación nueva y no modifica los duplicados que ya existían antes de ella.

#### Scenario: Boleto borrado
- **WHEN** el DNI tenía un boleto en el viaje que fue borrado y se le vende uno nuevo en ese viaje
- **THEN** la venta se permite

#### Scenario: Boleto sin fecha
- **WHEN** el DNI tiene un boleto sin fecha de la misma ruta y se le vende un boleto con fecha
- **THEN** la venta se permite

#### Scenario: Menor sin DNI
- **WHEN** dos boletos del viaje tienen menores acompañantes sin DNI cargado
- **THEN** los menores no se comparan entre sí

### Requirement: Bloqueo de la venta
Al finalizar una venta, el sistema MUST rechazarla sin crear venta, pasajeros, boletos ni asientos si algún DNI de la venta, de adulto o de menor, ya está en el viaje de ida o en el de vuelta. El control MUST repetirse durante la emisión con los viajes bloqueados, para que dos ventas simultáneas no emitan el mismo DNI en un viaje.

#### Scenario: Duplicado en la ida
- **WHEN** el vendedor finaliza una venta y un DNI ya tiene boleto en el viaje de ida
- **THEN** no se crea nada y el vendedor ve la alerta de duplicado

#### Scenario: Duplicado solo en la vuelta
- **WHEN** el vendedor finaliza un ida y vuelta y un DNI ya tiene boleto solo en el viaje de vuelta
- **THEN** no se crea nada, tampoco el boleto de ida, y el vendedor ve la alerta de duplicado

#### Scenario: Ventas simultáneas
- **WHEN** dos vendedores finalizan a la vez ventas para el mismo DNI en el mismo viaje
- **THEN** solo una venta se emite y la otra ve la alerta de duplicado

### Requirement: Alerta de duplicado en administración
Ante un duplicado en el panel de administración, el sistema SHALL mostrar una notificación persistente titulada "Este pasajero ya tiene boleto en este viaje" ("N pasajeros ya tienen boleto en este viaje" si son varios). Muestra una línea por persona con nombre, DNI, boleto, tramo y salida, un botón "Ver boleto N°X" por boleto que lo abre en otra pestaña, y "Volver a la venta" ("Volver" al reprogramar o editar), que la cierra sin tocar los datos.

#### Scenario: Ver boleto existente
- **WHEN** el vendedor presiona "Ver boleto N°X" en la alerta
- **THEN** la vista del boleto en conflicto se abre en una pestaña nueva y la venta sigue abierta en la pestaña original

#### Scenario: Volver a la venta
- **WHEN** el vendedor presiona "Volver a la venta"
- **THEN** la alerta se cierra y el formulario conserva viaje, asientos y pasajeros cargados

#### Scenario: Varios conflictos
- **WHEN** dos DNI de la venta ya tienen boleto en el viaje
- **THEN** el título dice "2 pasajeros ya tienen boleto en este viaje", la alerta muestra una línea por persona y ofrece "Ver boleto N°X" para cada boleto

### Requirement: Aviso anticipado al avanzar del paso "Pasajeros"
Al presionar "Siguiente" en el paso "Pasajeros", si algún DNI de adulto o de menor ya tiene boleto en el viaje de ida o de vuelta con fecha, el sistema SHALL abrir un modal con el mismo título de la alerta y la indicación de corregir el DNI o elegir otro viaje. Muestra una tarjeta por persona con nombre, DNI, boleto, tramo, asiento y salida, con "Ver boleto" en otra pestaña, y "Volver a pasajeros", que cierra el modal y deja la venta en ese paso. El campo DNI no muestra este aviso.

#### Scenario: DNI ya en el viaje
- **WHEN** el viaje de ida está elegido, un pasajero tiene un DNI que ya tiene boleto en ese viaje y el vendedor presiona "Siguiente"
- **THEN** se abre el modal de duplicado con una tarjeta por persona, "Ver boleto" y "Volver a pasajeros", y la venta no avanza al paso "Resumen"

#### Scenario: Menor en conflicto
- **WHEN** el DNI en conflicto es el de un menor acompañante del boleto existente
- **THEN** su tarjeta muestra el nombre del menor con la etiqueta "Menor"

#### Scenario: Campo DNI sin aviso
- **WHEN** el vendedor carga en el campo DNI un DNI que ya tiene boleto en el viaje elegido
- **THEN** el campo no muestra error de duplicado

#### Scenario: Sin duplicados
- **WHEN** ningún DNI de la venta tiene boleto en los viajes elegidos y el vendedor presiona "Siguiente"
- **THEN** no se abre el modal y la venta avanza

#### Scenario: Sin viaje elegido
- **WHEN** la ida se vende sin fecha y el vendedor presiona "Siguiente"
- **THEN** no se controla duplicado para ese tramo

### Requirement: Reprogramación sin duplicados
Al reprogramar un boleto, el sistema MUST rechazar el cambio si el DNI del pasajero o el de su menor acompañante ya tiene otro boleto activo en el viaje destino, sin contar el propio boleto que se mueve. Si se reprograman ambos tramos, se controla cada viaje destino y no se aplica ningún cambio si alguno tiene conflicto. Boletería ve la alerta de duplicado con "Ver boleto N°X" y "Volver".

#### Scenario: Viaje destino con el mismo DNI
- **WHEN** boletería reprograma un boleto a un viaje donde ese DNI ya tiene otro boleto activo
- **THEN** la reprogramación se rechaza, el boleto queda como estaba y se ve la alerta con "Ver boleto N°X"

#### Scenario: Reprogramar dentro del mismo viaje
- **WHEN** se cambia el asiento de un boleto sin cambiar de viaje
- **THEN** el propio boleto no cuenta como duplicado

#### Scenario: Conflicto solo en la vuelta
- **WHEN** se reprograman ida y vuelta y solo el viaje de vuelta tiene el DNI en otro boleto
- **THEN** no se reprograma ninguno de los dos tramos

#### Scenario: Asignación del primer viaje
- **WHEN** boletería asigna fecha a un boleto sin fecha y el viaje elegido ya tiene ese DNI
- **THEN** la asignación se rechaza con la alerta de duplicado

### Requirement: Asignación de fecha por el cliente sin duplicados
Cuando un cliente elige la fecha de un boleto sin fecha desde su panel, el sistema MUST aplicar la misma regla de duplicados. El cliente MUST ver un mensaje de rechazo que indica que ya tiene un boleto en ese viaje, sin enlaces al panel de administración.

#### Scenario: Cliente elige un viaje donde ya viaja
- **WHEN** el cliente elige fecha y horario de un viaje donde su DNI ya tiene otro boleto activo
- **THEN** el boleto sigue sin fecha y el cliente ve "Ya tenés un boleto en ese viaje. Elegí otra fecha u horario."

### Requirement: Edición de DNI sin duplicados
Al editar un boleto, el sistema MUST rechazar los cambios si se modifica el DNI del pasajero, se modifica el DNI del menor o se agrega un menor, y el DNI resultante ya está en otro boleto activo de alguno de los viajes de los boletos de ese pasajero (ida y vuelta). Una edición que no toca esos datos MUST NOT controlar duplicados. Se muestra la alerta de duplicado y el formulario de edición conserva los datos.

#### Scenario: Cambio de DNI a uno que ya viaja
- **WHEN** se edita el DNI de un boleto por uno que ya tiene boleto en el mismo viaje
- **THEN** la edición se rechaza, no se guarda ningún cambio y se ve la alerta con "Ver boleto N°X"

#### Scenario: Conflicto en el viaje de vuelta
- **WHEN** se edita el DNI del boleto de ida y el nuevo DNI ya tiene boleto en el viaje de vuelta de ese pasajero
- **THEN** la edición se rechaza

#### Scenario: Agregar un menor que ya viaja
- **WHEN** se agrega un menor a un boleto con un DNI que ya tiene boleto en ese viaje
- **THEN** la edición se rechaza

#### Scenario: Edición de otro dato en un duplicado previo
- **WHEN** un boleto que ya estaba duplicado antes de esta regla se edita solo en teléfono o precio
- **THEN** la edición se guarda normalmente
