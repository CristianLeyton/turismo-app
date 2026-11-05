# Cómo desplegar a producción

> Procedimiento para llevar los cambios del panel a producción (Hostinger:
> `/home/u791829980/domains/expressosofiaturismo.com.ar/public_html/panel`).
> Es una checklist, no una guía de git: los pasos están en el orden en que hay
> que ejecutarlos y cada uno se puede correr más de una vez sin romper nada.

## 1. Rama que usa producción

El repo tiene varias ramas (`main`, `desarrollo`, `desarrollo-finanzas`, ...).
Antes de tocar el servidor, confirmá desde qué rama actualiza:

- Si el servidor trabaja sobre **`desarrollo`**: alcanza con pushear esa rama.
- Si el servidor trabaja sobre **`main`**: hay que mergear `desarrollo` en
  `main` y pushear `main`. Ojo: `main` puede estar desactualizado respecto de
  `desarrollo` (revisar con `git log --oneline main..desarrollo`).

## 2. Antes de salir (en local)

```bash
php artisan test        # suite completa en verde
vendor/bin/pint --test  # estilo sin novedades propias
git push
```

## 3. En producción (orden exacto)

Conectado por SSH al hosting:

```bash
cd /home/u791829980/domains/expressosofiaturismo.com.ar/public_html/panel

# 3.1 Backup de la base de datos ANTES de migrar (panel de Hostinger o:)
mysqldump ... > backup_antes_de_deploy.sql

# 3.2 Código
git pull                # o subir los archivos por SFTP si no hay git

# 3.3 Migraciones (imprescindible si el diff incluye database/migrations)
#     Antes, leer la sección 7 (avisos pendientes para el próximo deploy).

php artisan migrate --force

o 

/opt/alt/php84/usr/bin/php artisan migrate

# 3.4 Permisos (imprescindible si cambió app/Support/Permissions.php)
php artisan permissions:sync

o

/opt/alt/php84/usr/bin/php artisan permissions:sync

# 3.5 Assets del frontend (public/build NO viaja en git: se compila en el server)
npm ci && npm run build

# 3.6 Cachés
php artisan optimize:clear && php artisan optimize
```

El cron de `app:cleanup-expired-reservations` ya está configurado en el
hosting y no cambia con los deploys (ver README).

## 4. Qué revisar según qué cambió en el diff

| Qué cambió en el commit | Paso obligatorio |
| --- | --- |
| `database/migrations/*` | `php artisan migrate --force` |
| `app/Support/Permissions.php` | `php artisan permissions:sync` (solo agrega, no revoca) |
| `resources/**` o `app/**` (clases nuevas en blades/tema) | `npm ci && npm run build` (tema Filament con Tailwind) |
| `composer.json` / `composer.lock` | `composer install --no-dev --optimize-autoloader` |
| `package.json` / `package-lock.json` | `npm ci && npm run build` |
| `.env.example` | Comparar con el `.env` del servidor y agregar las variables nuevas |
| `routes/*.php` o `bootstrap/app.php` | `php artisan route:clear` (lo incluye `optimize:clear` del paso 3.6). Si las rutas quedan cacheadas, el servidor sigue con las viejas: por ejemplo, sin el `auth` nuevo |

Si no cambió nada de la tabla, ese paso se puede saltear.

## 5. Post-deploy: verificación

- [ ] Entrar al panel con una cuenta normal (no solo superusuario).
- [ ] Abrir el módulo que cambió y probar el flujo de punta a punta.
- [ ] Revisar `storage/logs/laravel.log` (no debería haber errores nuevos).
- [ ] Si se agregó una feature "por bandera" (permiso que ningún rol trae por
      defecto, p. ej. `tickets.edit`): otorgarla desde la matriz de Roles y
      configurar su setting si tiene (p. ej. "Pedir contraseña al editar
      boletos" en Configuración).

## 6. Rollback

- Código: `git checkout <commit-anterior>` en el servidor (o subir por SFTP la
  versión previa).
- Base de datos: `php artisan migrate:rollback --step=N` si la migración lo
  permite; si no, restaurar el backup del paso 3.1.
- Cachés: `php artisan optimize:clear` después de cualquier cambio de código.

## 7. Avisos pendientes para el próximo deploy

> Borrar cada aviso una vez que ese deploy se hizo y salió bien.

### Emisión atómica: un solo boleto vigente por asiento (2026-11-03)

La migración `2026_11_02_194728_add_active_seat_guard_to_tickets_table` hace
que la base rechace dos boletos vigentes en el mismo viaje y asiento. Antes de
cambiar nada, la migración busca los duplicados que ya existan en producción:

- **Si no hay duplicados**, migra normalmente. No hay que hacer nada más.
- **Si hay duplicados**, `migrate` falla **sin modificar la tabla** y muestra un
  mensaje como este:

  ```
  No se puede agregar la restricción de un solo boleto vigente por asiento:
  hay boletos vigentes duplicados. ... Conflictos: viaje 704, asiento (seat_id) 304: boletos 27270, 27280; ...
  ```

  Qué hacer:
  1. Copiar la lista de conflictos de la salida de `migrate`.
  2. En el panel, para cada grupo, dar de baja o reprogramar uno de los
     boletos. El `seat_id` es el id interno del asiento, no el número que se
     ve en pantalla. Para ubicarlo, abrir los boletos por su número.
  3. Volver a correr `php artisan migrate --force`.

- **Mientras la migración no pase, la app funciona igual.** La venta ya es todo
  o nada con el código nuevo. Lo único que falta es la restricción en la base.
- **Rollback:** `php artisan migrate:rollback --step=1` vuelve al índice
  anterior.

### Ciclo de vida de las reservas (2026-11-03)

Cambia el selector de asientos (`resources/views/tickets/seat-selector.blade.php`)
y la API de reservas. No tiene migraciones.

- **Obligatorio:** `npm ci && npm run build` (paso 3.5). Sin recompilar, el
  navegador sigue con el selector viejo y la renovación por actividad no
  funciona.
- Se borraron `app/Console/Kernel.php` y `config/schedule.php`, que Laravel 12
  no cargaba. **El cron del servidor que corre
  `app:cleanup-expired-reservations` sigue igual**: no hay que tocarlo.
- Qué probar después: abrir "Vender" en dos pestañas con ventas en viajes
  distintos; cancelar una no debe borrar los asientos de la otra.

### Marca del tramo de vuelta (2026-11-03)

Una venta de ida y vuelta a precio 0 (cortesía o canje) podía dejar la ida
marcada como vuelta. El código nuevo identifica la vuelta solo por la marca
`is_return_leg`, y la migración
`2026_11_03_015112_fix_return_leg_marks_on_tickets_table` corrige los datos:

1. Marca como vuelta los boletos **dados de baja** con la convención vieja
   (ida y vuelta, sin viaje de vuelta y precio 0). La migración anterior solo
   marcó los vigentes.
2. En las ventas donde un pasajero tiene **exactamente dos** boletos de ida y
   vuelta, los dos marcados como vuelta, el creado primero vuelve a ser la ida.

**Antes de migrar** (después del backup del paso 3.1), correr estas consultas
en la base de producción y guardar el resultado:

```sql
-- A. Boletos con la convención vieja, sin marca y sin vuelta hermana
--    (no son idas a precio 0: la migración los marca como vuelta)
SELECT COUNT(*) FROM tickets t
WHERE t.is_round_trip = 1 AND t.return_trip_id IS NULL AND t.price = 0
  AND t.is_return_leg = 0
  AND NOT EXISTS (
    SELECT 1 FROM tickets v
    WHERE v.sale_id = t.sale_id AND v.passenger_id = t.passenger_id
      AND v.id <> t.id AND v.is_return_leg = 1
  );

-- B. Ventas/pasajeros de ida y vuelta sin ninguna ida vigente
SELECT sale_id, passenger_id, COUNT(*) AS boletos,
       GROUP_CONCAT(id ORDER BY id) AS ids
FROM tickets
WHERE is_round_trip = 1 AND deleted_at IS NULL
GROUP BY sale_id, passenger_id
HAVING SUM(is_return_leg = 0) = 0 AND COUNT(*) >= 2;
```

**Después de migrar**, repetirlas:

- **A** tiene que dar 0.
- **B** tiene que quedar vacía. Si quedan filas, la migración no las tocó
  porque el pasajero tiene más de dos boletos en la venta (o boletos de baja
  en el grupo). Revisarlas a mano: abrir los boletos por su número y, en la
  base, poner `is_return_leg = 0` en el que es la ida (el de origen y destino
  de la venta original).

- **Sin `npm run build`**: no cambian vistas ni clases de Tailwind.
- **Qué probar después:** vender una ida y vuelta a precio 0 con la vuelta sin
  fecha; en el detalle, la ida tiene que figurar como "Ida" y su precio tiene
  que ser editable.
- **Rollback:** revertir el código sin revertir la migración (`down()` no
  cambia datos). Con el código viejo, las idas a precio 0 se vuelven a mostrar
  como vuelta, que es el comportamiento anterior.

### Login de clientes con Filament (2026-11-03)

El panel de clientes (`/clientes`) pasa a usar el login de Filament con un
guard propio (`client`) sobre la tabla `clients`. El de administración no
cambia.

- **Obligatorio:**
  - `php artisan migrate --force`. Corre dos migraciones:
    - `2026_11_03_021547_add_remember_token_to_clients_table`: columna para
      "Recordarme".
    - `2026_11_03_021634_set_initial_password_for_clients_without_password`:
      los clientes sin contraseña (alta rápida desde la venta) reciben la
      inicial, que es su DNI, y tienen que cambiarla al entrar. Antes no podían
      ingresar.
  - `npm ci && npm run build` (paso 3.5): la página de login es nueva.
  - Cachés (paso 3.6): cambia `config/auth.php` y hay rutas nuevas.
- **Avisar a los clientes:**
  - La dirección de ingreso pasa a ser `/clientes/login`. `/clientes/ingresar`
    redirige sola, así que los links viejos siguen andando.
  - Los clientes que tenían la sesión abierta tienen que volver a ingresar, con
    el mismo DNI y la misma contraseña.
- **Qué probar después:**
  - Un cliente entra con DNI y contraseña. Con una contraseña errónea ve "El DNI
    o la contraseña son incorrectos."
  - Un cliente con la contraseña inicial es llevado a elegir una nueva.
  - En "Mis boletos", elegir fecha y asiento muestra el selector. Bajar el PDF
    funciona.
  - El panel muestra el menú de usuario (arriba a la derecha) con "Mi perfil",
    el tema y "Salir". En "Mi perfil" (`/clientes/perfil`, ahora el perfil de
    Filament) se editan nombre, apellido, teléfono y contraseña; el DNI no.
  - Con un vendedor logueado en `/admin` en otra pestaña, "Salir" del cliente
    no cierra la sesión del vendedor. Al revés sí: cerrar sesión en `/admin`
    cierra también la del cliente en ese navegador.
- **Va junto con "API de reservas segura"** (aviso siguiente): esa API ahora
  exige la sesión de cliente que crea este cambio.
- **Rollback:** revertir el código y correr
  `php artisan migrate:rollback --step=2`. Esto elimina `remember_token`; la
  migración de contraseñas no revierte datos, que siguen siendo válidos para el
  ingreso anterior.

### API de reservas segura (2026-11-03)

La API del selector de asientos (`/api/seat-reservations/*`) exige estar
logueado como vendedor (`/admin`) o como cliente (`/clientes`), y opera solo
sobre la sesión de quien hace el pedido. Se eliminó el endpoint `cleanup`: la
limpieza de reservas vencidas sigue a cargo del cron con
`app:cleanup-expired-reservations`, que no cambia.

- **Desplegar junto con "Login de clientes con Filament"** (aviso anterior).
  Sin ese cambio, los clientes no tienen sesión y su selector de asientos
  dejaría de funcionar.
- **Sin migraciones ni `npm run build`**: el JavaScript del selector está en el
  blade. Alcanza con limpiar cachés (paso 3.6), que incluye las rutas.
- **Pestañas abiertas durante el deploy:** siguen funcionando, porque el
  servidor ignora el identificador de sesión que mandaba el JavaScript viejo.
  Recargar la página evita cualquier caso raro.
- **Qué probar después:**
  - Sin login, `POST /api/seat-reservations/status` responde 401.
  - En "Vender", elegir, cambiar y deseleccionar asientos crea y libera las
    reservas, sin errores en la consola del navegador.
  - En el panel de clientes, "Elegir fecha" de un boleto sin fecha muestra el
    selector y reserva el asiento.
- **Rollback:** revertir el código y limpiar cachés.

### Permisos de "Líneas" (2026-11-03)

La vista Colectivos > Líneas pasa a exigir permisos del módulo nuevo `lineas`
(antes cualquier usuario del panel podía verla y editarla). No tiene
migraciones.

- **Obligatorio:** `php artisan permissions:sync` (paso 3.4). Sin él, solo el
  Super Administrador entra a Líneas.
- **Después del sync:** en Roles, habilitar "Líneas" a los roles que la usen.
  Por defecto ni Administrador ni Vendedor la traen, igual que el resto de los
  módulos de configuración.
- **Qué probar después:** con un Vendedor, Líneas no aparece en el menú
  Colectivos y `/admin/buses/lineas` da 403.
- **Rollback:** revertir el código. Los permisos `lineas.*` que queden en la
  base no tienen efecto.

### Editor de rutas: recorrido y horarios en una sola pantalla (2026-11-04)

Cada ruta se edita en una pantalla con pestañas Datos, Recorrido y Horarios.
Desaparecen los menús sueltos "Paradas" y "Horarios" del cluster Destinos. El
orden de las paradas se guarda recién al tocar "Confirmar cambios". Se bloquea
eliminar horarios y rutas con viajes pendientes, y destinos usados en rutas.
No tiene migraciones ni cambios de permisos.

**Antes de desplegar**, después del backup del paso 3.1, correr estas
consultas en la base de producción:

```sql
-- A. Roles a medida que pierden acceso: tienen permisos de paradas u horarios
--    pero no los de rutas que exige la pantalla de la ruta.
SELECT r.name FROM roles r
JOIN role_has_permissions rp ON rp.role_id = r.id
JOIN permissions p ON p.id = rp.permission_id
WHERE r.name <> 'Super Administrador'
GROUP BY r.id, r.name
HAVING SUM(p.name LIKE 'route_stops.%' OR p.name LIKE 'schedules.%') > 0
   AND (SUM(p.name = 'routes.view_any') = 0 OR SUM(p.name = 'routes.update') = 0);

-- B. Paradas con órdenes desfasados por el error viejo al reordenar.
SELECT route_id, id, stop_order FROM route_stops
WHERE stop_order > 1000 ORDER BY route_id, stop_order;

-- C. Paradas editadas en los últimos 90 días (por el bug de la hora mal
--    mostrada en el formulario de edición).
SELECT rs.id, r.name AS ruta, l.name AS parada,
       rs.departure_offset_minutes, rs.arrival_offset_minutes, rs.updated_at
FROM route_stops rs
JOIN routes r ON r.id = rs.route_id
JOIN locations l ON l.id = rs.location_id
WHERE rs.deleted_at IS NULL AND rs.updated_at >= NOW() - INTERVAL 90 DAY
ORDER BY rs.updated_at DESC;
```

- **A:** si devuelve roles, habilitarles "Rutas: Ver listado" y "Rutas:
  Editar" en la matriz de Roles después del deploy. Si no, pierden el acceso a
  paradas y horarios.
- **B:** si devuelve filas, la ruta funciona igual, porque el orden relativo se
  mantiene y la pantalla numera 1, 2, 3… Se normaliza sola la próxima vez que
  alguien reordene y confirme esa ruta.
- **C:** el formulario viejo mostraba mal la hora al editar una parada. Si
  alguien guardó sin tocarla, pudo quedar una hora incorrecta. Después del
  deploy, abrir el Recorrido de esas rutas y verificar que las horas tengan
  sentido.

- **Obligatorio:**
  - `npm ci && npm run build` (paso 3.5): hay una vista nueva
    (`resources/views/filament/resources/route-stops/reordering-header.blade.php`)
    con clases Tailwind.
  - Cachés (paso 3.6).
- **Avisar a quienes configuran rutas:**
  - Paradas y horarios se editan desde Destinos > Rutas > (lápiz de la ruta),
    en las pestañas Recorrido y Horarios.
  - Para cambiar el orden de las paradas: "Cambiar orden", arrastrar y
    "Confirmar cambios". Si se sale sin confirmar, no se guarda nada.
- **Qué probar después:**
  - Abrir una ruta y revisar las tres pestañas.
  - En Recorrido, editar una parada: la hora del formulario coincide con la de
    la tabla.
  - Arrastrar una parada y "Descartar cambios": vuelve al orden original.
  - Tocar eliminar en una ruta con boletos vendidos: aparece "No se puede
    eliminar la ruta". Lo mismo con un destino usado en una ruta.
- **Rollback:** revertir el código y limpiar cachés. Los datos no cambian de
  forma.

### Exportaciones de viajes con login (2026-11-04)

`/trips/{id}/pdf` y `/trips/{id}/excel` dejaban bajar sin sesión el listado de
pasajeros de cualquier viaje. Ahora exigen sesión del panel y el permiso
`trips.view`. Las URLs no cambian. No tiene migraciones ni cambios de permisos.

- **Obligatorio:** limpiar la caché de rutas. Si no, producción sigue sirviendo
  las rutas viejas, sin protección:

  ```bash
  php artisan route:clear

  o

  /opt/alt/php84/usr/bin/php artisan route:clear
  ```

  El paso 3.6 (`optimize:clear && optimize`) también la limpia y la regenera.
  Con correrlo alcanza, pero no hay que saltearlo.
- **Sin `npm run build`**: no cambian vistas.
- **Avisar:** quien tenga guardado un enlace `/trips/{id}/pdf|excel` (por
  ejemplo, un chofer) ahora tiene que iniciar sesión en el panel con un usuario
  que tenga "Viajes: Ver". Sin cuenta, el enlace ya no le sirve: tiene que
  pedirle el PDF a alguien del panel.
- **Qué probar después:**
  - En una ventana privada, sin login, abrir `/trips/<id>/pdf` de un viaje con
    pasajeros: tiene que redirigir a `/admin/login`, no descargar el PDF.
  - Logueado, "PDF" y "Excel" en Viajes siguen descargando.
- **Rollback:** revertir el código y volver a limpiar la caché de rutas.

---

## Ejemplo real: deploy de la edición de boletos (2026-10-13)

Diff: `3e9c297` — edición de boletos con historial (`docs/changelog.md`).

| Paso | Por qué |
| --- | --- |
| `php artisan migrate --force` | Crea la tabla `ticket_edits` (historial de ediciones). Sin ella, editar un boleto falla. |
| `php artisan permissions:sync` | Registra el permiso nuevo `tickets.edit` (por defecto ningún rol lo trae). |
| `npm ci && npm run build` | Los blades nuevos (`edit-history`, detalle de auditoría) usan clases Tailwind que entran recién al recompilar el tema. |
| Cachés | Config/route/view cacheados contra los archivos viejos. |
| `composer install` | **No hace falta**: `composer.lock` no cambió. |
| Migración de settings | **No hace falta**: la clave nueva (`tickets.require_password_edit`) se crea al primer guardado y su default es "apagado". |

TEST DE DEPLOY