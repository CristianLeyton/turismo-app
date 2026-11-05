# Tasks

## 1. Cliente autenticable

- [x] 1.1 Crear con `php artisan make:migration` la migración que agrega `remember_token` nullable a `clients` (design D8). Verificar con `php artisan migrate` en local que la columna existe y que el `down()` la elimina.
- [x] 1.2 Agregar a `config/auth.php` el provider `clients` y el guard `client` (design D1). Hacer que `Clients` extienda `Illuminate\Foundation\Auth\User` e implemente `FilamentUser` (solo el panel `clientes`) y `HasName` (design D2). Verificar con tests: `Auth::guard('client')->attempt(['dni' => ..., 'password' => ...])` autentica a un cliente vigente y no a uno dado de baja; `getFilamentName()` devuelve nombre y apellido.
- [x] 1.3 Agregar el hook `creating` de contraseña inicial y la migración de datos para los clientes con `password` NULL (design D7). Verificar con tests: un cliente creado sin contraseña puede autenticarse con su DNI y queda con `must_change_password`; uno creado con contraseña conserva la suya; la migración asigna la inicial a un cliente existente sin contraseña y no toca a los demás.

## 2. Login del panel

- [x] 2.1 Crear `App\Filament\Client\Pages\Auth\ClientLogin` (DNI y contraseña, DNI normalizado, error en `data.dni`) y configurar `ClientPanelProvider` con `authGuard('client')`, `login(ClientLogin::class)`, `authMiddleware([Authenticate::class, ...])` y `userMenu(false)` (design D3 y D4). Verificar con tests de Livewire sobre `ClientLogin`: con "11.223.344" y la contraseña correcta inicia sesión en el guard `client` y redirige a "Mis boletos"; con contraseña incorrecta y con DNI desconocido no inicia sesión y el mensaje es el mismo; superado el límite de intentos, la contraseña correcta también se rechaza.
- [x] 2.2 Reemplazar `EnsureClientIdentified` por `EnsureClientPasswordIsSet`, registrado en `authMiddleware` (design D6). Verificar con tests: sin sesión, `/clientes` redirige a `/clientes/login`; con la contraseña inicial, "Mis boletos" redirige a `/clientes/clave`; después de elegir la contraseña, "Mis boletos" responde 200.
- [x] 2.3 Agregar la redirección de `clientes/ingresar` a `/clientes/login` y eliminar `ClientAccess` y la vista `filament.client.identify` (design D3). Verificar con un test que `GET /clientes/ingresar` redirige al login y con `grep` que no quedan referencias a `ClientAccess`.

## 3. Identidad en el resto del panel

- [x] 3.1 Convertir `ClientSession` en fachada de `auth('client')` (`current`, `dni`, `logout` sin invalidar la sesión) y eliminar `identifyByDni` y `SESSION_KEY` (design D5 y D4). Actualizar "Salir" de `MyTickets` y `SetPassword` para que redirijan al login. Verificar con tests: "Salir" deja al cliente sin sesión y lo lleva a `/clientes/login`; un usuario de administración autenticado en el mismo test sigue autenticado en `web` después de que el cliente sale.
- [x] 3.2 Adaptar `ClientPanelTicketsTest` y `ClientPanelProfileTest` para identificar al cliente con `actingAs($client, 'client')` en lugar de la clave de sesión, y reemplazar los tests de `ClientAccess` por los de `ClientLogin` de la tarea 2.1. Verificar que los dos archivos pasan completos, incluidos el scoping por DNI, la asignación de fecha y el PDF propio y ajeno.
- [x] 3.3 Verificar con un test que con un cliente autenticado `/admin` redirige al login de administración, y que con un usuario de administración autenticado `/clientes` redirige al login de clientes.

## 4. Menú de usuario y perfil de Filament

- [x] 4.1 Mostrar el menú de usuario y redefinir su `logout` para que apunte a la ruta nueva `clientes.logout` (`ClientLogoutController`, design D4). Verificar con tests: `POST /clientes/salir` deja al cliente sin sesión, redirige a `/clientes/login` y no cierra la sesión `web` de un vendedor; el menú de usuario de "Mis boletos" muestra el nombre del cliente y el link a `/clientes/salir`.
- [x] 4.2 Reemplazar `ClientProfile` por `ClientEditProfile` (perfil de Filament, design D9) y eliminar la página y su vista. Adaptar los tests del perfil de `ClientPanelProfileTest`. Verificar con tests: edita nombre, apellido y teléfono sin tocar el DNI; cambia la contraseña con la actual correcta y levanta `must_change_password`; rechaza una actual incorrecta, una nueva de menos de 6 caracteres, una confirmación distinta y un teléfono inválido; con la contraseña inicial, `/clientes/perfil` redirige a `/clientes/clave`.

## 5. Cierre

- [x] 5.1 Correr `npm run build` y recorrer en el navegador: login de clientes (claro y oscuro), primer ingreso con contraseña inicial, "Mis boletos", asignar fecha con selector de asientos, menú de usuario, perfil, PDF y "Salir". En la consola no tiene que haber errores, y el panel de administración tiene que seguir funcionando en otra pestaña.
- [x] 5.2 Correr `vendor/bin/pint --dirty --format agent` y los tests de clientes, `SeatReservationWizardTest` y `RescheduleTicketPageTest`. Todo tiene que pasar.
- [x] 5.3 Agregar en `docs/deploy.md`, sección 7, el aviso de este deploy: migraciones, `npm run build`, los clientes tienen que volver a ingresar, la URL nueva y qué probar. Verificar que el aviso está y que `openspec validate login-clientes-filament --strict` da válido.

## Workflow follow-up

- Archivar con `/opsx:archive`. Crea `openspec/specs/client-access/spec.md`.
- Después implementar `api-reservas-segura`, que depende de este change para que la API acepte el guard `client`.
