# Proposal

## Why

La API de reservas de asientos (`/api/seat-reservations/*`) no exige login y opera sobre el identificador de sesión que manda el cliente en el cuerpo del pedido (hallazgo H5 del baseline). El selector escribe ese identificador en el HTML, así que ante un XSS queda expuesto, y con él cualquiera puede liberar o renovar las reservas de un vendedor. Además, `reserve` devuelve el mensaje interno de la excepción y `cleanup` lo puede invocar cualquier página con token CSRF.

## What Changes

- **BREAKING:** las operaciones de la API exigen un usuario autenticado: un usuario del panel de administración (guard `web`) o un cliente del panel de clientes (guard `client`). Sin login responden 401 en JSON.
- **Depende de `login-clientes-filament`**, que convierte al cliente en un usuario autenticado con el guard `client`. Sin ese change, exigir login rompería el selector de asientos del panel de clientes.
- **BREAKING:** la sesión sobre la que se opera es siempre la del pedido (la cookie de sesión). Se ignora el identificador de sesión que mande el cliente, y el selector deja de escribirlo en el HTML.
- **BREAKING:** se elimina el endpoint `cleanup`. La limpieza de reservas vencidas queda solo en el comando `app:cleanup-expired-reservations`, que es lo que corre el cron de producción. Ninguna pantalla usaba el endpoint.
- Ante un error inesperado, `reserve` responde un mensaje genérico. El detalle queda solo en el log.
- El selector manda `Accept: application/json` en sus pedidos, para que un pedido sin sesión reciba 401 y no una redirección al login.

## Capabilities

### New Capabilities

_Ninguna._

### Modified Capabilities

- `seat-reservation`: cambian los requisitos "API de reservas para el selector" (login obligatorio, sesión del servidor, sin `cleanup`, error genérico) y "Limpieza de reservas vencidas a demanda" (solo por comando).

## Impact

- `routes/web.php`: grupo `api/seat-reservations` con middleware `auth:web,client` y sin la ruta `cleanup`.
- `app/Http/Controllers/Api/SeatReservationController.php`: sesión tomada de `session()->getId()`, sin el parámetro `session_id`, sin `cleanup()` y con mensaje de error genérico.
- `resources/views/tickets/seat-selector.blade.php`: deja de exponer `sessionId` al JavaScript y agrega `Accept: application/json`. El cálculo de "reservado por mí" en PHP sigue usando la sesión del servidor.
- El JavaScript del selector está dentro del blade y no se compila: no hace falta `npm run build`, porque no cambian clases de Tailwind.
- Tests: `SeatReservationLifecycleTest` llama a la API sin login y con `session_id` inventado; hay que adaptarlo.
- Sin migraciones ni permisos nuevos.
