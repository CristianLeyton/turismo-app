# Spec Delta

## ADDED Requirements

### Requirement: Permiso "Editar precio fijo" sin asignar por defecto
El módulo Boletos de la matriz de roles SHALL ofrecer el permiso "Editar precio fijo". Ningún rol del sistema MUST traerlo por defecto, y la sincronización de permisos MUST crearlo sin asignarlo a ningún rol salvo al Super Administrador, que recibe todo el catálogo. El permiso solo actúa al editar un boleto, así que MUST NOT dar acceso a la edición por sí solo: hace falta además "Editar datos del pasajero/venta".

#### Scenario: Instalación existente
- **GIVEN** los roles Administrador y Vendedor con sus permisos actuales
- **WHEN** se corre la sincronización de permisos
- **THEN** existe el permiso "Editar precio fijo" y ninguno de los dos roles lo tiene

#### Scenario: Se ve en la matriz
- **WHEN** el Super Administrador edita un rol en la matriz
- **THEN** el módulo Boletos muestra "Editar precio fijo" y lo puede asignar

#### Scenario: Sin permiso de edición
- **GIVEN** un usuario con "Editar precio fijo" y sin "Editar datos del pasajero/venta"
- **WHEN** intenta abrir la edición de un boleto
- **THEN** no tiene acceso
