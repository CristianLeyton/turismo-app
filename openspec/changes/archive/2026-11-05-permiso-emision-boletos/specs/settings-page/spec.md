# Spec Delta

## MODIFIED Requirements

### Requirement: Parámetros agrupados en pestañas
La página SHALL organizar los parámetros en pestañas, en este orden: "Venta" (acompañantes, venta fuera de término y, con su permiso, selectores de origen y destino), "Boleto" (con su permiso, emisión al finalizar la venta con el PDF automático y el mensaje de WhatsApp; y líneas de contacto), "Calendario" (días con servicio y excepciones), "Seguridad" (confirmación de contraseña), "Clientes" (panel de clientes), esta solo con el permiso del panel de clientes, y "Apariencia" (color principal), esta solo con el permiso de apariencia. Un único botón "Guardar cambios" MUST guardar todas las pestañas visibles.

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

#### Scenario: Pestaña Boleto con el permiso de emisión
- **WHEN** un usuario con `settings.manage` y con el permiso de emisión abre la pestaña Boleto
- **THEN** ve las secciones "Emisión al finalizar la venta" y "Líneas de contacto"

#### Scenario: Pestaña Boleto sin el permiso de emisión
- **WHEN** un usuario con `settings.manage` pero sin el permiso de emisión abre la pestaña Boleto
- **THEN** ve solo la sección "Líneas de contacto"

## ADDED Requirements

### Requirement: Permiso para configurar la emisión al finalizar la venta
Solo un usuario con el permiso "Configurar emisión al finalizar la venta" del módulo Configuración MUST ver la sección "Emisión al finalizar la venta" y cambiar el interruptor "Descargar PDF automáticamente al vender" y el mensaje de WhatsApp. Si quien guarda no tiene el permiso, los dos MUST quedar como estaban, aunque el pedido traiga otros valores o no los traiga.

#### Scenario: Cambiar la emisión con el permiso
- **GIVEN** la descarga automática activada
- **WHEN** un usuario con el permiso desactiva "Descargar PDF automáticamente al vender", carga "Tu pasaje: {link}" como mensaje y guarda
- **THEN** la descarga automática queda desactivada y el mensaje guardado es "Tu pasaje: {link}"

#### Scenario: Guardar sin el permiso conserva la emisión por link
- **GIVEN** la descarga automática desactivada y el mensaje "Tu pasaje: {link}"
- **WHEN** un usuario sin el permiso de emisión cambia las horas de venta a 8 y guarda
- **THEN** las horas de venta quedan en 8, la descarga automática sigue desactivada y el mensaje sigue siendo "Tu pasaje: {link}"

#### Scenario: Pedido manipulado
- **GIVEN** la descarga automática desactivada y el mensaje "Tu pasaje: {link}"
- **WHEN** un usuario sin el permiso de emisión guarda Parámetros enviando la descarga automática activada y el mensaje "Otro: {link}"
- **THEN** los demás parámetros se guardan, la descarga automática sigue desactivada y el mensaje sigue siendo "Tu pasaje: {link}"

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** el rol Administrador no recibe el permiso de emisión, y se lo puede asignar desde la matriz de roles
