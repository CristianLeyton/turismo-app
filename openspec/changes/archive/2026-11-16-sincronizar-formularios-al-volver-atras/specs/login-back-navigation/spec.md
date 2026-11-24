# Spec Delta

## Purpose

Garantiza que las páginas de ingreso de los paneles envíen lo que el usuario ve en pantalla, aunque haya llegado a ellas con los botones Atrás o Adelante del navegador.

## ADDED Requirements

### Requirement: Los valores restaurados por el navegador se envían en el ingreso
Cuando el usuario llega a la página de ingreso de un panel (`/admin/login` o `/clientes/login`) con Atrás o Adelante del navegador, y el navegador vuelve a poner en los campos lo escrito antes, el formulario SHALL enviar esos valores visibles. El ingreso MUST NOT rechazar como vacío un campo que muestra texto.

#### Scenario: Volver atrás al login de administración con el usuario escrito
- **GIVEN** un usuario escribió su nombre de usuario en `/admin/login`, fue al panel de clientes con el enlace "¿Sos cliente?" y volvió con Atrás
- **WHEN** el campo "Nombre de usuario" muestra el texto restaurado y el usuario completa la contraseña y presiona "Entrar"
- **THEN** el ingreso se evalúa con ese nombre de usuario y no aparece el error "El campo nombre de usuario es obligatorio."

#### Scenario: Volver atrás al login de clientes con el DNI escrito
- **GIVEN** un cliente escribió su DNI en `/clientes/login`, salió de la página y volvió con Atrás
- **WHEN** el campo "DNI" muestra el texto restaurado y el cliente presiona "Entrar" con su contraseña
- **THEN** el ingreso se evalúa con ese DNI y no aparece el error "El DNI es obligatorio."

#### Scenario: Credenciales incorrectas tras volver atrás
- **GIVEN** el usuario volvió con Atrás a una página de ingreso y el campo de usuario o DNI muestra texto restaurado
- **WHEN** envía el formulario con una contraseña incorrecta
- **THEN** ve el mensaje de credenciales incorrectas del panel, no el de campo obligatorio

### Requirement: Entrada normal al ingreso sin cambios
Al abrir la página de ingreso de forma normal (enlace, URL escrita, recarga o redirección), el formulario SHALL comportarse como hasta ahora: los campos vacíos se informan como obligatorios y el autocompletado del gestor de contraseñas sigue funcionando.

#### Scenario: Envío con el usuario vacío en una entrada normal
- **WHEN** el usuario abre `/admin/login` desde la URL y presiona "Entrar" sin escribir el nombre de usuario
- **THEN** ve "El campo nombre de usuario es obligatorio."

#### Scenario: Envío con el DNI vacío en una entrada normal
- **WHEN** el cliente abre `/clientes/login` desde la URL y presiona "Entrar" sin escribir el DNI
- **THEN** ve "El DNI es obligatorio."
