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

Acciones estándar: `view_any`, `view`, `create`, `update`, `delete`, `restore`, `force_delete`. Acciones especiales: `tickets.vender_sin_fecha`, `tickets.vender_pasado_limite`, `tickets.reschedule`, `clients.ban`, `settings.manage`, `roles.assign`.

### Venta sin fecha (`tickets.vender_sin_fecha`)

Permiso del módulo Boletos que habilita emitir pasajes **sin fecha ni horario**: el vendedor marca "Ida sin fecha" y/o "Vuelta sin fecha" en el wizard de venta, elige colectivo, origen y destino (origen/destino siempre filtrados por las rutas del colectivo elegido) y el boleto se crea con `trip_id`, `seat_id` NULL y el `bus_id` elegido (pendiente). La fecha, el horario y el asiento se asignan después desde *Reprogramar boleto* (permiso `tickets.reschedule`), que para boletos pendientes exige elegir asiento, sólo acepta horarios de rutas del colectivo guardado en el boleto, y registra la auditoría en `ticket_date_changes`.

En una venta de ida y vuelta **sin fecha**, el boleto de ida ofrece el alcance *Ida y vuelta* y asigna fecha, horario y asiento a **los dos tramos en un solo paso** (la vuelta se identifica por el boleto hermano `is_return_leg`, aunque todavía no tenga viaje ni `return_trip_id`). El alcance *Solo este tramo* sigue disponible para asignar un tramo por vez. El formulario acota las fechas cruzadas (la vuelta no puede salir antes que la ida) y el service vuelve a validar el orden real en el servidor.

- **Ningún rol del sistema lo trae por defecto** (ni Administrador ni Vendedor); se habilita por rol desde la matriz de la UI. El Super Administrador lo tiene por bypass total (no cuenta como asignación).
- **Feature flag de rollout:** los switches "Ida/Vuelta sin fecha" permanecen ocultos mientras el permiso exista pero **ningún rol** lo tenga asignado en la matriz (`Permissions::permissionGrantedToAnyRole()`, cache 60s). Basta asignarlo a un rol para que aparezcan para quien tenga el permiso.
- **El colectivo es obligatorio en toda venta** (con o sin fecha): `bus_id` es requerido en el wizard y `CreateTicket` lo defiende server-side con `halt()`.
- Server-side: `CreateTicket` rechaza con `halt()` la venta sin fecha de quien no tenga el permiso, aunque fuerce los switches por Livewire.
- En ventas mixtas (ida con fecha + vuelta sin fecha, o viceversa) la vuelta pendiente se marca con `is_return_leg = true` y precio 0; el precio viaja en el boleto de ida.
- Los PDFs y pantallas muestran la leyenda "FECHA Y HORARIO A CONFIRMAR" en boletos pendientes.
- Tests: `tests/Feature/SellWithoutDateTest.php`.

### Venta fuera de término (`tickets.vender_pasado_limite`)

Permiso del módulo Boletos que habilita **emitir pasajes de un viaje que YA SALIÓ** después del plazo configurado. Pasado ese plazo, quien no tenga el permiso no puede crear el boleto.

- **Plazo configurable:** *Configuración → Venta fuera de término → Horas de venta después de la salida* (clave `tickets.venta_limite_horas`, default **5**). `0` desactiva el límite. Lo lee `Setting::ventaLimiteHoras()`.
- **Desde cuándo corre:** desde la salida del colectivo en la **parada donde sube el pasajero** del tramo (origen en la ida, destino en la vuelta), no desde la salida del ramal. El helper es `Trip::departureDateTimeForStop()`.
- **Ningún rol del sistema lo trae por defecto** (ni Administrador ni Vendedor); se habilita por rol desde la matriz. El Super Administrador lo tiene por bypass total (no cuenta como asignación).
- **Las reglas se centralizan en `App\Support\SaleCutoff`** (única fuente de verdad), y aplican en tres puntos:
  1. **Paso 1 del wizard de venta** (`TicketForm`, `afterValidation` del step, sólo en `CreateTicket`): al intentar avanzar se evalúa ida y vuelta; si el viaje ya salió fuera del plazo o es de un día anterior, se manda la notificación "Venta fuera de término" y el wizard **no avanza** (no se despacha `next-wizard-step`).
  2. **Campos seleccionables:** *Fecha de ida* con `minDate(SaleCutoff::minSelectableDate())` (hoy sin permiso, un año atrás con permiso) y los selects de *Horario de ida* y *Horario de vuelta* no listan horarios bloqueados (`SaleCutoff::blockReasonForSchedule()`).
  3. **Guard final al crear** (`CreateTicket::assertDepartureWithinLimit()`): verifica antes de crear (antes del `try`, para que el `halt()` no lo capture el catch genérico) y cubre los cuatro modos de venta: normal, diferido con las dos fechas y los mixtos con la ida o la vuelta pendientes. Los boletos **sin fecha** no se ven afectados (todavía no tienen viaje); el control aplica al asignarles fecha vía reprogramación, que exige `tickets.reschedule`.
  - La **edición** de boletos existentes (`EditTicket`, mismo `TicketForm`) no estálimitada por estas reglas: el guard del paso 1 sólo se activa cuando el componente es `CreateTicket`.
- Tests: `tests/Feature/TicketSaleCutoffTest.php` (reglas del guard final) y `tests/Feature/TicketSaleCutoffWizardTest.php` (reglas del soporte, `minDate`, y avance/no-avance del paso 1 vía Livewire).

### Mapeo recurso Filament → módulo

Filament autoriza cada resource con la **policy de su modelo**. El mapeo vigente:

| Recurso | Modelo | Módulo que lo autoriza |
|---|---|---|
| Boletos | `Ticket` | `tickets.*` |
| Clientes | `Clients` | `clients.*` (policy registrada explícitamente: el modelo es plural y el autodescubrimiento no encuentra `ClientsPolicy`) |
| **Ventas** | `User` | `sales.*` — el recurso lista vendedores, así que define `canViewAny()` propio; sin él la página exigía `users.view_any` |
| Viajes | `Trip` | `trips.*` |
| Pagos | `Payment` | `payments.*` |
| Métodos de pago | `PaymentMethod` | `payment_methods.*` |
| Usuarios | `User` | `users.*` |
| Roles y permisos | `Role` | `roles.*` (checks propios en el resource) |
| Historial | `Ticket` | `ticket_audit.*` (overrides propios) |
| Flota/ubicaciones | `Bus`, `BusLayoutArea`, `Location`, `Route`, `RouteStop`, `Schedule`, `Seat` | sus módulos `*` vía `AuthorizesModule` |

Ojo al agregar acciones custom dentro de un recurso: usá el módulo correspondiente, no el del modelo del recurso (ej. "Registrar pago" en Ventas exige `payments.create` porque crea un `Payment`).

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

Es idempotente y **sólo agrega**: nunca revoca permisos, ni de roles custom ni de los roles del sistema. Esto es a propósito, porque los permisos "habilitables por bandera" (`tickets.vender_sin_fecha`, `tickets.vender_pasado_limite`) no están en los defaults y se otorgan a mano desde la matriz: un sync destructivo los borraría.

## Comandos

```bash
php artisan permissions:sync                    # Aplica novedades del catálogo
php artisan permissions:sync --reconcile-users  # Además recalcula is_admin de todos
php artisan permission:cache-reset              # Limpia la caché de permisos
```

## Deploy de la venta sin fecha

La funcionalidad exige dos migraciones nuevas y la sincronización de permisos:

```bash
php artisan migrate                # 2026_10_06_000001 (tickets.trip_id nullable)
                                   # 2026_10_06_000002 (tickets.is_return_leg + backfill)
                                   # 2026_10_06_000003 (tickets.bus_id FK nullable)
php artisan permissions:sync       # Crea tickets.vender_sin_fecha y
                                   # tickets.vender_pasado_limite (idempotente)
```

Luego habilitá el permiso para los roles que puedan vender sin fecha, desde *Roles y permisos → matriz*.

## Deploy del límite de venta

No requiere migraciones: el plazo vive en `settings` (default 5) y el permiso en el catálogo.

```bash
php artisan permissions:sync       # Crea tickets.vender_pasado_limite (idempotente)
```

Ajustá las horas en *Configuración → Venta fuera de término*. Para que alguien pueda vender pasado el plazo, habilitale el permiso desde *Roles y permisos → matriz* (nadie lo trae por defecto).

## Tests

`tests/Feature/SellWithoutDateTest.php` cubre el permiso `tickets.vender_sin_fecha`, la venta pendiente (sólo ida y modo mixto), el guard server-side de permiso y de colectivo obligatorio, el feature flag de visibilidad de switches, el rechazo de horarios de otro colectivo al asignar fecha, la asignación posterior con `tickets.reschedule` y la regresión de la venta con fecha.

`tests/Feature/TicketSaleCutoffTest.php` cubre el permiso `tickets.vender_pasado_limite`, el default de 5 horas, el bloqueo pasadas las horas, la venta dentro del plazo, el bypass con el permiso, las horas configurables, `0` = sin límite y que el reloj corre desde la parada de subida.

`tests/Feature/TicketSaleCutoffWizardTest.php` cubre las reglas de `App\Support\SaleCutoff` (fecha anterior, ventana de horas, permiso, `0` = sin límite), el `minDate` de la Fecha de ida sin y con permiso, y el avance (o no) del paso 1 del wizard vía Livewire: sin permiso no se despacha `next-wizard-step` con un horario de fecha anterior; con horario válido o con el permiso sí avanza.

`tests/Feature/RolesAndPermissionsTest.php` cubre:
- Paridad exacta de los roles Vendedor y Administrador (incl. lo que NO pueden).
- Bypass del Super Administrador.
- Sincronización bidireccional `is_admin` ⇄ roles.
- Idempotencia del catálogo y el comando `permissions:sync`.
- Roles custom con permisos parciales.
- Protecciones del recurso de roles (roles del sistema no borrables).
- Mapeo modelo → policy de cada resource (regresiones de Ventas y Clientes).
- Botón "Registrar pago" en Ventas visible sólo con `payments.create`.
