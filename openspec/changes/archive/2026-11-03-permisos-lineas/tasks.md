# Tasks

## 1. Catálogo de permisos

- [x] 1.1 Agregar el módulo `lineas` (label "Líneas", `standardActions()`) en `Permissions::catalog()` junto a `buses`, sin incluirlo en `defaultsFor()` de Administrador ni Vendedor; verificar con un test en `tests/Feature/RolesAndPermissionsTest.php` que tras `Permissions::syncToDatabase()` existen los permisos `lineas.*` y que Administrador y Vendedor no los tienen
- [x] 1.2 Agregar al test de la matriz (`test_la_matriz_de_permisos_muestra_los_modulos_del_catalogo` o uno nuevo) la verificación de que aparece el módulo "Líneas", y correr `php artisan test --compact tests/Feature/RolesAndPermissionsTest.php`

## 2. Policy de Líneas

- [x] 2.1 Crear `app/Policies/LineaPolicy.php` con `php artisan make:policy LineaPolicy --no-interaction`, usando el trait `AuthorizesModule` con `permissionModule(): 'lineas'` (mismo formato que `BusPolicy`)
- [x] 2.2 Agregar tests en `tests/Feature/LineaResourceTest.php`: Vendedor (`User::factory()->vendedor()`) no puede acceder a `ManageLineas` (`LineaResource::canViewAny()` es falso y la página responde 403); un rol custom con solo `lineas.view_any` ve el listado pero las acciones crear/editar/eliminar no están disponibles; un rol con `lineas.*` puede crear, editar y eliminar; el Super Administrador sigue pudiendo todo (los tests existentes siguen pasando). Correr `php artisan test --compact tests/Feature/LineaResourceTest.php`

## 3. Cierre

- [x] 3.1 Correr `vendor/bin/pint --dirty --format agent` y luego `php artisan test --compact --filter="LineaResourceTest|RolesAndPermissionsTest"` y verificar que todo pasa

## Workflow follow-up

- Al desplegar, correr `php artisan permissions:sync` y habilitar "Líneas" desde la matriz a los roles que lo necesiten.
- Archivar el change tras la revisión.
