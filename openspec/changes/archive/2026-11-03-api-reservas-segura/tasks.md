# Tasks

> Requiere `login-clientes-filament` implementado: la API acepta el guard `client` que ese change crea.

## 1. API: login y sesión del servidor

- [x] 1.1 Adaptar `SeatReservationLifecycleTest` a la API nueva: autenticar con `actingAs` y mandar la sesión del pedido con un helper que genere un id de 40 caracteres y lo envíe como cookie de sesión (`withCookie(config('session.cookie'), $id)`), usando ese id en las reservas de "mi sesión". Verificar que los tests existentes, ya adaptados, fallan solo por la falta de los cambios de 1.2 a 1.4.
- [x] 1.2 Agregar `->middleware('auth:web,client')` al grupo `api/seat-reservations` de `routes/web.php` (design D1). Verificar con tests que cada una de las 4 operaciones responde 401 en JSON sin login y no cambia ninguna reserva, y que `reserve` funciona tanto con un usuario de administración (`actingAs($user)`) como con un cliente (`actingAs($client, 'client')`).
- [x] 1.3 En `SeatReservationController`, tomar la sesión de `$request->session()->getId()` en `reserve`, `release`, `keep-alive` y `status`, dejar de validar `session_id` y sacarlo del log de entrada (design D2 y D5). Verificar con tests: `reserve` con un `session_id` ajeno en el cuerpo deja la reserva a nombre de la sesión del pedido; `release` y `keep-alive` con el `session_id` de otra sesión no tocan las reservas de esa sesión; `status` devuelve solo las de la sesión del pedido.
- [x] 1.4 Eliminar la ruta y el método `cleanup` (design D4). Verificar con un test que `POST /api/seat-reservations/cleanup` con login responde 404 y que el comando `app:cleanup-expired-reservations` sigue eliminando las vencidas.
- [x] 1.5 Cambiar la respuesta del `catch` de `reserve` por el mensaje genérico "No se pudo reservar los asientos. Intentá de nuevo." (design D5). Verificar con un test que fuerza una excepción, por ejemplo con `SeatReservation::creating` lanzando un error: responde 500, el mensaje es el genérico y no incluye el texto de la excepción.

## 2. Selector de asientos

- [x] 2.1 En `seat-selector.blade.php`, eliminar `sessionId` del `x-data` y del cuerpo de los cuatro `fetch`, quitar la guarda `!this.sessionId`, agregar `'Accept': 'application/json'` a los cuatro `fetch` y tomar `$sessionId` siempre de `session()->getId()` (design D3). Verificar con un test que renderiza el wizard de venta hasta el selector: el HTML no contiene `session()->getId()` y un asiento reservado por la sesión sigue apareciendo como propio.
- [x] 2.2 Verificar en el navegador, con `composer run dev` o el sitio local, que en "Vender" seleccionar, cambiar y deseleccionar asientos crea y libera reservas en `seat_reservations` a nombre de la sesión, que la renovación por actividad sigue funcionando y que en la consola no hay errores de los pedidos a `/api/seat-reservations/*`. Repetir la selección de asientos en el panel de clientes, al asignar fecha a un boleto sin fecha.

## 3. Cierre

- [x] 3.1 Correr `vendor/bin/pint --dirty --format agent` y los tests `SeatReservationLifecycleTest`, `SeatReservationWizardTest`, `EmissionAtomicityTest`, `ClientPanelTicketsTest`, `RescheduleTicketPageTest` y `TicketRescheduleServiceTest`. Todos tienen que pasar.
- [x] 3.2 Agregar en `docs/deploy.md`, sección 7, el aviso de este deploy: sin migraciones ni `npm run build`, limpiar cachés de rutas y probar después que `status` sin login responde 401 y que vender con selección de asientos sigue andando. Verificar que el aviso está y que `openspec validate api-reservas-segura --strict` da válido.

## Workflow follow-up

- Archivar con `/opsx:archive` y sincronizar las specs.
- Después, revisar el requisito "Cierre de una venta exitosa" de `ticket-sale`, que supera los 500 caracteres.
