# Design

## Context

Ver `proposal.md` para la motivación. Estado actual:

- `ticket_date_changes` guarda `user_id` con `nullOnDelete`. `TicketRescheduleService::rescheduleForActor($ticket, $data, ?int $actorId, ...)` escribe `user_id = $actorId`; el panel de clientes lo llama con `null` desde `ClientTicketAssignmentService::assign()`.
- El Historial (`TicketAuditResource`, `TicketAuditTable`, `ticket-audit-detail.blade.php`) muestra el autor con `$change->user?->name ?? 'Usuario eliminado del sistema'`.
- `TicketAuditTable::actorFilter()` ya existe (sin OpenSpec) y separa clientes de vendedores con `user_id` nulo / no nulo. La tabla usa `persistFiltersInSession()`.
- `User` y `Clients` usan SoftDeletes; la pantalla de Usuarios permite borrado definitivo, que es lo que deja `user_id` en nulo.
- `Setting::panelClientesActivo()` dice si el panel de clientes está encendido.
- Las ediciones (`ticket_edits.user_id`) y eliminaciones solo las hace personal; los clientes solo reprograman.

## Goals / Non-Goals

**Goals:**
- Que el autor de una reprogramación sea un dato explícito que sobrevive al borrado de usuarios y clientes.
- Que el detalle y el filtro lean ese dato, no inferencias.
- Que el filtro desaparezca por completo cuando el panel de clientes está apagado.

**Non-Goals:**
- Registrar autor en otras tablas: las ediciones y eliminaciones ya son siempre del personal.
- Mostrar al cliente su propio historial de cambios.
- Permitir que el cliente edite o elimine boletos.

## Decisions

**1. Dos columnas: `actor_type` y `client_id`.**
`actor_type` (texto corto, `user` por defecto, `client` para clientes) dice quién actuó y no depende de que exista la fila. `client_id` (nulo, FK a `clients` con `nullOnDelete`) enlaza al cliente cuando se lo conoce, solo para mostrar su nombre. Constantes `ACTOR_USER` y `ACTOR_CLIENT` en `TicketDateChange`. Alternativas descartadas: un booleano `by_client` (no admite un tercer origen, como un proceso automático), y solo `client_id` (un cambio de un cliente sin fila en el padrón quedaría sin autor).

**2. Migración con backfill en la misma migración.**
Se agregan las columnas con `actor_type = 'user'` por defecto; después se marca `client` en las filas con `user_id` nulo y se enlaza `client_id` por el DNI del pasajero del boleto contra `clients.dni` (incluidos los clientes dados de baja). El backfill vive en un método público de la migración (`backfill()`) para poder probarlo, y se hace por lotes con la API de consultas y no con SQL específico de un motor. Es una suposición explícita: hasta hoy un `user_id` nulo era un cliente o un usuario borrado, y se asume cliente. `down()` quita las columnas. Siguen las convenciones de `fix_return_leg_marks_on_tickets_table` (PHPDoc con el porqué, `DB::table`, lotes de 500).

**3. El servicio recibe al cliente, no lo infiere.**
`rescheduleForActor()` suma un parámetro opcional `?int $clientId = null`. Con `clientId`, la fila queda `actor_type = client`, `client_id` y `user_id` nulo; sin él, queda `user` con el `user_id` recibido. `ClientTicketAssignmentService::assign()` pasa el cliente de la sesión. Así los llamadores existentes del admin no cambian.

**4. Un solo lugar para el texto del autor.**
`TicketDateChange::actorLabel()` devuelve el texto del detalle: nombre y usuario de acceso del usuario; "Cliente" más el nombre si se conoce el cliente (se lo carga incluso dado de baja); "Cliente" solo si no hay enlace; y "Usuario eliminado del sistema" cuando es de tipo `user` y no hay usuario. La vista llama a ese método. `TicketAuditTable` carga `dateChanges.client` junto con `dateChanges.user` para no hacer consultas por fila.

**5. Filtro por tipo de autor, no por `user_id`.**
`actorFilter()` pasa a usar `actor_type`: "Clientes" → reprogramaciones con `actor_type = client`; "Vendedores" → reprogramaciones con `actor_type = user`, ediciones visibles o boletos eliminados. Un usuario borrado del todo sigue siendo "Vendedores".

**6. Visible solo con el panel de clientes activo, con doble guarda.**
El filtro se declara con `visible()` según `Setting::panelClientesActivo()`, que lo saca del formulario y de la consulta. Como el valor puede estar guardado en la sesión (`persistFiltersInSession`), la consulta y el indicador del filtro también comprueban el parámetro y devuelven la lista sin filtrar y sin indicador si está apagado; un test cubre ese caso real. Alternativa descartada: limpiar la sesión al apagar el panel, que depende de la sesión de cada usuario y no se puede hacer desde Parámetros.

## Risks / Trade-offs

- [El backfill asume cliente en todo `user_id` nulo, y un usuario ya borrado del todo antes de este cambio quedaría como cliente] → Es irrecuperable porque el dato no existe; queda documentado en la migración. De ahora en más no se confunde.
- [Tablas grandes en la migración] → El backfill va por lotes, y las filas sin usuario son pocas (solo cambios de clientes y usuarios borrados).
- [Un filtro oculto con valor guardado en sesión podría seguir aplicándose] → La doble guarda de la decisión 6 y un test con el valor guardado.
- [Otros llamadores de `rescheduleForActor` con `actorId` nulo quedan como `user`] → Es el comportamiento correcto para algo que no es un cliente; solo el panel de clientes pasa `clientId`.

## Migration Plan

1. Desplegar la migración antes del código nuevo (agrega columnas con valor por defecto, así que el código viejo sigue funcionando).
2. Revertir: quitar las columnas con `down()`; el código viejo vuelve a inferir por `user_id`.
