# Design

## Context

- `LineaResource` (`app/Filament/Resources/Lineas/LineaResource.php`) no tiene policy; Filament autoriza todo cuando no encuentra una, y el modelo `App\Models\Linea` se resolvería por convención a `App\Policies\LineaPolicy`, que no existe.
- Los demás módulos de configuración usan el trait `App\Policies\Concerns\AuthorizesModule` con un nombre de módulo (p. ej. `BusPolicy` → `buses`).
- `App\Support\Permissions::catalog()` es la única fuente de permisos; `syncToDatabase()` crea permisos y aplica defaults solo de forma aditiva. `Gate::before` da bypass al Super Administrador.

## Goals / Non-Goals

**Goals:**
- Cerrar el acceso abierto a "Líneas" siguiendo exactamente el patrón de los módulos de configuración existentes.

**Non-Goals:**
- Cambiar quién puede asignar una línea a un colectivo (sigue gobernado por `buses.update`).
- Tocar la autorización del cluster "Colectivos" (Filament lo oculta solo si ninguno de sus recursos es accesible).
- Agregar soft deletes a líneas: `restore`/`force_delete` se incluyen solo por consistencia con las acciones estándar.

## Decisions

- **Módulo `lineas` con `standardActions()`**, label "Líneas", ubicado junto a `buses` en el catálogo. Alternativa descartada: reutilizar los permisos `buses.*` para Líneas; acopla dos pantallas que un rol podría querer separar y no se vería en la matriz.
- **`LineaPolicy` con `AuthorizesModule`** (`permissionModule(): 'lineas'`), descubierta por convención, sin registro manual en `AppServiceProvider`. Mantiene la paridad con `BusPolicy`.
- **Sin defaults para Administrador ni Vendedor**, igual que el resto de los módulos de configuración (ver comentario en `defaultsFor()`). Se habilita desde la matriz cuando haga falta.
- **Sin migración de datos**: los permisos se crean con `php artisan permissions:sync`, como se hizo con permisos agregados anteriormente (p. ej. `tickets.vender_dia_sin_servicio`).

## Risks / Trade-offs

- [Un Administrador que hoy gestiona líneas pierde el acceso al desplegar] → avisar en el despliegue y habilitar `lineas.*` a su rol desde la matriz si corresponde.
- [Olvidar `permissions:sync` en el despliegue] → sin los permisos en la base, nadie salvo el Super Administrador entra; es un fallo seguro (cerrado), y se corrige corriendo el comando.
- [Tests que cuentan el total del catálogo] → `test_el_catalogo_se_sincroniza_de_forma_idempotente` usa `count(Permissions::all())`, por lo que se ajusta solo.

## Migration Plan

1. Desplegar el código.
2. Correr `php artisan permissions:sync`.
3. Si algún rol necesita gestionar líneas, habilitar los permisos de "Líneas" desde la matriz de roles.

Rollback: revertir el código; los permisos `lineas.*` que queden en la base no tienen efecto.
