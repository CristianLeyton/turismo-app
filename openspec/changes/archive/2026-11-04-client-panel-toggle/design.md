# Design

## Context

- Los parámetros viven en `Setting` (clave/valor con caché por request). Cada parámetro tiene una constante y un getter con su valor por defecto, por ejemplo `descargaAutomaticaPdf()`.
- `TicketSettings` arma las pestañas con `tab()` y mapea los campos a pestañas en `TAB_FIELDS`, que usan el badge de errores y la notificación. La página entera exige `settings.manage`.
- Los permisos salen del catálogo de `Permissions::catalog()`. `syncToDatabase()` solo agrega y aplica `defaultsFor()`. El Super Administrador recibe `all()` y además tiene bypass por `Gate::before`.
- El panel de clientes (`ClientPanelProvider`, guard `client`) tiene su propia lista `->middleware([...])` y `->authMiddleware([...])`. Fuera del panel, en `routes/web.php`, hay cuatro rutas de clientes sin grupo común.
- El aviso del login de administración ya existe como render hook `AUTH_LOGIN_FORM_AFTER` comentado en `AdminPanelProvider`.
- `resources/views/boletos/share.blade.php` tiene cambios locales del usuario sin commitear.

## Goals / Non-Goals

**Goals:**
- Una sola fuente de verdad (`Setting`) para saber si el panel está activo, consultada por el middleware, el login de administración, el boleto público y la venta.
- Que el corte sea en el servidor: 404 también para las peticiones Livewire de una página del panel que quedó abierta.

**Non-Goals:**
- No cerrar ni invalidar sesiones de cliente al desactivar: alcanza con el 404.
- No cambiar la API de reservas de asientos (`auth:web,client`). Sin páginas del panel, un cliente no tiene desde dónde usarla.
- No cambiar la regla del padrón para la venta sin fecha (ver specs de `ticket-sale`).

## Decisions

**1. Parámetro `Setting::PANEL_CLIENTES_ACTIVO` con el getter `panelClientesActivo()`, por defecto `false`.**
Sigue el patrón de los demás parámetros booleanos. Clave: `clients.panel_activo`. No hace falta migración porque la tabla es clave/valor.

**2. Permiso `settings.client_panel` ("Activar/desactivar panel de clientes") en el módulo `settings` del catálogo.**
No se agrega a `defaultsFor(ROLE_ADMIN)`. El Super Administrador lo recibe por `all()` y por el bypass. Alternativa descartada: reutilizar `settings.manage`, porque no permitiría que un administrador maneje Parámetros sin poder apagar el panel.

**3. Middleware `EnsureClientPanelIsEnabled` que hace `abort(404)` con el parámetro desactivado.**
- En el panel va registrado con `->middleware([EnsureClientPanelIsEnabled::class], isPersistent: true)`, aparte de la lista actual. Al ser middleware de panel y no de `authMiddleware`, también cubre el ingreso. Al ser persistente, Filament lo aplica a las peticiones `livewire/update` de los componentes del panel, así que una página que quedó abierta tampoco puede ejecutar acciones como "Elegir fecha". Antes de implementar hay que confirmar con `search-docs` la firma de `middleware()` en Filament 4.
- Prioridad: Laravel ordena los middleware por prioridad y `Authenticate` corre antes que el resto, así que `/clientes` sin sesión redirigía a un login que no existe en lugar de responder 404. Por eso, en `bootstrap/app.php`, el middleware se pone antes de `AuthenticatesRequests` con `prependToPriorityList()`.
- En `routes/web.php`, las cuatro rutas de clientes se agrupan con `Route::middleware(EnsureClientPanelIsEnabled::class)->group(...)`. Esto incluye el `Route::redirect` de `clientes/ingresar`, que así responde 404 en lugar de redirigir a otro 404.
- Alternativa descartada: no registrar el panel en `bootstrap/providers.php` según el parámetro. Leer la base de datos durante el boot de los providers es frágil (en `config:cache` y en las migraciones) y, además, dejaría las rutas de Filament sin nombre, así que fallaría todo `route('filament.clientes.*')`.

**4. Pestaña "Clientes" con `->visible()` según el permiso, y guardado protegido en `save()`.**
`tab('Clientes', Heroicon::OutlinedUsers, ...)` se agrega a `TAB_FIELDS` con `panel_clientes_activo`. Filament no deshidrata los campos ocultos, pero de todos modos `save()` escribe el parámetro solo si `Auth::user()->can('settings.client_panel')`. No hay que confiar en el estado que manda Livewire.

**5. Aviso del login de administración: se descomenta el render hook y el closure devuelve `''` con el panel apagado.**
El enlace pasa de `/clientes` a `route('filament.clientes.auth.login')`, nombre confirmado con `route:list`. El HTML deja de ser un heredoc con CSS fijo de tema claro y pasa a la vista `filament/admin/client-login-shortcut.blade.php`, con clases Tailwind y variante `dark:`, como piden las convenciones del panel.

**6. Logo del boleto público: el `href` se calcula en la vista con `Setting::panelClientesActivo()`.**
Es la línea `<a href="{{ url('') }}">` de `share.blade.php`. El cambio es mínimo y tiene que respetar los cambios locales del usuario en el mismo archivo. Alternativa: pasar la URL desde `TicketShareController`. Es más limpio, pero toca otro archivo para resolver un ternario y la vista ya lee helpers.

**7. Textos de la venta sin fecha: `helperText` con closure y cuerpo de la notificación armado según el parámetro.**
Los dos interruptores (`sell_without_date_ida` y `sell_without_date_vuelta`) comparten el texto. Para no duplicarlo, se extrae a un método estático privado de `TicketForm`. En `CreateTicket` la oración "Si no, el pasajero no podría ver su boleto desde el panel de clientes." se agrega solo con el panel activo.

## Risks / Trade-offs

- [Los tests existentes del panel de clientes fallan por el valor por defecto desactivado] → Los tests de clientes activan el parámetro en `setUp()`, y el apagado se cubre con un test propio.
- [Caché por request de `Setting`: si un test cambia el parámetro y después pide la URL en el mismo proceso, puede leer un valor viejo] → `Setting::set()` ya limpia esa clave. En los tests se usa `Setting::set()` y no inserts directos.
- [Si `isPersistent` no se comporta como se espera, una página abierta podría ejecutar una acción con el panel apagado] → Hay un test que monta el componente de "Mis boletos" con el panel apagado y espera 404 (o la verificación equivalente que permita Filament).
- [Conflicto con los cambios locales sin commitear de `share.blade.php`] → Se edita solo la línea del logo y no se toca el resto del archivo.

## Migration Plan

1. Desplegar el código: `/clientes` empieza a responder 404, porque el parámetro no tiene valor guardado.
2. Correr `php artisan permissions:sync` para crear `settings.client_panel`.
3. Cuando se quiera abrir el panel, el Super Administrador (o alguien con el permiso asignado desde la matriz) lo activa en Parámetros > Clientes.

Rollback: revertir el código. La clave `clients.panel_activo` y el permiso que quedan en la base no afectan al código anterior.
