# Tasks

## 1. Panel de administración

- [x] 1.1 Agregar `afterSave()` en `app/Filament/Resources/Users/Pages/Auth/EditProfile.php` que re-hidrate el formulario con `fillForm()`, y verificar con `php artisan test --compact --filter=EditProfileRefreshFormTest` que el test del panel admin pasa

## 2. Panel de clientes

- [x] 2.1 Agregar `afterSave()` (mismo patrón) en `app/Filament/Client/Pages/Auth/ClientEditProfile.php`
- [x] 2.2 Extender/mirar el test para el perfil del cliente: guardar con nombre con espacios sobrantes y teléfono vacío, verificar que el formulario queda mostrando lo persistido (trim, teléfono null, `must_change_password` false cuando aplicó) con `php artisan test --compact --filter=ClientEditProfileRefreshFormTest`

## 3. Casos límite cubiertos en los tests

- [x] 3.1 Test: guardado con error de validación, no re-hidrata (los valores que escribió el usuario quedan en el formulario) — ejecutar junto con los tests de los grupos 1-2
- [x] 3.2 Test: tras guardar con cambio de contraseña, los campos de contraseña quedan vacíos — se verifica dentro de los tests de perfil existentes o el nuevo

## Workflow follow-up

- Ejecutar `vendor/bin/pint --dirty --format agent` al cerrar la implementación.
- Correr `php artisan test --compact` completo antes de archivar.
- Archivar el cambio cuando la revisión esté lista.
