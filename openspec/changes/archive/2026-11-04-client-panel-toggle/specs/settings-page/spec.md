# Spec Delta

## MODIFIED Requirements

### Requirement: Parámetros agrupados en pestañas
La página SHALL organizar los parámetros en pestañas, en este orden: "Venta" (acompañantes y venta fuera de término), "Boleto" (PDF automático, WhatsApp y líneas de contacto), "Calendario" (días con servicio y excepciones), "Seguridad" (confirmación de contraseña) y "Clientes" (panel de clientes), esta última solo con el permiso del panel de clientes. Un único botón "Guardar cambios" MUST guardar todas las pestañas visibles.

#### Scenario: Abrir la página
- **WHEN** un administrador sin el permiso del panel de clientes abre Parámetros
- **THEN** ve las pestañas Venta, Boleto, Calendario y Seguridad, en ese orden, con la pestaña Venta abierta

#### Scenario: Abrir la página con el permiso del panel de clientes
- **WHEN** un usuario con `settings.manage` y con el permiso del panel de clientes abre Parámetros
- **THEN** ve las pestañas Venta, Boleto, Calendario, Seguridad y Clientes, en ese orden, con la pestaña Venta abierta

#### Scenario: Guardar desde otra pestaña
- **WHEN** el administrador cambia un parámetro de Venta, pasa a la pestaña Seguridad, cambia otro y toca "Guardar cambios"
- **THEN** se guardan los dos cambios

## ADDED Requirements

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
