# role-permissions Specification

## Purpose
Define qué permisos traen por defecto los roles del sistema (Super Administrador, Administrador y Vendedor) y cómo la sincronización del catálogo los aplica sin quitar lo habilitado a mano.

## Requirements

### Requirement: Permisos por defecto del Administrador
El rol Administrador SHALL traer por defecto acceso a boletos (ver, vender, reprogramar, eliminar y restaurar), ventas, viajes, pagos, métodos de pago, usuarios, roles, historial de boletos y configuración general. MUST NOT traer por defecto ningún permiso del módulo Clientes ni ningún permiso "Eliminar definitivamente".

#### Scenario: Administrador sin clientes
- **WHEN** se crea un usuario con el rol Administrador en una instalación nueva
- **THEN** no puede ver, crear, editar, banear, eliminar ni restaurar clientes

#### Scenario: Administrador sin borrado definitivo
- **WHEN** un Administrador con los permisos por defecto intenta eliminar definitivamente un boleto, una venta, un viaje, un pago, un método de pago o un usuario
- **THEN** la acción no está disponible

#### Scenario: Administrador conserva el resto
- **WHEN** un Administrador con los permisos por defecto abre usuarios, pagos, roles o eliminar y restaurar boletos
- **THEN** tiene acceso como antes

### Requirement: Permisos por defecto del Vendedor
El rol Vendedor SHALL traer por defecto ver y vender boletos y acceso a viajes. MUST NOT traer por defecto ningún permiso de los módulos Clientes ni Ventas, ni ningún permiso "Eliminar definitivamente".

#### Scenario: Vendedor sin clientes ni ventas
- **WHEN** se crea un usuario con el rol Vendedor en una instalación nueva
- **THEN** no tiene ningún permiso de Clientes ni de Ventas

#### Scenario: Vendedor sigue vendiendo
- **WHEN** un Vendedor con los permisos por defecto abre la venta de boletos o el listado de viajes
- **THEN** puede vender y ver los viajes

### Requirement: Sincronización aditiva de permisos
La sincronización del catálogo de permisos SHALL crear los permisos y roles del sistema que falten y agregarles sus permisos por defecto. MUST NOT quitar permisos ya asignados a ningún rol, aunque ya no estén entre los permisos por defecto.

#### Scenario: Instalación existente
- **GIVEN** un rol Administrador que ya tenía permisos de Clientes asignados
- **WHEN** se corre la sincronización de permisos
- **THEN** el rol conserva los permisos de Clientes

### Requirement: Solo se asignan permisos propios desde la matriz
En la matriz de roles un editor SHALL ver y asignar solo los permisos que él mismo posee. El Super Administrador MUST poder asignar cualquier permiso del catálogo.

#### Scenario: Administrador sin permisos de clientes
- **WHEN** un Administrador con los permisos por defecto abre la matriz para crear un rol
- **THEN** no ve el módulo Clientes

#### Scenario: Super Administrador habilita clientes
- **WHEN** el Super Administrador edita el rol Vendedor en la matriz
- **THEN** puede asignarle permisos de Clientes
