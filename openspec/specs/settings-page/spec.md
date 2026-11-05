# settings-page Specification

## Purpose

Define cómo se presenta y se guarda la página Configuración > Parámetros: agrupación en pestañas, qué texto se muestra, cómo se informan los errores de validación y las reglas de los parámetros que no pertenecen a otra capacidad.

## Requirements

### Requirement: Acceso a la página de parámetros
La página "Parámetros", con tilde, SHALL figurar en el grupo "Configuración" del menú. Solo un usuario con el permiso `settings.manage` MUST poder verla en el menú y abrirla.

#### Scenario: Administrador con permiso
- **WHEN** un usuario con `settings.manage` abre el menú Configuración
- **THEN** ve "Parámetros" y puede abrir la página

#### Scenario: Usuario sin permiso
- **WHEN** un usuario sin `settings.manage` intenta abrir la página
- **THEN** no la ve en el menú y el acceso se rechaza

### Requirement: Parámetros agrupados en pestañas
La página SHALL organizar los parámetros en pestañas, en este orden: "Venta" (acompañantes, venta fuera de término y, con su permiso, selectores de origen y destino), "Boleto" (PDF automático, WhatsApp y líneas de contacto), "Calendario" (días con servicio y excepciones), "Seguridad" (confirmación de contraseña), "Clientes" (panel de clientes), esta solo con el permiso del panel de clientes, y "Apariencia" (color principal), esta solo con el permiso de apariencia. Un único botón "Guardar cambios" MUST guardar todas las pestañas visibles.

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

### Requirement: Texto visible limitado a lo esencial
Cada sección y cada campo SHALL mostrar como texto visible a lo sumo una oración, y solo cuando sin ella el administrador podría configurar algo mal. La información complementaria (ejemplos, formato de carga, permisos que dejan saltear una regla) MUST ir en un ícono de información con tooltip y MUST NOT ser necesaria para configurar bien el parámetro.

#### Scenario: Confirmación de contraseña
- **WHEN** el administrador abre la pestaña Seguridad
- **THEN** la sección explica una sola vez que se pide la contraseña de quien ejecuta la acción y que, si no coincide, la acción no se ejecuta, y los tres interruptores no repiten esa explicación

#### Scenario: Información de permisos
- **WHEN** el administrador abre la pestaña Calendario
- **THEN** la aclaración sobre el permiso "Vender en días sin servicio" está en un tooltip y no como texto visible

### Requirement: Guardado todo o nada
Al tocar "Guardar cambios", la página SHALL guardar todos los parámetros solo si todos son válidos. Si algún campo, de cualquier pestaña, no es válido, MUST NOT guardarse ningún parámetro.

#### Scenario: Un error en otra pestaña
- **WHEN** el administrador cambia las horas de venta a 8, carga un teléfono de contacto inválido y guarda
- **THEN** no se guarda ningún cambio y las horas de venta siguen con el valor anterior

### Requirement: Errores visibles en las pestañas
Cuando el guardado falla por validación, la página SHALL abrir la pestaña que contiene el primer campo con error y desplazarse hasta él. Toda pestaña con campos inválidos MUST mostrar un indicador rojo con la cantidad de errores, que desaparece cuando se vuelve a guardar sin errores en esa pestaña.

#### Scenario: Errores en dos pestañas
- **WHEN** el administrador guarda con un teléfono inválido en Boleto y sin ningún día de servicio en Calendario
- **THEN** se abre la pestaña Boleto en el teléfono inválido, y las pestañas Boleto y Calendario muestran un indicador rojo con 1 error cada una

#### Scenario: Error corregido
- **WHEN** después de ese error el administrador corrige los dos campos y guarda
- **THEN** los indicadores desaparecen y la configuración se guarda

### Requirement: Aviso de guardado fallido
Cuando el guardado falla por validación, la página SHALL mostrar una notificación de error que diga que no se guardó ningún cambio y nombre las pestañas que tienen errores.

#### Scenario: Notificación con pestañas
- **WHEN** el guardado falla con errores en las pestañas Boleto y Calendario
- **THEN** aparece una notificación de error que dice que no se guardaron los cambios y pide revisar Boleto y Calendario

### Requirement: Mensajes de validación en español
Todo error de validación de la página SHALL mostrarse en español rioplatense con voseo (por ejemplo, "Ingresá…", "Elegí…") y MUST NOT mostrarse en inglés.

#### Scenario: Valor fuera de rango
- **WHEN** el administrador carga 800 en las horas de venta y guarda
- **THEN** el campo muestra un mensaje en español que indica que el máximo es 720 horas

### Requirement: Validación de las horas de venta fuera de término
Las horas de venta después de la salida SHALL ser obligatorias y MUST ser un número entero entre 0 y 720. Un valor con decimales MUST rechazarse en lugar de redondearse.

#### Scenario: Valor con decimales
- **WHEN** el administrador carga 2,5 horas y guarda
- **THEN** el campo muestra que la cantidad de horas debe ser un número entero y no se guarda nada

#### Scenario: Sin límite
- **WHEN** el administrador carga 0 y guarda
- **THEN** el valor se guarda y la venta fuera de término queda sin límite

### Requirement: Validación del mensaje de WhatsApp
El mensaje de WhatsApp SHALL ser opcional: vacío, se usa el texto por defecto. Si se carga, MUST incluir `{link}` y tener como máximo 300 caracteres. Mientras el campo está oculto porque la descarga automática está activada, guardar MUST NOT modificar el mensaje guardado.

#### Scenario: Mensaje sin el link
- **WHEN** el administrador carga "Gracias por viajar con nosotros" y guarda
- **THEN** el campo muestra que el mensaje tiene que incluir {link} y no se guarda nada

#### Scenario: Mensaje vacío
- **WHEN** el administrador borra el mensaje y guarda
- **THEN** se guarda y al compartir se usa el texto por defecto "Podés acceder a tu boleto desde aquí: {link}"

#### Scenario: Campo oculto
- **GIVEN** el mensaje guardado es "Tu pasaje: {link}"
- **WHEN** el administrador activa la descarga automática del PDF y guarda
- **THEN** el mensaje guardado sigue siendo "Tu pasaje: {link}" y vuelve a verse al desactivar la descarga automática

### Requirement: Activar y desactivar el panel de clientes
La pestaña "Clientes" SHALL ofrecer el interruptor "Panel de clientes activo", que muestra el valor guardado y, sin configurar, aparece desactivado. Solo un usuario con el permiso "Activar/desactivar panel de clientes" del módulo Configuración MUST poder ver la pestaña y cambiar el parámetro. Al guardar la página, si quien guarda no tiene ese permiso, el parámetro MUST quedar como estaba, aunque el pedido incluya otro valor.

#### Scenario: Activar el panel
- **WHEN** un usuario con el permiso activa "Panel de clientes activo" y guarda
- **THEN** el parámetro queda activado y `/clientes/login` deja de responder 404

#### Scenario: Valor inicial
- **WHEN** nadie configuró el parámetro y un usuario con el permiso abre la pestaña Clientes
- **THEN** el interruptor aparece desactivado

#### Scenario: Sin el permiso
- **WHEN** un usuario con `settings.manage` pero sin el permiso del panel de clientes abre Parámetros
- **THEN** no ve la pestaña Clientes

#### Scenario: Pedido manipulado
- **GIVEN** el panel de clientes desactivado
- **WHEN** un usuario sin el permiso del panel de clientes guarda Parámetros enviando el parámetro activado
- **THEN** los demás parámetros se guardan y el panel de clientes sigue desactivado

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** el rol Administrador no recibe el permiso del panel de clientes, y se lo puede asignar desde la matriz de roles

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

### Requirement: Elegir el estilo de los selectores de origen y destino
La pestaña "Venta" SHALL ofrecer el interruptor "Selectores de origen y destino detallados", que muestra el valor guardado y, sin configurar, aparece apagado. Solo un usuario con el permiso "Configurar selectores de origen y destino" del módulo Configuración MUST ver la sección y cambiar el parámetro. Si quien guarda no tiene el permiso, el parámetro MUST quedar como estaba, aunque el pedido traiga otro valor.

#### Scenario: Prender los selectores detallados
- **WHEN** un usuario con el permiso prende "Selectores de origen y destino detallados" y guarda
- **THEN** el parámetro queda prendido y la venta muestra los selectores detallados

#### Scenario: Valor inicial
- **WHEN** nadie configuró el parámetro y un usuario con el permiso abre la pestaña Venta
- **THEN** el interruptor aparece apagado

#### Scenario: Sin el permiso
- **WHEN** un usuario con `settings.manage` pero sin el permiso de los selectores abre la pestaña Venta
- **THEN** no ve la sección de los selectores de origen y destino

#### Scenario: Pedido manipulado
- **GIVEN** el parámetro apagado
- **WHEN** un usuario sin el permiso de los selectores guarda Parámetros enviando el parámetro prendido
- **THEN** los demás parámetros se guardan y el parámetro sigue apagado

#### Scenario: Permiso no asignado por defecto
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** el rol Administrador no recibe el permiso de los selectores, y se lo puede asignar desde la matriz de roles
