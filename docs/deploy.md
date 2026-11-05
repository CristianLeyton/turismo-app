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
