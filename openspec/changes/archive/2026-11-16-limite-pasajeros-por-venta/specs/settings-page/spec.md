## MODIFIED Requirements

### Requirement: Parámetros agrupados en pestañas
La página SHALL organizar los parámetros en pestañas, en este orden: "Venta" (acompañantes, venta fuera de término y, con su permiso, pasajeros por venta y, con su permiso, selectores de origen y destino y, con su permiso, observaciones y, con su permiso, precio de los boletos), "Boleto" (con su permiso, emisión al finalizar la venta con el PDF automático y el mensaje de WhatsApp; y líneas de contacto), "Calendario" (días con servicio y excepciones), esta solo con el permiso de calendario, "Seguridad" (confirmación de contraseña), esta solo con el permiso de seguridad, "Clientes" (panel de clientes), esta solo con el permiso del panel de clientes, y "Apariencia" (color principal), esta solo con el permiso de apariencia. Un único botón "Guardar cambios" MUST guardar todas las pestañas visibles.

#### Scenario: Abrir la página
- **WHEN** un usuario con `settings.manage` y sin los permisos de calendario, seguridad, panel de clientes ni apariencia abre Parámetros
- **THEN** ve las pestañas Venta y Boleto, en ese orden, con la pestaña Venta abierta

#### Scenario: Abrir la página con los permisos de calendario y seguridad
- **WHEN** un usuario con `settings.manage`, el permiso de calendario y el de seguridad, sin los del panel de clientes ni apariencia, abre Parámetros
- **THEN** ve las pestañas Venta, Boleto, Calendario y Seguridad, en ese orden, con la pestaña Venta abierta

#### Scenario: Abrir la página con un solo permiso de pestaña
- **WHEN** un usuario con `settings.manage` y solo el permiso de seguridad abre Parámetros
- **THEN** ve las pestañas Venta, Boleto y Seguridad, en ese orden

#### Scenario: Abrir la página con el permiso del panel de clientes
- **WHEN** un usuario con `settings.manage` y con el permiso del panel de clientes, sin los de calendario, seguridad ni apariencia, abre Parámetros
- **THEN** ve las pestañas Venta, Boleto y Clientes, en ese orden, con la pestaña Venta abierta

#### Scenario: Abrir la página con el permiso de apariencia
- **WHEN** un usuario con `settings.manage` y con el permiso de apariencia, sin los de calendario, seguridad ni panel de clientes, abre Parámetros
- **THEN** ve las pestañas Venta, Boleto y Apariencia, en ese orden, con la pestaña Venta abierta

#### Scenario: Abrir la página con los dos permisos
- **WHEN** un usuario con `settings.manage`, el permiso del panel de clientes y el de apariencia, sin los de calendario ni seguridad, abre Parámetros
- **THEN** ve las pestañas Venta, Boleto, Clientes y Apariencia, en ese orden

#### Scenario: Abrir la página con todos los permisos
- **WHEN** un usuario con `settings.manage` y los permisos de calendario, seguridad, panel de clientes y apariencia abre Parámetros
- **THEN** ve las pestañas Venta, Boleto, Calendario, Seguridad, Clientes y Apariencia, en ese orden

#### Scenario: Guardar desde otra pestaña
- **WHEN** un usuario con el permiso de seguridad cambia un parámetro de Venta, pasa a la pestaña Seguridad, cambia otro y toca "Guardar cambios"
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

#### Scenario: Pestaña Venta con el permiso del límite de pasajeros
- **WHEN** un usuario con `settings.manage` y con el permiso del límite de pasajeros abre la pestaña Venta
- **THEN** ve la sección "Pasajeros por venta" después de "Venta fuera de término"

#### Scenario: Pestaña Boleto con el permiso de emisión
- **WHEN** un usuario con `settings.manage` y con el permiso de emisión abre la pestaña Boleto
- **THEN** ve las secciones "Emisión al finalizar la venta" y "Líneas de contacto"

#### Scenario: Pestaña Boleto sin el permiso de emisión
- **WHEN** un usuario con `settings.manage` pero sin el permiso de emisión abre la pestaña Boleto
- **THEN** ve solo la sección "Líneas de contacto"

## ADDED Requirements

### Requirement: Configurar el límite de pasajeros por venta
La sección "Pasajeros por venta" de la pestaña Venta SHALL ofrecer el campo "Máximo de pasajeros por venta", que muestra el valor guardado y, sin configurar, 4. El campo MUST ser obligatorio y aceptar solo números enteros de 1 a 8, con los errores en español con voseo. Cambiar el límite MUST NOT modificar ventas ya emitidas.

#### Scenario: Valor inicial
- **WHEN** nadie configuró el límite y un usuario con el permiso abre la pestaña Venta
- **THEN** el campo muestra 4

#### Scenario: Cambiar el límite
- **WHEN** un usuario con el permiso escribe 6 y guarda
- **THEN** el parámetro queda en 6 y la venta ofrece de 1 a 6 pasajeros

#### Scenario: Valor fuera de rango
- **WHEN** el usuario escribe 0 o 9 y guarda
- **THEN** no se guarda ningún parámetro y el campo muestra el error debajo, en español

#### Scenario: Valor con decimales
- **WHEN** el usuario escribe 2,5 y guarda
- **THEN** no se guarda ningún parámetro y el campo indica que tiene que ser un número entero

#### Scenario: Campo vacío
- **WHEN** el usuario deja el campo vacío y guarda
- **THEN** no se guarda ningún parámetro y el campo pide ingresar la cantidad

#### Scenario: Ventas ya emitidas
- **GIVEN** una venta emitida con 4 pasajeros
- **WHEN** el límite baja a 2
- **THEN** la venta y sus 4 boletos siguen iguales

### Requirement: Permiso para configurar el límite de pasajeros por venta
Solo un usuario con el permiso "Configurar límite de pasajeros por venta" (`settings.passenger_limit`) del módulo Configuración MUST ver la sección "Pasajeros por venta" y cambiar el parámetro. Si quien guarda no tiene el permiso, el límite MUST quedar como estaba, aunque el pedido traiga otro valor o no lo traiga.

#### Scenario: Sin el permiso
- **WHEN** un usuario con `settings.manage` pero sin el permiso del límite de pasajeros abre la pestaña Venta
- **THEN** no ve la sección "Pasajeros por venta"

#### Scenario: Guardar sin el permiso conserva el límite
- **GIVEN** el límite de pasajeros por venta en 6
- **WHEN** un usuario sin el permiso cambia las horas de venta a 8 y guarda
- **THEN** las horas de venta quedan en 8 y el límite sigue en 6

#### Scenario: Pedido manipulado
- **GIVEN** el límite de pasajeros por venta en 4
- **WHEN** un usuario sin el permiso guarda Parámetros enviando 8 como límite
- **THEN** los demás parámetros se guardan y el límite sigue en 4

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** ni el rol Administrador ni el Vendedor reciben el permiso del límite de pasajeros, y se lo puede asignar desde la matriz de roles
