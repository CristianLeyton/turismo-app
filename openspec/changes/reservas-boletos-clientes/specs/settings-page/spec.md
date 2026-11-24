## MODIFIED Requirements

### Requirement: Parámetros agrupados en pestañas
La página SHALL organizar los parámetros en pestañas, en este orden: "Venta" (acompañantes, venta fuera de término y, con su permiso, pasajeros por venta y, con su permiso, selectores de origen y destino y, con su permiso, observaciones y, con su permiso, precio de los boletos), "Boleto" (con su permiso, emisión al finalizar la venta con el PDF automático y el mensaje de WhatsApp; y líneas de contacto), "Calendario" (días con servicio y excepciones), esta solo con el permiso de calendario, "Seguridad" (confirmación de contraseña), esta solo con el permiso de seguridad, "Clientes" (panel de clientes), esta solo con el permiso del panel de clientes, "Reservas" (reservas de clientes), esta solo con el permiso de reservas y mientras el interruptor del panel de clientes esté prendido en el formulario, y "Apariencia" (color principal), esta solo con el permiso de apariencia. Un único botón "Guardar cambios" MUST guardar todas las pestañas visibles.

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

#### Scenario: Abrir la página con el permiso de reservas y el panel prendido
- **GIVEN** el panel de clientes prendido
- **WHEN** un usuario con `settings.manage`, el permiso del panel de clientes y el de reservas, sin los de calendario, seguridad ni apariencia, abre Parámetros
- **THEN** ve las pestañas Venta, Boleto, Clientes y Reservas, en ese orden

## ADDED Requirements

### Requirement: Pestaña Reservas solo con el panel de clientes prendido
La pestaña "Reservas" SHALL mostrarse solo si el panel de clientes está prendido: según el interruptor del formulario, si quien edita ve la pestaña Clientes, o según el valor guardado, si no la ve. MUST aparecer o desaparecer al cambiar el interruptor, sin guardar antes. Oculta, guardar MUST conservar todos sus valores y MUST NOT validarlos.

#### Scenario: Apagar el panel en el formulario
- **GIVEN** un usuario con los permisos del panel de clientes y de reservas
- **WHEN** apaga "Panel de clientes activo" sin guardar
- **THEN** la pestaña Reservas desaparece

#### Scenario: Guardar con la pestaña oculta
- **GIVEN** horas de reserva guardadas en 48 y el panel de clientes apagado
- **WHEN** el usuario guarda Parámetros
- **THEN** las horas de reserva siguen en 48

### Requirement: Parámetros de la pestaña Reservas
La pestaña "Reservas" SHALL ofrecer: el interruptor "Reservas de clientes activas" (apagado sin configurar); "Anticipación mínima para reservar" en horas (24, de 0 a 720); "Horas de reserva del asiento" (24, de 1 a 720); "Corte antes de la salida" en horas (1, de 0 a 72); "Métodos de pago para reservas", selección múltiple de los métodos activos; y "Datos para transferir": alias, CBU, titular y banco opcional. Los números MUST ser enteros y obligatorios, con errores en español con voseo.

#### Scenario: Valores iniciales
- **WHEN** nadie configuró las reservas y un usuario con el permiso abre la pestaña
- **THEN** ve el interruptor apagado, 24, 24 y 1 horas, ningún método elegido y los datos para transferir vacíos

#### Scenario: Valor inválido
- **WHEN** el usuario carga 0 en "Horas de reserva del asiento" o 2,5 en el corte y guarda
- **THEN** no se guarda ningún parámetro y el campo muestra el error en español

### Requirement: Datos para transferir válidos
Los datos para transferir SHALL ser opcionales en conjunto, pero si se carga alguno de alias, CBU o titular, los tres MUST ser obligatorios. El CBU MUST tener exactamente 22 dígitos, el alias entre 6 y 20 caracteres de letras, números, puntos o guiones, y el titular y el banco hasta 100 caracteres.

#### Scenario: Datos vacíos
- **WHEN** el usuario deja vacíos alias, CBU, titular y banco y guarda
- **THEN** se guarda y el cliente no ve datos para transferir

#### Scenario: Datos incompletos
- **WHEN** el usuario carga solo el alias y guarda
- **THEN** no se guarda nada y se piden el CBU y el titular

#### Scenario: CBU inválido
- **WHEN** el usuario carga un CBU de 20 dígitos y guarda
- **THEN** no se guarda nada y se avisa que el CBU tiene 22 números

### Requirement: Aviso con el precio fijo apagado
Con el precio fijo apagado, la pestaña "Reservas" SHALL mostrar el aviso "Las reservas no se ofrecen a los clientes mientras el precio fijo esté apagado." El interruptor de reservas MUST poder prenderse igual.

#### Scenario: Precio fijo apagado
- **GIVEN** el precio fijo apagado
- **WHEN** un usuario con el permiso abre la pestaña Reservas
- **THEN** ve el aviso y puede prender las reservas

### Requirement: Permiso para configurar las reservas
Solo un usuario con el permiso "Configurar reservas de clientes" (`settings.client_bookings`) del módulo Configuración MUST ver la pestaña "Reservas" y cambiar sus parámetros. Si quien guarda no tiene el permiso, todos sus parámetros MUST quedar como estaban, aunque el pedido traiga otros valores.

#### Scenario: Sin el permiso
- **WHEN** un usuario con `settings.manage` pero sin el permiso de reservas abre Parámetros
- **THEN** no ve la pestaña Reservas

#### Scenario: Pedido manipulado
- **GIVEN** las reservas apagadas
- **WHEN** un usuario sin el permiso guarda Parámetros enviando las reservas prendidas
- **THEN** los demás parámetros se guardan y las reservas siguen apagadas

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** ni el rol Administrador ni el Vendedor reciben el permiso de reservas
