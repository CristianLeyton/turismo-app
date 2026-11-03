# Design

## Context

Ver `proposal.md` (Why). Estado actual relevado:

- Las rutas `api/seat-reservations/*` están en `routes/web.php`, dentro del grupo `web` (sesión y CSRF), sin middleware de autenticación.
- Hoy el panel `admin` usa el guard `web` (modelo `User`) y el panel `clientes` no usa autenticación de Laravel: identifica al cliente con una clave propia en la sesión. El change `login-clientes-filament` lo pasa al guard `client` (modelo `Clients`). Este change se implementa **después** de ese. Los dos guards comparten la misma sesión de Laravel, así que `session()->getId()` es el mismo identificador para cualquiera de los dos.
- Todos los que crean reservas del lado del servidor ya usan `session()->getId()`: la emisión (`CreateTicket`, `Trip::createTicketsWithLock`), la reprogramación (`TicketRescheduleForm`) y la asignación desde el panel de clientes (`ClientTicketAssignForm`). Le pasan ese mismo valor al selector como `session_id`. El único que confía en el valor del cliente es el controlador de la API.
- El selector (`seat-selector.blade.php`) escribe `sessionId: '{{ $sessionId }}'` en el `x-data` y lo manda en el cuerpo de `reserve`, `release`, `keep-alive` y `status`. Sus `fetch` no mandan `Accept: application/json`.
- Ninguna pantalla llama a `cleanup`. El cron de producción corre el comando `app:cleanup-expired-reservations` (README).
- `SeatReservationLifecycleTest` llama a la API sin usuario y con `session_id => 'mi-sesion'`.

## Goals / Non-Goals

**Goals:**
- Que nadie pueda operar sobre reservas de otra sesión mandando su identificador.
- Que la API no sea usable sin login y no filtre detalles internos.

**Non-Goals:**
- Permisos finos por operación, por ejemplo exigir `tickets.create`. Cualquier usuario autenticado puede reservar, porque los clientes también usan el selector para asignar fecha.
- Rate limiting.
- Cambiar la lógica de reserva, renovación o liberación.

## Decisions

### D1. Middleware `auth:web,client` en el grupo de rutas
El grupo `api/seat-reservations` pasa a usar `->middleware('auth:web,client')`: alcanza con estar autenticado en cualquiera de los dos guards.
- *Por qué:* es el mecanismo estándar de Laravel. Con los dos guards cubre a los vendedores (venta y reprogramación) y a los clientes (asignación de fecha).
- *Alternativa descartada:* solo `auth` (guard `web`). Deja afuera a los clientes y rompe el selector de su panel.
- *Respuesta sin login:* el middleware `auth` responde 401 en JSON cuando el pedido espera JSON (`expectsJson()`). Por eso el selector agrega `Accept: application/json` (D3). Los tests usan `postJson`.

### D2. La sesión sale del servidor
El controlador reemplaza `$request->input('session_id')` por `$request->session()->getId()` en las cuatro operaciones y deja de validar `session_id`. Si el cuerpo trae el campo, se ignora.
- *Por qué:* es el mismo valor que ya usan la emisión, la reprogramación y el panel de clientes, así que el comportamiento para el usuario legítimo no cambia.
- *Alternativa descartada:* seguir recibiendo `session_id` y compararlo con el de la sesión, rechazando si difiere. Agrega un caso de error sin beneficio.

### D3. El selector deja de exponer la sesión
- Se elimina `sessionId` del `x-data` y del cuerpo de los cuatro `fetch`. La guarda `!this.sessionId` de `releaseReservations` se elimina.
- Los cuatro `fetch` agregan `'Accept': 'application/json'`.
- La variable PHP `$sessionId` del blade se mantiene para calcular "reservado por mí" del lado del servidor. Se toma siempre de `session()->getId()` y se ignora `$session_id`, aunque los formularios lo sigan pasando.
- *Por qué:* el identificador deja de estar en el DOM, que es lo que lo exponía ante un XSS.

### D4. Se elimina `cleanup`
Se borran la ruta y el método `cleanup()` del controlador. El comando `app:cleanup-expired-reservations` no cambia.
- *Alternativa descartada:* dejarlo solo para superadministradores. No lo usa nadie y el cron ya cubre la necesidad.

### D5. Error genérico en `reserve`
El `catch` de `reserve` responde 500 con "No se pudo reservar los asientos. Intentá de nuevo." y deja el detalle en `Log::error`, como ahora. También se quita del log de entrada el campo `session_id`, para no escribir identificadores de sesión en el log.

## Risks / Trade-offs

- [Pestañas abiertas antes del deploy tienen el JavaScript viejo, que manda `session_id` sin `Accept`] → El servidor ignora el campo y el usuario está logueado, así que los pedidos siguen funcionando. Sin login, un pedido viejo sin `Accept` no recibe 401: Laravel intenta redirigir a la ruta `login`, que no existe (los paneles usan las de Filament), y responde error. Aun así, sin sesión no hay reservas que tocar, y el caso desaparece al recargar la página.
- [Sesión vencida mientras el vendedor está en el selector] → La API responde 401 y el selector lo trata como un error de reserva. El siguiente pedido de Livewire ya muestra la expiración de la página (419) como hasta ahora.
- [Un cliente autenticado podría reservar asientos de cualquier viaje desde la consola] → Ya podía hacerlo sin login. Ahora queda acotado a usuarios registrados y a reservas de 5 minutos. Los permisos finos quedan fuera de alcance.

## Migration Plan

1. Deploy normal: `git pull` y cachés (`php artisan optimize:clear && php artisan optimize`, que incluye las rutas). No hay migraciones ni `npm run build`.
2. Verificar en producción que vender y reprogramar con selección de asientos sigue andando, y que `POST /api/seat-reservations/status` sin login responde 401.
3. Rollback: revertir el código y limpiar cachés.
