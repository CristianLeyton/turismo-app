# Proposal

## Why

La vista "Líneas" (cluster Colectivos) no tiene policy: Filament permite todo cuando no la encuentra, así que cualquier usuario del panel (incluido un Vendedor) puede ver, crear, editar y eliminar líneas, y además ve el cluster "Colectivos" en la navegación. El resto de los módulos de configuración (colectivos, rutas, horarios, asientos) ya se autoriza con permisos del catálogo; Líneas quedó afuera al crearse.

## What Changes

- Nuevo módulo `lineas` en el catálogo central de permisos, con las acciones estándar (ver listado, ver detalle, crear, editar, eliminar, restaurar, eliminar definitivamente), visible en la matriz de roles como "Líneas".
- La vista "Líneas" y sus acciones (crear, editar, eliminar) pasan a exigir los permisos `lineas.*` correspondientes.
- Los permisos `lineas.*` no se asignan por defecto a Administrador ni a Vendedor, igual que los demás módulos de configuración; el Super Administrador mantiene acceso por bypass. Se habilitan desde la matriz de roles.
- **BREAKING** (de comportamiento): usuarios no Super Administrador que hoy entran a "Líneas" dejan de verla hasta que su rol reciba los permisos.

## Capabilities

### New Capabilities
<!-- ninguna -->

### Modified Capabilities
- `linea-colectivo`: se agrega el requisito de que la gestión de líneas esté restringida por permisos del catálogo de roles.

## Impact

- `app/Support/Permissions.php` (catálogo).
- Nueva policy para el modelo `Linea` en `app/Policies/`.
- Tests: `tests/Feature/LineaResourceTest.php` y/o `tests/Feature/RolesAndPermissionsTest.php`.
- Despliegue: correr `php artisan permissions:sync` para crear los permisos nuevos en la base.
- Sin cambios en el formulario de colectivos: elegir la línea de un colectivo sigue dependiendo de `buses.update`.
