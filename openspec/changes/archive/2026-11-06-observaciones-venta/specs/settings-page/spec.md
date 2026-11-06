# Spec Delta

## MODIFIED Requirements

### Requirement: Parámetros agrupados en pestañas
La página SHALL organizar los parámetros en pestañas, en este orden: "Venta" (acompañantes, venta fuera de término y, con su permiso, selectores de origen y destino y, con su permiso, observaciones), "Boleto" (con su permiso, emisión al finalizar la venta con el PDF automático y el mensaje de WhatsApp; y líneas de contacto), "Calendario" (días con servicio y excepciones), "Seguridad" (confirmación de contraseña), "Clientes" (panel de clientes), esta solo con el permiso del panel de clientes, y "Apariencia" (color principal), esta solo con el permiso de apariencia. Un único botón "Guardar cambios" MUST guardar todas las pestañas visibles.

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

#### Scenario: Pestaña Boleto con el permiso de emisión
- **WHEN** un usuario con `settings.manage` y con el permiso de emisión abre la pestaña Boleto
- **THEN** ve las secciones "Emisión al finalizar la venta" y "Líneas de contacto"

#### Scenario: Pestaña Boleto sin el permiso de emisión
- **WHEN** un usuario con `settings.manage` pero sin el permiso de emisión abre la pestaña Boleto
- **THEN** ve solo la sección "Líneas de contacto"

## ADDED Requirements

### Requirement: Activar las observaciones de la venta
La pestaña "Venta" SHALL ofrecer, en la sección "Observaciones", el interruptor "Observaciones en la venta", que muestra el valor guardado y, sin configurar, aparece apagado. Solo un usuario con el permiso "Activar/desactivar observaciones" (`settings.sale_observations`) del módulo Configuración MUST ver la sección y cambiar el parámetro. Si quien guarda no tiene el permiso, el parámetro MUST quedar como estaba, aunque el pedido traiga otro valor.

#### Scenario: Prender las observaciones
- **WHEN** un usuario con el permiso prende "Observaciones en la venta" y guarda
- **THEN** el parámetro queda prendido y el paso "Resumen" de la venta muestra el campo "Observaciones"

#### Scenario: Valor inicial
- **WHEN** nadie configuró el parámetro y un usuario con el permiso abre la pestaña Venta
- **THEN** el interruptor aparece apagado

#### Scenario: Sin el permiso
- **WHEN** un usuario con `settings.manage` pero sin el permiso de las observaciones abre la pestaña Venta
- **THEN** no ve la sección "Observaciones"

#### Scenario: Pedido manipulado
- **GIVEN** el parámetro apagado
- **WHEN** un usuario sin el permiso de las observaciones guarda Parámetros enviando el parámetro prendido
- **THEN** los demás parámetros se guardan y el parámetro sigue apagado

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** ni el rol Administrador ni el Vendedor reciben el permiso de las observaciones, y se lo puede asignar desde la matriz de roles
