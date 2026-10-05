# Roles y permisos

> Sistema de autogestión de roles y permisos basado en [spatie/laravel-permission](https://spatie.be/docs/laravel-permission/v8/introduction) y el catálogo central `App\Support\Permissions`.

## Cómo funciona

La autorización se resuelve en tres capas:

1. **Catálogo** — [app/Support/Permissions.php](../app/Support/Permissions.php): única fuente de verdad. Define los módulos del sistema, las acciones de cada uno y los permisos por defecto de los tres roles del sistema.
2. **Roles** — tabla `roles` + pivote `model_has_roles` (spatie). Cada usuario puede tener uno o más roles.
3. **Policies y checks de UI** — las policies de `app/Policies` consultan `$user->can('modulo.accion')`; los componentes de Filament usan `->visible(fn () => auth()->user()->can(...))`.

### Roles del sistema

| Rol | Qué puede hacer |
|---|---|
| **Super Administrador** | Todo (bypass total vía `Gate::before`). Reservado para el usuario fundador (id 1). |
| **Administrador** | Ventas, boletos (incl. reprogramar/eliminar), clientes (incl. banear), pagos, usuarios, roles, historial y configuración. No accede a módulos de configuración de flota (colectivos, rutas, horarios, asientos) ni a `payments.force_delete` — igual que el viejo `is_admin`. |
| **Vendedor** | Vender boletos, ver/editar clientes, ver ventas y viajes. Igual que cualquier usuario sin `is_admin` antes de la migración. |

Cualquier **rol custom** (ej. "Supervisor") se crea desde la UI y se le asignan permisos con la matriz de checkboxes.

### Política de borrado de roles

- Los **roles del sistema** (Super Administrador, Administrador, Vendedor) **no se pueden borrar** ni renombrar desde la UI.
- Un rol **con usuarios asignados no se puede eliminar**: el botón muestra un aviso con la cantidad de usuarios y el borrado se bloquea en el servidor. Esto evita que alguien pierda acceso al sistema por un borrado accidental.
- Un rol **sin usuarios** sí se puede eliminar (limpieza de roles creados por error).
- Para eliminar un rol con usuarios: primero reasignalos a otro rol desde *Usuarios → editar → Roles*, y después eliminá el rol.

### Nombres de permisos

Formato: `{modulo}.{accion}`, ejemplos: `tickets.create`, `users.delete`, `payments.force_delete`, `settings.manage`, `roles.assign`.

Acciones estándar: `view_any`, `view`, `create`, `update`, `delete`, `restore`, `force_delete`. Acciones especiales: `tickets.reschedule`, `clients.ban`, `settings.manage`, `roles.assign`.

## Cómo se usa

### Crear un rol nuevo (sin tocar código)

`Admin → Roles y permisos → Nuevo rol` → nombre + checkboxes de la matriz → guardar. Asignarlo en `Admin → Usuarios → editar usuario → Roles`.

### Agregar un permiso nuevo al sistema

1. Agregalo al catálogo en `app/Support/Permissions.php` (al módulo existente o a uno nuevo).
2. Corré `php artisan permissions:sync`.
3. Asignalo desde la matriz al rol que necesite.
4. Autorizá con `$user->can('modulo.accion')` en la policy o el componente.

### Asignar roles a usuarios

`Admin → Usuarios → editar` → campo **Roles** (multi-select). Requiere el permiso `roles.assign`.

El flag `is_admin` se recalcula automáticamente:
- Si tiene rol "Administrador" o "Super Administrador" → `is_admin = 1`.
- Si no tiene roles → se le asigna "Vendedor" (todo usuario puede vender, como antes).

## Compatibilidad con el flag `is_admin`

`is_admin` **sigue existiendo** y queda sincronizada automáticamente en ambas direcciones:

- **Roles → is_admin**: `User::syncIsAdminFlag()` (se ejecuta al guardar usuarios desde la UI).
- **is_admin → roles**: `App\Observers\UserObserver` — si algún código/script crea un usuario con `'is_admin' => true`, se le asigna el rol Administrador.

Esto permite hacer **rollback** del código a la versión anterior sin perder datos: el sistema viejo seguiría funcionando con el boolean.

Eliminación futura: en una fase posterior se puede migrar todo el código a permisos y droppear la columna.

## Migración de datos

La migración `2026_10_05_123000_seed_roles_permissions_and_assign_from_is_admin` crea roles/permisos y asigna:

- Usuario **id 1** → Super Administrador (+ Administrador)
- `is_admin = true` → Administrador
- Resto → Vendedor

Es idempotente y no revoca permisos de roles custom.

## Comandos

```bash
php artisan permissions:sync                    # Aplica novedades del catálogo
php artisan permissions:sync --reconcile-users  # Además recalcula is_admin de todos
php artisan permission:cache-reset              # Limpia la caché de permisos
```

## Tests

`tests/Feature/RolesAndPermissionsTest.php` cubre:
- Paridad exacta de los roles Vendedor y Administrador (incl. lo que NO pueden).
- Bypass del Super Administrador.
- Sincronización bidireccional `is_admin` ⇄ roles.
- Idempotencia del catálogo y el comando `permissions:sync`.
- Roles custom con permisos parciales.
- Protecciones del recurso de roles (roles del sistema no borrables).
