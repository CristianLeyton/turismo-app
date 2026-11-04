# Spec Delta

## ADDED Requirements

### Requirement: Gestión de líneas restringida por permisos
El acceso a la vista de líneas y a cada una de sus acciones MUST requerir el permiso correspondiente del módulo "Líneas" del catálogo de roles: ver listado, crear, editar y eliminar. Un usuario sin el permiso de ver listado MUST NOT ver la vista en la navegación ni poder abrirla. El Super Administrador MUST conservar acceso completo.

#### Scenario: Vendedor sin permisos de líneas
- **WHEN** un usuario con rol Vendedor intenta abrir la vista de líneas
- **THEN** el sistema le niega el acceso y la vista no aparece en su navegación

#### Scenario: Rol con permiso solo de ver listado
- **WHEN** un usuario cuyo rol tiene solo el permiso de ver listado de líneas abre la vista
- **THEN** ve el listado de líneas pero no puede crear, editar ni eliminar líneas

#### Scenario: Rol con permisos completos de líneas
- **WHEN** un usuario cuyo rol tiene los permisos de ver listado, crear, editar y eliminar líneas usa la vista
- **THEN** puede crear, editar y eliminar líneas como hoy

#### Scenario: Super Administrador
- **WHEN** el Super Administrador abre la vista de líneas
- **THEN** tiene acceso completo sin necesidad de asignación explícita

### Requirement: Permisos de líneas en la matriz de roles
El catálogo de roles MUST incluir el módulo "Líneas" con las acciones estándar, visible en la matriz de permisos. Esos permisos MUST NOT asignarse por defecto a los roles Administrador ni Vendedor, y la sincronización de permisos MUST NOT revocarlos una vez habilitados desde la matriz.

#### Scenario: Sincronización de permisos
- **WHEN** se ejecuta la sincronización de permisos
- **THEN** los permisos del módulo "Líneas" existen y los roles Administrador y Vendedor no los tienen asignados

#### Scenario: Habilitar desde la matriz
- **WHEN** se habilita "Líneas — Ver listado" a un rol desde la matriz y luego se vuelve a sincronizar
- **THEN** el rol conserva el permiso y sus usuarios pueden abrir la vista de líneas
