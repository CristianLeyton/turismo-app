# Spec Delta

## MODIFIED Requirements

### Requirement: Parámetros agrupados en pestañas
La página SHALL organizar los parámetros en pestañas, en este orden: "Venta" (acompañantes, venta fuera de término y, con su permiso, selectores de origen y destino y, con su permiso, observaciones y, con su permiso, precio de los boletos), "Boleto" (con su permiso, emisión al finalizar la venta con el PDF automático y el mensaje de WhatsApp; y líneas de contacto), "Calendario" (días con servicio y excepciones), "Seguridad" (confirmación de contraseña), "Clientes" (panel de clientes), esta solo con el permiso del panel de clientes, y "Apariencia" (color principal), esta solo con el permiso de apariencia. Un único botón "Guardar cambios" MUST guardar todas las pestañas visibles.

#### Scenario: Abrir la página
- **WHEN** un administrador sin el permiso del panel de clientes ni el de apariencia abre Parámetros
- **THEN** ve las pestañas Venta, Boleto, Calendario y Seguridad, en ese orden, con la pestaña Venta abierta

#### Scenario: Abrir la página con el permiso del panel de clientes
- **WHEN** un usuario con `settings.manage` y con el permiso del panel de clientes, sin el de apariencia, abre Parámetros
- **THEN** ve las pestañas Venta, Boleto, Calendario, Seguridad y Clientes, en ese orden, con la pestaña Venta abierta

#### Scenario: Abrir la página con el permiso de apariencia
- **WHEN** un usuario con `settings.manage` y con el permiso de apariencia, sin el del panel de clientes, abre Parámetros
- **THEN** ve las pestañas Venta, Boleto, Calendario, Seguridad y Apariencia, en ese orden, con la pestaña Venta abierta

#### Scenario: Abrir la página con los dos permisos
- **WHEN** un usuario con `settings.manage`, el permiso del panel de clientes y el de apariencia abre Parámetros
- **THEN** ve las pestañas Venta, Boleto, Calendario, Seguridad, Clientes y Apariencia, en ese orden

#### Scenario: Guardar desde otra pestaña
- **WHEN** el administrador cambia un parámetro de Venta, pasa a la pestaña Seguridad, cambia otro y toca "Guardar cambios"
- **THEN** se guardan los dos cambios

#### Scenario: Pestaña Venta con el permiso de los selectores
- **WHEN** un usuario con `settings.manage` y con el permiso de los selectores de origen y destino abre la pestaña Venta
- **THEN** ve las secciones de acompañantes, venta fuera de término y selectores de origen y destino

#### Scenario: Pestaña Venta con el permiso de las observaciones
- **WHEN** un usuario con `settings.manage` y con el permiso de las observaciones abre la pestaña Venta
- **THEN** ve, además de las secciones que le correspondan, la sección "Observaciones"

#### Scenario: Pestaña Venta con el permiso del precio fijo
- **WHEN** un usuario con `settings.manage` y con el permiso del precio fijo abre la pestaña Venta
- **THEN** ve, además de las secciones que le correspondan, la sección "Precio de los boletos", al final de la pestaña

#### Scenario: Pestaña Boleto con el permiso de emisión
- **WHEN** un usuario con `settings.manage` y con el permiso de emisión abre la pestaña Boleto
- **THEN** ve las secciones "Emisión al finalizar la venta" y "Líneas de contacto"

#### Scenario: Pestaña Boleto sin el permiso de emisión
- **WHEN** un usuario con `settings.manage` pero sin el permiso de emisión abre la pestaña Boleto
- **THEN** ve solo la sección "Líneas de contacto"

## ADDED Requirements

### Requirement: Configurar el precio fijo de los boletos
La sección "Precio de los boletos" de la pestaña Venta SHALL ofrecer el interruptor "Precio fijo activo", que sin configurar aparece apagado, y, solo con el interruptor prendido, los campos "Precio del boleto" y "Seguro para menores", con prefijo `$` y los valores guardados.

#### Scenario: Valor inicial
- **WHEN** nadie configuró el precio fijo y un usuario con el permiso abre la pestaña Venta
- **THEN** el interruptor aparece apagado y los campos de montos no se ven

#### Scenario: Prender el precio fijo
- **WHEN** un usuario con el permiso prende "Precio fijo activo", carga 5000 como precio del boleto y 800 como seguro, y guarda
- **THEN** los tres parámetros quedan guardados y la venta calcula el precio con esos montos

#### Scenario: Apagar conserva los montos
- **GIVEN** el precio fijo prendido con precio 5000 y seguro 800
- **WHEN** el usuario apaga el interruptor, guarda y lo vuelve a prender
- **THEN** los campos muestran 5000 y 800

### Requirement: Validación de los montos del precio fijo
Con "Precio fijo activo" prendido, "Precio del boleto" y "Seguro para menores" SHALL ser obligatorios y MUST ser números enteros, sin centavos: el precio entre 1 y 999.999 y el seguro entre 0 y 999.999. Un valor con decimales MUST rechazarse en lugar de redondearse. Con el interruptor apagado, los montos MUST NOT validarse ni modificarse al guardar.

#### Scenario: Prender sin montos
- **WHEN** el usuario prende "Precio fijo activo" sin cargar el precio del boleto y guarda
- **THEN** el campo muestra un mensaje en español que pide el precio del boleto y no se guarda ningún parámetro

#### Scenario: Precio en cero
- **WHEN** el usuario carga 0 como precio del boleto y guarda
- **THEN** el campo muestra que el precio debe ser de al menos $1 y no se guarda ningún parámetro

#### Scenario: Seguro en cero
- **WHEN** el usuario carga 5000 como precio del boleto y 0 como seguro y guarda
- **THEN** se guarda y la venta no suma seguro al viajar con menor

#### Scenario: Monto con centavos
- **WHEN** el usuario carga 5000,50 como precio del boleto y guarda
- **THEN** el campo muestra que el precio debe ser un número entero y no se guarda ningún parámetro

#### Scenario: Monto fuera de rango
- **WHEN** el usuario carga 1000000 como seguro y guarda
- **THEN** el campo muestra que el máximo es $999.999 y no se guarda ningún parámetro

### Requirement: Permiso para configurar el precio fijo
Solo un usuario con el permiso "Configurar precio fijo de los boletos" (`settings.fixed_price`) del módulo Configuración MUST ver la sección "Precio de los boletos" y cambiar sus tres parámetros. Si quien guarda no tiene el permiso, los tres MUST quedar como estaban, aunque el pedido traiga otros valores o no los traiga.

#### Scenario: Sin el permiso
- **WHEN** un usuario con `settings.manage` pero sin el permiso del precio fijo abre la pestaña Venta
- **THEN** no ve la sección "Precio de los boletos"

#### Scenario: Guardar sin el permiso conserva el precio fijo
- **GIVEN** el precio fijo prendido con precio 5000 y seguro 800
- **WHEN** un usuario sin el permiso del precio fijo cambia las horas de venta a 8 y guarda
- **THEN** las horas de venta quedan en 8 y el precio fijo sigue prendido con precio 5000 y seguro 800

#### Scenario: Pedido manipulado
- **GIVEN** el precio fijo apagado
- **WHEN** un usuario sin el permiso del precio fijo guarda Parámetros enviando el precio fijo prendido con precio 1
- **THEN** los demás parámetros se guardan y el precio fijo sigue apagado, sin montos cambiados

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** ni el rol Administrador ni el Vendedor reciben el permiso del precio fijo, y se lo puede asignar desde la matriz de roles
