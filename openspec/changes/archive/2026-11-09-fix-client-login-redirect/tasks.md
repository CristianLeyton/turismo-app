# Tasks

## 1. Helper de pertenencia al panel

- [x] 1.1 Agregar `isPanelUrl(string $url): bool` en `App\Support\ClientSession`: normaliza a path y compara contra el path del panel `clientes` desde `Filament::getPanel('clientes')`. Verificar con test unitario/feature de URLs relativas, absolutas con query y simuladas de `/admin`. (Verificación: `php artisan test --compact --filter=ClientSessionTest`.)

## 2. Respuesta del login de clientes

- [x] 2.1 Crear `App\Filament\Client\Auth\ClientLoginResponse extends LoginResponse`: con `url.intended` del panel → ahí; si no → `Filament::getUrl()`; cualquier otro panel → `parent::toResponse()`. (Verificación: siguiente task.)
- [x] 2.2 Test: sesión con `url.intended = /admin` (y otro con `/`), `Livewire::test(ClientLogin::class)` con credenciales válidas → `assertRedirect` a la home del panel clientes, y la URL prevista queda descartada de la sesión. (Verificación: `php artisan test --compact --filter=ClientLoginRedirectTest`.)
- [x] 2.3 Test: sesión con `url.intended` de una página propia del panel (`/clientes/...`) → el ingreso va a esa página. (Verificación: mismo test de 2.2.)

## 3. Mount del login con cliente ya autenticado

- [x] 3.1 Sobreescribir `mount()` en `ClientLogin` con el mismo filtro (intended del panel si corresponde, si no `Filament::getUrl()`), conservando `form->fill()`. (Verificación: siguiente task.)
- [x] 3.2 Test: cliente autenticado con `session()->put('url.intended', '/admin')` → `mount` redirige a la home del panel, no a `/admin`. (Verificación: `php artisan test --compact --filter=ClientLoginRedirectTest`.)

## 4. Registro del binding y comportamiento de admin intacto

- [x] 4.1 Registrar el binding `LoginResponse::class → ClientLoginResponse::class` en `ClientPanelProvider::register()` (o donde siga el patrón del proyecto) y verificar que `/admin` resuelve la respuesta por defecto. (Verificación: corrección de tests de 2.x más test de admin en 4.2.)
- [x] 4.2 Test: login de administración con usuario web válido y `url.intended` de una página protegida de `/admin` sigue llegando a esa página (cubre la rama `parent::toResponse()`). (Verificación: `php artisan test --compact --filter=ClientLoginRedirectTest`.)

## 5. Integración y formato

- [X] 5.1 Probar manualmente el flujo del bug: entrar a la raíz del sitio sin sesión web hasta `/admin/login`, seguir el aviso de clientes, loguearse y verificar que aterriza en "Mis boletos". (Verificación: observable en navegador.)
- [x] 5.2 Correr `vendor/bin/pint --dirty --format agent` y la batería de tests del panel de clientes (`php artisan test --compact --filter=ClientLogin`) para asegurar que nada se rompió. (Verificación: comandos sin errores.)

## Workflow follow-up

- Archivar el cambio después de la revisión del proyecto con `openspec archive`.
