## ADDED Requirements

### Requirement: Permisos de reservas fuera de los roles por defecto
El catálogo SHALL incluir el módulo "Reservas" con "Ver reservas", "Confirmar reservas" y "Rechazar reservas", y el permiso "Configurar reservas de clientes" en Configuración. Ninguno MUST venir por defecto en los roles Administrador ni Vendedor; se asignan desde la matriz de roles.

#### Scenario: Instalación nueva
- **WHEN** se sincronizan los permisos y roles del sistema
- **THEN** los cuatro permisos existen y ni Administrador ni Vendedor los tienen

#### Scenario: Super Administrador
- **WHEN** el Super Administrador abre el panel
- **THEN** puede ver, confirmar y rechazar reservas y configurarlas
