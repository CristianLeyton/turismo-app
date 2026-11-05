# Spec Delta

## MODIFIED Requirements

### Requirement: Parámetros agrupados en pestañas
La página SHALL organizar los parámetros en pestañas, en este orden: "Venta" (acompañantes y venta fuera de término), "Boleto" (PDF automático, WhatsApp y líneas de contacto), "Calendario" (días con servicio y excepciones), "Seguridad" (confirmación de contraseña), "Clientes" (panel de clientes), esta solo con el permiso del panel de clientes, y "Apariencia" (color principal), esta solo con el permiso de apariencia. Un único botón "Guardar cambios" MUST guardar todas las pestañas visibles.

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

## ADDED Requirements

### Requirement: Cambiar el color principal
La pestaña "Apariencia" SHALL ofrecer el campo "Color principal" con un selector de color, que muestra el color guardado y, sin configurar, el fucsia por defecto, más una vista previa de la paleta del color elegido, actualizada antes de guardar. El campo MUST ser obligatorio y MUST aceptar solo un color hex de 6 dígitos (`#RRGGBB`), con el error en español.

#### Scenario: Cambiar el color
- **WHEN** un usuario con el permiso elige `#10288c` en "Color principal" y guarda
- **THEN** el color queda guardado y, al recargar, la página usa la nueva paleta

#### Scenario: Valor inicial
- **WHEN** nadie configuró el color y un usuario con el permiso abre la pestaña Apariencia
- **THEN** el campo muestra `#c026d3` y la vista previa muestra la paleta fucsia

#### Scenario: Vista previa antes de guardar
- **WHEN** el usuario elige otro color sin guardar
- **THEN** la vista previa muestra la paleta del color elegido y el sitio sigue con el color guardado

#### Scenario: Color inválido
- **WHEN** se envía "azul" o `#12345` como color principal y se guarda
- **THEN** el campo muestra un mensaje en español que pide un color válido y no se guarda ningún parámetro

### Requirement: Permiso para cambiar la apariencia
Solo un usuario con el permiso "Cambiar apariencia" del módulo Configuración MUST poder ver la pestaña "Apariencia" y cambiar el color principal. Al guardar la página, si quien guarda no tiene ese permiso, el color MUST quedar como estaba, aunque el pedido incluya otro valor.

#### Scenario: Sin el permiso
- **WHEN** un usuario con `settings.manage` pero sin el permiso de apariencia abre Parámetros
- **THEN** no ve la pestaña Apariencia

#### Scenario: Pedido manipulado
- **GIVEN** el color principal guardado es `#c026d3`
- **WHEN** un usuario sin el permiso de apariencia guarda Parámetros enviando `#10288c` como color principal
- **THEN** los demás parámetros se guardan y el color principal sigue siendo `#c026d3`

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** el rol Administrador no recibe el permiso de apariencia, y se lo puede asignar desde la matriz de roles
