# Starter Kit User Role

Este proyecto es un kit de inicio básico para crear un panel de administración en Laravel usando Filament. Permite gestionar usuarios y asignarles permisos de administrador de forma sencilla.

## Instalación

### 1. Instalar proyecto

```bash
composer install 
npm install && npm run dev
```

Editar `composer.json` y cambiar la línea:

```json
"minimum-stability": "dev"
```

### 2. Migraciones y seeders

```bash
php artisan migrate --seed
```

Esto crea el dashboard vacío y el usuario Filament.

### NO OLVIDES PONER LA COLA DE MAIL A TRABAJAR

```bash
php artisan queue:work 
```

### 3. Listo para trabajar, lo que sigue es para empezar a trabajar con otros recursos de tu proyecto

## Modelos y Recursos

### Crear modelo con migración

```bash
php artisan make:model Productos -m
```

### Crear recurso Filament (CRUD)

Primero crea las migraciones, seeders o factories necesarios.

```bash
php artisan make:filament-resource Productos
```

#### CRUD simple con modales

```bash
php artisan make:filament-resource Productos --simple --generate
```

#### CRUD con eliminaciones suaves (soft deletes)

```bash
php artisan make:filament-resource Productos --simple --generate --soft-deletes
```

En el modelo agrega:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;
    // ...código existente...
}
```

En la migración agrega:

```php
Schema::table('users', function (Blueprint $table) {
    $table->softDeletes();
});
```

## Configuración de almacenamiento

En `.env` agrega:

```bash
FILAMENT_FILESYSTEM_DISK="public_storage"
```

En `config/filesystems.php`, en `disks` agrega:

```php
'public_storage' => [
    'driver' => 'local',
    'root' => base_path('public/storage_public'),
    // 'root' => base_path('../storage_public'), // Usar al subir a InfinityFree
    'url' => env('APP_URL').'/storage_public',
    'visibility' => 'public',
]
```

## Políticas de usuario

Crear política para el modelo User:

```bash
php artisan make:policy UserPolicy --model=User
```

Esto permite bloquear acciones por defecto y gestionar permisos.

## Recomendaciones adicionales

- Configura correctamente los roles y permisos usando [Laravel Policies](https://laravel.com/docs/authorization#writing-policies).
- Protege rutas de administración con middleware `auth` y verifica el rol de usuario.
- Usa seeders para crear usuarios de prueba.
- Revisa la documentación oficial de [Filament](https://filamentphp.com/docs/3.x/panels/installation) y [Laravel](https://laravel.com/docs).

---

¡Listo! Con estos pasos tienes un panel de administración básico para gestionar usuarios y roles en Laravel con Filament
