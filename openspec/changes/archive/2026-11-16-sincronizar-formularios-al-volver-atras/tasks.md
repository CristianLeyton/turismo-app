# Tasks

## 1. Script de sincronización compartido

- [x] 1.1 Crear `resources/views/filament/login-back-navigation-sync.blade.php` con el script (ver design.md, decisiones 2 y 3): en `pageshow`, si la navegación es `back_forward` y no `persisted`, despachar `input` (con bubbles) en los inputs con `wire:model*` y valor no vacío, sin tocar `hidden`, `password`, `checkbox`, `radio` ni `file`. Si Livewire no inicializó, esperar a `livewire:initialized`. Incluir en el `<script>` un atributo identificable (p. ej. `data-login-back-navigation-sync`) para los tests. Verificar que la vista renderiza sin errores con el test de 2.3.

## 2. Registro en los dos paneles

- [x] 2.1 En `AdminPanelProvider`, registrar un render hook `PanelsRenderHook::BODY_END` con `scopes: CustomLogin::class` que renderice la vista. Verificar con el test de 2.3.
- [x] 2.2 En `ClientPanelProvider`, registrar el mismo hook con `scopes: ClientLogin::class`. Verificar con el test de 2.3.
- [x] 2.3 Crear `tests/Feature/LoginBackNavigationSyncTest.php` (PHPUnit, con `php artisan make:test --phpunit`) que compruebe que el marcador del script aparece en `GET /admin/login` y en `GET /clientes/login` (con el panel de clientes activo, como en `ClientPanelToggleTest`) y que no aparece en una página autenticada de cada panel (p. ej. el dashboard de admin con un usuario logueado). Correr `php artisan test --compact tests/Feature/LoginBackNavigationSyncTest.php` y verificar que pasa.
- [x] 2.4 Correr `vendor/bin/pint --dirty --format agent` y los tests de login existentes (`php artisan test --compact tests/Feature/ClientAuthenticationTest.php tests/Feature/ClientPanelToggleTest.php`) y verificar que pasan.

## 3. Verificación en el navegador

- [x] 3.1 Reproducir en Chrome el recorrido original: escribir un usuario en `/admin/login`, ir al panel de clientes con "¿Sos cliente?", hacer un ingreso fallido y volver con Atrás. Verificar por consola que el estado del componente (`Livewire.all()[0].ephemeral.data.username`) coincide con el valor del input, y que al enviar no aparece "El campo nombre de usuario es obligatorio.".
- [x] 3.2 Repetir con el DNI en `/clientes/login` (salir y volver con Atrás) y verificar que no aparece "El DNI es obligatorio.".
- [x] 3.3 Abrir `/admin/login` de forma normal y enviar con el usuario vacío. Verificar que sigue apareciendo el error de campo obligatorio.
