# Spec Delta

## Purpose

Define cómo se administran los usuarios del panel, incluida la protección del primer administrador de la instalación para que nunca se pueda eliminar ni dejar de ser administrador.

## ADDED Requirements

### Requirement: Protección del primer administrador
El primer administrador creado en la instalación SHALL aparecer en la lista de usuarios. Solo un administrador (con el rol Administrador o superadministrador) MUST poder editar sus datos y restablecer su contraseña; para cualquier otro usuario esas acciones MUST verse deshabilitadas en esa fila y la edición MUST rechazarse también fuera de la interfaz. Nadie, ni siquiera un superadministrador, MUST poder eliminarlo ni eliminarlo permanentemente: esas acciones MUST verse deshabilitadas en su fila y MUST rechazarse también si se intenta ejecutarlas sin pasar por la interfaz. Al editarlo, sus roles MUST NOT poder cambiarse, para que siga siendo administrador.

#### Scenario: Administrador edita al primer administrador
- **GIVEN** un usuario con el rol Administrador
- **WHEN** edita el nombre del primer administrador y guarda
- **THEN** el cambio se guarda

#### Scenario: Superadministrador no puede eliminarlo
- **GIVEN** un usuario superadministrador
- **WHEN** abre la lista de usuarios
- **THEN** en la fila del primer administrador "Eliminar" y "Eliminar permanentemente" están deshabilitados

#### Scenario: Eliminación rechazada fuera de la interfaz
- **WHEN** un usuario con permiso de eliminar usuarios intenta eliminar al primer administrador sin pasar por el botón
- **THEN** la eliminación se rechaza y el usuario sigue existiendo

#### Scenario: Usuario que no es administrador
- **GIVEN** un usuario con permiso de editar usuarios pero sin el rol Administrador ni superadministrador
- **WHEN** abre la lista de usuarios
- **THEN** en la fila del primer administrador editar, restablecer contraseña y eliminar están deshabilitados

#### Scenario: Roles fijos al editarlo
- **GIVEN** un administrador editando al primer administrador
- **WHEN** abre el formulario
- **THEN** el campo de roles no se puede modificar
