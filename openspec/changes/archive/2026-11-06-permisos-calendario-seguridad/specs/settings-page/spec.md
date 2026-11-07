## MODIFIED Requirements

### Requirement: Parámetros agrupados en pestañas
La página SHALL organizar los parámetros en pestañas, en este orden: "Venta" (acompañantes, venta fuera de término y, con su permiso, selectores de origen y destino y, con su permiso, observaciones y, con su permiso, precio de los boletos), "Boleto" (con su permiso, emisión al finalizar la venta con el PDF automático y el mensaje de WhatsApp; y líneas de contacto), "Calendario" (días con servicio y excepciones), esta solo con el permiso de calendario, "Seguridad" (confirmación de contraseña), esta solo con el permiso de seguridad, "Clientes" (panel de clientes), esta solo con el permiso del panel de clientes, y "Apariencia" (color principal), esta solo con el permiso de apariencia. Un único botón "Guardar cambios" MUST guardar todas las pestañas visibles.

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

#### Scenario: Pestaña Boleto con el permiso de emisión
- **WHEN** un usuario con `settings.manage` y con el permiso de emisión abre la pestaña Boleto
- **THEN** ve las secciones "Emisión al finalizar la venta" y "Líneas de contacto"

#### Scenario: Pestaña Boleto sin el permiso de emisión
- **WHEN** un usuario con `settings.manage` pero sin el permiso de emisión abre la pestaña Boleto
- **THEN** ve solo la sección "Líneas de contacto"

### Requirement: Texto visible limitado a lo esencial
Cada sección y cada campo SHALL mostrar como texto visible a lo sumo una oración, y solo cuando sin ella el administrador podría configurar algo mal. La información complementaria (ejemplos, formato de carga, permisos que dejan saltear una regla) MUST ir en un ícono de información con tooltip y MUST NOT ser necesaria para configurar bien el parámetro.

#### Scenario: Confirmación de contraseña
- **WHEN** un usuario con el permiso de seguridad abre la pestaña Seguridad
- **THEN** la sección explica una sola vez que se pide la contraseña de quien ejecuta la acción y que, si no coincide, la acción no se ejecuta, y los tres interruptores no repiten esa explicación

#### Scenario: Información de permisos
- **WHEN** un usuario con el permiso de calendario abre la pestaña Calendario
- **THEN** la aclaración sobre el permiso "Vender en días sin servicio" está en un tooltip y no como texto visible

### Requirement: Errores visibles en las pestañas
Cuando el guardado falla por validación, la página SHALL abrir la pestaña que contiene el primer campo con error y desplazarse hasta él. Toda pestaña con campos inválidos MUST mostrar un indicador rojo con la cantidad de errores, que desaparece cuando se vuelve a guardar sin errores en esa pestaña. Una pestaña oculta por falta de permiso MUST NOT validar sus campos ni impedir el guardado.

#### Scenario: Errores en dos pestañas
- **WHEN** un usuario con el permiso de calendario guarda con un teléfono inválido en Boleto y sin ningún día de servicio en Calendario
- **THEN** se abre la pestaña Boleto en el teléfono inválido, y las pestañas Boleto y Calendario muestran un indicador rojo con 1 error cada una

#### Scenario: Error corregido
- **WHEN** después de ese error el usuario corrige los dos campos y guarda
- **THEN** los indicadores desaparecen y la configuración se guarda

#### Scenario: Pestaña oculta sin errores propios
- **GIVEN** un usuario sin el permiso de calendario
- **WHEN** guarda Parámetros con un teléfono inválido en Boleto
- **THEN** solo la pestaña Boleto muestra el indicador rojo y la notificación nombra solo Boleto

## ADDED Requirements

### Requirement: Permiso para configurar el calendario de servicio
Solo un usuario con el permiso "Configurar calendario de servicio" (`settings.calendar`) del módulo Configuración MUST ver la pestaña "Calendario" y cambiar los días con servicio y las excepciones por fecha. Si quien guarda no tiene el permiso, los días con servicio y las excepciones MUST quedar como estaban, aunque el pedido traiga otros valores o no los traiga.

#### Scenario: Sin el permiso
- **WHEN** un usuario con `settings.manage` pero sin el permiso de calendario abre Parámetros
- **THEN** no ve la pestaña Calendario

#### Scenario: Guardar sin el permiso conserva el calendario
- **GIVEN** los días con servicio son lunes a viernes y hay una excepción "sin servicio" el 25/12/2026
- **WHEN** un usuario sin el permiso de calendario cambia las horas de venta a 8 y guarda
- **THEN** las horas de venta quedan en 8 y los días con servicio y la excepción siguen iguales

#### Scenario: Pedido manipulado
- **GIVEN** los días con servicio son lunes a viernes
- **WHEN** un usuario sin el permiso de calendario guarda Parámetros enviando solo el domingo como día con servicio y una excepción nueva
- **THEN** los demás parámetros se guardan y el calendario sigue sin cambios

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** ni el rol Administrador ni el Vendedor reciben el permiso de calendario, y se lo puede asignar desde la matriz de roles

### Requirement: Permiso para configurar la confirmación de contraseña
Solo un usuario con el permiso "Configurar confirmación de contraseña" (`settings.security`) del módulo Configuración MUST ver la pestaña "Seguridad" y cambiar los interruptores de confirmación de contraseña al borrar, reprogramar y editar boletos. Si quien guarda no tiene el permiso, los tres MUST quedar como estaban, aunque el pedido traiga otros valores o no los traiga.

#### Scenario: Sin el permiso
- **WHEN** un usuario con `settings.manage` pero sin el permiso de seguridad abre Parámetros
- **THEN** no ve la pestaña Seguridad

#### Scenario: Guardar sin el permiso conserva la confirmación
- **GIVEN** la confirmación de contraseña está activada al borrar boletos
- **WHEN** un usuario sin el permiso de seguridad cambia las horas de venta a 8 y guarda
- **THEN** las horas de venta quedan en 8 y la confirmación al borrar boletos sigue activada

#### Scenario: Pedido manipulado
- **GIVEN** la confirmación de contraseña está activada en las tres acciones
- **WHEN** un usuario sin el permiso de seguridad guarda Parámetros enviando las tres desactivadas
- **THEN** los demás parámetros se guardan y las tres siguen activadas

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** ni el rol Administrador ni el Vendedor reciben el permiso de seguridad, y se lo puede asignar desde la matriz de roles
