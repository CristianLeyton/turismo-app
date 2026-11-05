# Proposal

## Why

El panel de clientes (`/clientes`) tiene un ingreso propio: una página que valida DNI y contraseña y guarda el DNI en la sesión (`ClientSession`), sin usar la autenticación de Laravel. Eso duplica lo que Filament ya resuelve (límite de intentos, "Recordarme", cierre de sesión, regeneración de la sesión al entrar) y deja a los clientes afuera de cualquier control que dependa de `auth`. Por ejemplo, la API de reservas de asientos no puede exigir login sin romper el selector del panel de clientes (change `api-reservas-segura`).

## What Changes

- El panel de clientes usa el login de Filament con un guard propio (`client`) sobre la tabla `clients`, separado del guard `web` de los usuarios del panel de administración. Un vendedor y un cliente pueden tener sesión a la vez en el mismo navegador.
- El formulario de ingreso pide **DNI** (acepta puntos y espacios) y **contraseña**, con "Recordarme". La contraseña inicial sigue siendo el DNI y el primer ingreso sigue obligando a elegir una propia.
- **BREAKING:** la página de ingreso pasa de `/clientes/ingresar` a `/clientes/login`. La URL vieja redirige a la nueva.
- **BREAKING:** el límite de intentos pasa a ser el de Filament (por IP, 5 por minuto), en lugar del propio (8 por IP y 5 por DNI). Ante un error, el mensaje es uno solo para DNI desconocido y contraseña incorrecta, sin indicar cuál de los dos falló.
- Un cliente dado de alta sin contraseña (por ejemplo, el alta rápida desde la venta) recibe como contraseña inicial su DNI, con la obligación de cambiarla. Hoy esos clientes no pueden entrar.
- "Salir" cierra la sesión del guard `client`.
- Se elimina la página propia de ingreso y la identificación por clave de sesión. El resto del panel (mis boletos, asignar fecha, perfil, PDF) usa el cliente autenticado.

## Capabilities

### New Capabilities

- `client-access`: ingreso, sesión y salida de los clientes en su panel; contraseña inicial y cambio obligatorio; acceso a sus boletos y al PDF solo con sesión.

### Modified Capabilities

_Ninguna._ El change `api-reservas-segura` se ajusta aparte para aceptar también al cliente autenticado.

## Impact

- `app/Models/Clients.php`: pasa a ser autenticable (`Illuminate\Foundation\Auth\User`) e implementa `FilamentUser` y `HasName`. Contraseña inicial al crear sin contraseña.
- `config/auth.php`: provider `clients` y guard `client`.
- Migración nueva: columna `remember_token` en `clients`.
- `app/Providers/Filament/ClientPanelProvider.php`: `authGuard('client')`, `login(...)`, `authMiddleware`. Sin `ClientAccess`.
- Página nueva de login de clientes (extiende el `Login` de Filament, al estilo de `CustomLogin` del panel de administración).
- `app/Support/ClientSession.php`: queda como fachada sobre `auth('client')`.
- `app/Http/Middleware/EnsureClientIdentified.php`: solo la regla de cambio obligatorio de contraseña.
- `MyTickets`, `SetPassword`, `ClientProfile`, `ClientTicketAssignmentService`, `ClientTicketPdfController`: "Salir" y lectura del cliente vía la fachada.
- Se elimina `app/Filament/Client/Pages/ClientAccess.php` y su vista `filament.client.identify`.
- Tests: `ClientPanelTicketsTest` y `ClientPanelProfileTest` identifican al cliente con la clave de sesión. Pasan a usar `actingAs($client, 'client')`.
- Los clientes con sesión abierta al momento del deploy tienen que volver a ingresar.
