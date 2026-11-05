# Proposal

## Why

Los roles Administrador y Vendedor traían por defecto más acceso del que la empresa quiere dar de entrada: borrado definitivo de registros, el módulo de Clientes completo y, en el Vendedor, también el de Ventas. Se busca un punto de partida más restrictivo, donde esos accesos se habiliten a mano desde la matriz de roles solo cuando hacen falta.

## What Changes

- **BREAKING** El rol Administrador deja de traer por defecto cualquier permiso del módulo Clientes.
- **BREAKING** El rol Administrador deja de traer por defecto cualquier permiso "Eliminar definitivamente" (boletos, ventas, viajes, métodos de pago, usuarios; en pagos ya no lo tenía).
- **BREAKING** El rol Vendedor deja de traer por defecto cualquier permiso de los módulos Clientes y Ventas, y el "Eliminar definitivamente" de Viajes.
- El resto de los permisos por defecto de los dos roles no cambia.
- La sincronización de permisos sigue siendo solo aditiva: en instalaciones existentes no se revoca nada; los roles conservan lo que ya tenían asignado hasta que se desmarque desde la matriz.
- Como consecuencia del control existente de la matriz (un editor solo puede asignar permisos que posee), un Administrador con los permisos por defecto ya no ve el módulo Clientes en la matriz: solo el Super Administrador puede habilitarlo.

## Capabilities

### New Capabilities
- `role-permissions`: permisos por defecto de los roles del sistema y comportamiento de la sincronización del catálogo de permisos.

### Modified Capabilities
<!-- Ninguna: no hay una spec previa de roles y permisos. -->

## Impact

- `app/Support/Permissions.php`: `defaultsFor()` y un helper para las acciones estándar sin borrado definitivo.
- Tests: `tests/Feature/RolesAndPermissionsTest.php` y `tests/Feature/ClientsTableTest.php`.
- Documentación: `docs/roles-y-permisos.md`, `docs/manual-funcional.md` y `docs/changelog.md` describen todavía los permisos anteriores.
- Instalaciones existentes: sin efecto automático; hay que quitar los permisos desde la matriz si se quiere el nuevo punto de partida.
