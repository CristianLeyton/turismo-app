## ADDED Requirements

### Requirement: Cobertura del día de hoy
Con "Precio fijo activo" prendido, la grilla SHALL tener al menos un período con "Desde" igual a hoy o anterior. Si al guardar no queda ninguno, ya sea al prender el interruptor, al borrar períodos o al mover su "Desde" hacia adelante, la página MUST mostrar en la grilla "Tiene que haber un precio vigente para hoy: cargá un período que empiece hoy o antes." y no guardar ningún parámetro.

#### Scenario: Prender solo con períodos futuros
- **GIVEN** hoy es el 21/11/2026
- **WHEN** el usuario prende "Precio fijo activo", carga un único período desde el 02/12/2026 y guarda
- **THEN** aparece el mensaje y no se guarda ningún parámetro

#### Scenario: Borrar el período vigente sin otro anterior
- **GIVEN** hoy es el 21/11/2026 y el precio fijo prendido con un período desde el 20/11/2026 y otro desde el 02/12/2026
- **WHEN** el usuario borra el período del 20/11/2026 y guarda
- **THEN** aparece el mensaje y los dos períodos siguen guardados

#### Scenario: Borrar el período vigente con uno anterior
- **GIVEN** hoy es el 21/11/2026 y el precio fijo prendido con períodos desde el 01/11/2026, el 20/11/2026 y el 02/12/2026
- **WHEN** el usuario borra el período del 20/11/2026 y guarda
- **THEN** se guarda y desde ese momento rige el período del 01/11/2026

#### Scenario: Período que empieza hoy
- **GIVEN** hoy es el 21/11/2026
- **WHEN** el usuario prende "Precio fijo activo", carga un único período desde el 21/11/2026 y guarda
- **THEN** se guarda

### Requirement: Migración del precio fijo a períodos
Al actualizar el sistema, si había montos de precio fijo cargados, el precio del boleto y el seguro SHALL convertirse en un único período con "Desde" igual al día de la actualización. Si estaba prendido, MUST seguir prendido y cobrando los mismos montos. Sin montos cargados, la grilla MUST quedar vacía.

#### Scenario: Precio fijo prendido antes de actualizar
- **GIVEN** el precio fijo prendido con precio 5000 y seguro 800
- **WHEN** se actualiza el sistema el 21/11/2026
- **THEN** el precio fijo sigue prendido con un período desde el 21/11/2026 con boleto 5000 y seguro 800, y la venta cobra lo mismo que antes

#### Scenario: Precio fijo nunca configurado
- **GIVEN** nadie configuró el precio fijo
- **WHEN** se actualiza el sistema
- **THEN** el interruptor sigue apagado y la grilla no tiene períodos

## MODIFIED Requirements

### Requirement: Configurar el precio fijo de los boletos
La sección "Precio de los boletos" de la pestaña Venta SHALL ofrecer el interruptor "Precio fijo activo", que sin configurar aparece apagado, y, solo con el interruptor prendido, la grilla "Períodos de precio". Cada fila MUST tener "Desde", "Precio del boleto" y "Seguro para menores", con prefijo `$`, y mostrar sin poder editarlo el "Hasta": el día anterior al "Desde" de la fila siguiente, o "en adelante" en la última. La grilla MUST ordenarse por "Desde" al guardar y marcar como "Vigente" el período que rige hoy. Los períodos pasados MUST conservarse.

#### Scenario: Valor inicial
- **WHEN** nadie configuró el precio fijo y un usuario con el permiso abre la pestaña Venta
- **THEN** el interruptor aparece apagado y la grilla no se ve

#### Scenario: Prender el precio fijo
- **WHEN** un usuario con el permiso prende "Precio fijo activo", carga un período desde hoy con boleto 5000 y seguro 800, y guarda
- **THEN** el interruptor y el período quedan guardados y la venta calcula el precio con esos montos

#### Scenario: Hasta calculado
- **GIVEN** períodos desde el 20/11/2026 y desde el 02/12/2026
- **WHEN** el usuario abre la pestaña Venta
- **THEN** el primero muestra "Hasta 01/12/2026" y el segundo "en adelante"

#### Scenario: Orden al guardar
- **WHEN** el usuario carga primero un período desde el 02/12/2026 y después uno desde el 20/11/2026, y guarda
- **THEN** la grilla muestra primero el del 20/11/2026

#### Scenario: Período vigente marcado
- **GIVEN** hoy es el 25/11/2026 y hay períodos desde el 01/11/2026, el 20/11/2026 y el 02/12/2026
- **WHEN** el usuario abre la pestaña Venta
- **THEN** solo el período del 20/11/2026 aparece como "Vigente"

#### Scenario: Apagar conserva los montos
- **GIVEN** el precio fijo prendido con dos períodos
- **WHEN** el usuario apaga el interruptor, guarda y lo vuelve a prender
- **THEN** la grilla muestra los mismos dos períodos

### Requirement: Validación de los montos del precio fijo
Con "Precio fijo activo" prendido, cada período SHALL tener "Desde", "Precio del boleto" y "Seguro para menores" obligatorios. Dos períodos MUST NOT tener el mismo "Desde". Los montos MUST ser números enteros, sin centavos: el precio entre 1 y 999.999 y el seguro entre 0 y 999.999. Un valor con decimales MUST rechazarse en lugar de redondearse. Con el interruptor apagado, los períodos MUST NOT validarse ni modificarse al guardar.

#### Scenario: Prender sin montos
- **WHEN** el usuario prende "Precio fijo activo", carga un período desde hoy sin el precio del boleto y guarda
- **THEN** el campo muestra un mensaje en español que pide el precio del boleto y no se guarda ningún parámetro

#### Scenario: Período sin fecha
- **WHEN** el usuario carga un período con montos pero sin "Desde" y guarda
- **THEN** el campo muestra un mensaje en español que pide la fecha y no se guarda ningún parámetro

#### Scenario: Fecha repetida
- **WHEN** el usuario carga dos períodos desde el 02/12/2026 y guarda
- **THEN** la grilla muestra "Hay más de un período que empieza el mismo día." y no se guarda ningún parámetro

#### Scenario: Precio en cero
- **WHEN** el usuario carga 0 como precio del boleto de un período y guarda
- **THEN** el campo muestra que el precio debe ser de al menos $1 y no se guarda ningún parámetro

#### Scenario: Seguro en cero
- **WHEN** el usuario carga un período desde hoy con 5000 como precio del boleto y 0 como seguro, y guarda
- **THEN** se guarda y la venta no suma seguro al viajar con menor

#### Scenario: Monto con centavos
- **WHEN** el usuario carga 5000,50 como precio del boleto de un período y guarda
- **THEN** el campo muestra que el precio debe ser un número entero y no se guarda ningún parámetro

#### Scenario: Monto fuera de rango
- **WHEN** el usuario carga 1000000 como seguro de un período y guarda
- **THEN** el campo muestra que el máximo es $999.999 y no se guarda ningún parámetro

#### Scenario: Apagado no valida
- **GIVEN** el precio fijo prendido con un período vigente
- **WHEN** el usuario apaga el interruptor y guarda
- **THEN** se guarda y los períodos siguen como estaban

### Requirement: Permiso para configurar el precio fijo
Solo un usuario con el permiso "Configurar precio fijo de los boletos" (`settings.fixed_price`) del módulo Configuración MUST ver la sección "Precio de los boletos" y cambiar el interruptor y los períodos de precio. Si quien guarda no tiene el permiso, el interruptor y los períodos MUST quedar como estaban, aunque el pedido traiga otros valores o no los traiga.

#### Scenario: Sin el permiso
- **WHEN** un usuario con `settings.manage` pero sin el permiso del precio fijo abre la pestaña Venta
- **THEN** no ve la sección "Precio de los boletos"

#### Scenario: Guardar sin el permiso conserva el precio fijo
- **GIVEN** el precio fijo prendido con un período desde el 20/11/2026 con boleto 5000 y seguro 800
- **WHEN** un usuario sin el permiso del precio fijo cambia las horas de venta a 8 y guarda
- **THEN** las horas de venta quedan en 8 y el precio fijo sigue prendido con el mismo período

#### Scenario: Pedido manipulado
- **GIVEN** el precio fijo apagado
- **WHEN** un usuario sin el permiso del precio fijo guarda Parámetros enviando el precio fijo prendido con un período de precio 1
- **THEN** los demás parámetros se guardan y el precio fijo sigue apagado, sin períodos cambiados

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** ni el rol Administrador ni el Vendedor reciben el permiso del precio fijo, y se lo puede asignar desde la matriz de roles
