# Design

## Context

Ver `proposal.md` (Why). Estado actual relevado:

- **Panel de clientes:** `ClientPanelProvider` no usa `->login()` ni `authMiddleware`. `ClientAccess` (`/clientes/ingresar`) valida el DNI normalizado y la contraseña con `Hash::check`, aplica un límite propio en caché (8 por IP y 5 por DNI por minuto) y guarda `client_panel_dni` en la sesión mediante `ClientSession::identifyByDni`.
- **`EnsureClientIdentified`:** redirige al ingreso sin identificación, saca de la página de ingreso a quien ya está identificado y obliga a pasar por `SetPassword` (`/clientes/clave`) mientras `must_change_password` esté activo.
- **Quién lee la identidad:** `ClientSession::current()` y `dni()` los usan `MyTickets` (salir), `SetPassword`, `ClientProfile`, `ClientTicketAssignmentService` y `ClientTicketPdfController`. Esa ruta (`clientes/boletos/{ticket}/pdf`) está en `routes/web.php`, fuera del panel.
- **Contraseñas:** `Clients` ya tiene `password` con cast `hashed` y `must_change_password`. El backfill dejó a todos con contraseña inicial igual al DNI. El reseteo desde el panel de administración hace lo mismo. Pero el alta rápida desde la venta (`TicketForm`, `Clients::create`) no asigna contraseña: en la base local hay 1 cliente con `password` NULL que hoy no puede entrar.
- **Tabla `clients`:** no tiene `remember_token`. `Clients` usa `SoftDeletes`.
- **Autenticación:** `config/auth.php` tiene solo el guard `web` (provider `users`), que es el que usa el panel `admin`. El login de administración (`CustomLogin`) extiende `Filament\Auth\Pages\Login` y cambia el email por `username`.
- **Logout de Filament:** `LogoutController` hace `Filament::auth()->logout()` y después `session()->invalidate()`. Invalida la sesión completa, incluida la del otro guard.

## Goals / Non-Goals

**Goals:**
- Que el cliente sea un usuario autenticado de Laravel (guard `client`), para que cualquier control basado en `auth` lo reconozca.
- Reusar el login de Filament: formulario, límite de intentos, "Recordarme" y regeneración de la sesión.
- Mantener el flujo de contraseña inicial y cambio obligatorio.

**Non-Goals:**
- Recuperación de contraseña por email o registro autónomo de clientes.
- Cambiar el modelo `User` o el login de administración.
- Unificar `clients` y `users`.

## Decisions

### D1. Guard `client` sobre la tabla `clients`
En `config/auth.php`: provider `clients` (driver `eloquent`, modelo `App\Models\Clients`) y guard `client` (driver `session`, provider `clients`). El panel usa `->authGuard('client')`.
- *Por qué un guard aparte y no `web`:* `web` resuelve `users`. Con otro guard, cada uno guarda su propia clave en la sesión, así que un vendedor y un cliente pueden estar autenticados a la vez y ninguno entra al panel del otro.
- El provider de Eloquent aplica el scope de `SoftDeletes`: un cliente dado de baja no se encuentra y no puede entrar.

### D2. `Clients` autenticable
`Clients` pasa a extender `Illuminate\Foundation\Auth\User` (mantiene `HasFactory` y `SoftDeletes`) e implementa:
- `FilamentUser::canAccessPanel(Panel $panel)`: `true` solo para el panel `clientes`.
- `HasName::getFilamentName()`: `"{$this->nombre} {$this->apellido}"`, porque no hay columna `name`.

También agrega `remember_token` a `$hidden`. El cast `hashed` de `password` ya existe.
- *Baneados (`can_buy = false`):* hoy pueden entrar a ver sus boletos y se mantiene igual. `can_buy` controla la compra, no el acceso.

### D3. Página `ClientLogin`
`App\Filament\Client\Pages\Auth\ClientLogin` extiende `Filament\Auth\Pages\Login`, como `CustomLogin`:
- `getEmailFormComponent()` devuelve un `TextInput` `dni` (label "DNI", `inputMode('numeric')`, autofocus).
- `getCredentialsFromFormData()` devuelve `['dni' => ClientSession::normalizeDni(...), 'password' => ...]`.
- `throwFailureValidationException()` marca el error en `data.dni` con el mensaje estándar de Filament.

El panel registra `->login(ClientLogin::class)` y `->authMiddleware([Authenticate::class])`. La ruta queda en `/clientes/login`.
- *Límite de intentos:* el propio de `Login` (5 por minuto, por IP). Se descarta el límite propio por DNI.
- *Ingreso anterior:* se elimina `ClientAccess`. Se agrega una ruta `clientes/ingresar` que redirige a `filament.clientes.auth.login`, porque puede estar compartida.

### D4. Logout solo del guard `client`
El panel muestra el menú de usuario de Filament, pero su ítem `logout` se redefine (`userMenuItems(['logout' => ...])`) para que haga POST a una ruta propia, `clientes.logout` (`POST /clientes/salir`, controlador `ClientLogoutController`). Esa ruta hace `auth('client')->logout()` y `session()->regenerateToken()`, y redirige al login. **No** invalida la sesión. La acción "Salir" de `SetPassword` hace lo mismo con `ClientSession::logout()`.
- *Por qué:* el `LogoutController` de Filament invalida la sesión completa y cerraría también la de administración del mismo navegador.
- *Alternativa descartada:* ocultar el menú de usuario (`userMenu(false)`). Se pierde el acceso estándar al perfil.
- *Límite conocido:* el logout del panel de administración sigue usando el controlador de Filament, que invalida la sesión y cierra también la del cliente. Se acepta: es un caso de prueba del personal, no de uso real.

### D9. Perfil de Filament
Se elimina la página propia `ClientProfile` y su vista. El panel usa `->profile(ClientEditProfile::class, isSimple: false)`, donde `ClientEditProfile` extiende `Filament\Auth\Pages\EditProfile`, como el `EditProfile` del panel de administración:
- **Campos:** `nombre`, `apellido`, `telefono` (vacío o entre 7 y 12 números, sin espacios) y `dni` solo lectura (`disabled`, `dehydrated(false)`). Contraseña nueva, confirmación y actual, con los componentes de Filament. La actual se valida con `currentPassword` contra el guard del panel.
- **Contraseña nueva:** mínimo 6 caracteres, en lugar de `Password::default()`, para mantener la regla de `SetPassword`. Al guardarla, `must_change_password` pasa a `false`.
- **Ruta:** slug `perfil`, para conservar `/clientes/perfil`.
- **Cambio obligatorio:** `EnsureClientPasswordIsSet` sigue redirigiendo el perfil a `/clientes/clave` mientras la obligación esté vigente.

### D5. `ClientSession` como fachada
`ClientSession` mantiene su API para no tocar sus consumidores:
- `current()` devuelve `auth('client')->user()`.
- `dni()` devuelve el `dni` de ese usuario.
- `logout()` aplica el logout de D4.
- Se eliminan `identifyByDni()` y `SESSION_KEY`.
- `normalizeDni()` queda igual.

`ClientTicketPdfController` sigue llamando a `ClientSession::current()`. Su ruta ya está en el grupo `web`, así que el guard `client` resuelve la sesión.

### D6. `EnsureClientIdentified` → `EnsureClientPasswordIsSet`
La redirección por falta de sesión la hace `Authenticate` de Filament. El middleware queda solo con la regla de `must_change_password` (redirigir a `filament.clientes.pages.clave`) y se renombra para reflejarlo. Se registra en `authMiddleware`, después de `Authenticate`. También se elimina la redirección "ya identificado → fuera del ingreso", porque la hace el login de Filament.

### D7. Contraseña inicial al crear
En `Clients`, un hook `creating`: si `password` está vacío, asigna el DNI y `must_change_password = true`. Cubre el alta rápida y cualquier otra futura.

Una migración de datos aplica la misma regla a los clientes existentes con `password` NULL. El cast `hashed` no corre en `DB::table`, así que se recorren con Eloquent: `chunkById` y `forceFill(...)->saveQuietly()`, como el backfill original.

### D8. `remember_token`
Migración que agrega `remember_token` (nullable, 100) a `clients`. Sin la columna, ingresar con "Recordarme" falla al guardar el token.

## Risks / Trade-offs

- [Las sesiones de clientes abiertas al momento del deploy se pierden] → Vuelven a ingresar con el mismo DNI y contraseña. Se avisa en `docs/deploy.md`.
- [Se pierde el límite por DNI: alguien con muchas IPs podría probar contraseñas de un DNI] → Las contraseñas son bcrypt y el límite por IP sigue. Si hace falta, `ClientLogin` puede sumar un segundo límite por DNI después.
- [El mensaje de error ya no distingue "DNI no encontrado" de "contraseña incorrecta"] → Es intencional, porque no revela qué DNI están en el padrón. El texto puede sumar "si no podés entrar, consultá en boletería".
- [El logout de administración cierra también la sesión de cliente del mismo navegador] → Ver D4.
- [El panel de clientes pasa a tener `Filament::auth()` con otro modelo: algún componente compartido podría asumir `App\Models\User`] → Los widgets de cuenta no están en el panel de clientes. Se verifica recorriendo el panel en el navegador (tareas).

## Migration Plan

1. Deploy: `git pull`, `php artisan migrate --force` (`remember_token` y contraseña inicial de los clientes sin contraseña), `php artisan optimize:clear && php artisan optimize` y `npm ci && npm run build` (vista de login del panel de clientes).
2. Verificar: entrar a `/clientes/ingresar` redirige al login; un cliente entra con DNI y contraseña; uno con la contraseña inicial es llevado a elegirla; "Salir" funciona.
3. Rollback: revertir el código y `php artisan migrate:rollback` de la migración de `remember_token`. La de contraseña inicial no se revierte, porque deja datos válidos para el código anterior.
