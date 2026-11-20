# Design

## Context

Ver proposal.md (Why). Estado actual relevante:

- `Setting` es un clave/valor con caché por request; cada parámetro tiene constante y helper (`Setting::observacionesActivas()`, etc.). `TicketSettings` agrupa los campos por pestaña en `TAB_FIELDS` y protege cada parámetro delicado con un permiso `settings.*`: sin el permiso la sección no se ve y `save()` no escribe el valor.
- La venta (`TicketForm` + `CreateTicket`) tiene un repeater de pasajeros con `price`, `payment_method` y `travels_with_child` por pasajero, e `is_round_trip` a nivel venta. `CreateTicket` crea los boletos en dos caminos: con fecha (`createTicketsWithLock`) y sin fecha / mixto. En los dos, el precio va a la ida y la vuelta se crea con `price = 0`.
- `EditTicket` edita un boleto por vez. El tramo de vuelta (`isReturnLeg()`) no edita precio (`isReturnLegWithSharedPrice`). `travels_with_child` es por boleto, mientras que los pasajeros hijos cuelgan del pasajero adulto, compartido por ida y vuelta. Cada cambio se audita en `TicketEdit` con motivo.
- `Sale::recalculateTotal()` existe pero `EditTicket` no lo llama: hoy `total_amount` queda desactualizado al editar un precio.

## Goals / Non-Goals

**Goals:**
- Una única fuente del cálculo, usada por el formulario (vista previa), la emisión y la edición.
- El servidor es el que decide el precio; el formulario solo lo muestra.

**Non-Goals:**
- Precios por ruta, tramo, horario o tipo de pasajero.
- Descuentos, cortesías o precio manual con el precio fijo prendido.
- Recalcular boletos ya vendidos.
- Mostrar el precio fijo en el panel de clientes o en otra pantalla que no sea la venta, la edición y Parámetros.

## Decisions

### 1. Clase de soporte `App\Support\FixedTicketPrice`
Métodos estáticos: `isActive(): bool`, `basePrice(): int`, `childInsurance(): int`, `forPassenger(bool $withChild, bool $isRoundTrip): int` (`(base + (withChild ? seguro : 0)) * (isRoundTrip ? 2 : 1)`) y `insuranceDelta(bool $hadChild, bool $hasChild): int` (+seguro, −seguro o 0). Lee los parámetros de `Setting` mediante helpers nuevos (`precioFijoActivo()`, `precioFijoBoleto()`, `precioFijoSeguroMenor()`).
*Por qué*: la vista previa, la emisión y la edición usan la misma fórmula; si viviera en el formulario se duplicaría en `CreateTicket` y `EditTicket`. *Alternativa*: un servicio inyectable. Se descarta porque el proyecto ya usa clases estáticas en `app/Support` (`SaleCutoff`, `ServiceDays`).

### 2. Parámetros nuevos en `Setting`
`sales.precio_fijo_activo` (bool, default apagado), `sales.precio_fijo_boleto` y `sales.precio_fijo_seguro_menor` (enteros guardados como string). En `TicketSettings`: sección "Precio de los boletos" al final de "Venta", visible con `settings.fixed_price` (constante `FIXED_PRICE_PERMISSION`); montos visibles y `required` solo con el toggle prendido (`->visible`/`->required` sobre `Get`), reglas `integer`, `min:1`/`min:0`, `max:999999` y mensajes en voseo. Se agregan a `TAB_FIELDS['Venta']`. En `save()`: sin permiso, no se escribe ninguno de los tres; con permiso y toggle apagado, se escribe solo el toggle (los montos se conservan, igual que el mensaje de WhatsApp oculto).
*Validación del entero*: `integer` rechaza "5000,50" y "5000.50", y evita el redondeo silencioso que ya está prohibido en las horas de venta.

### 3. Formulario de venta: precio calculado y fotografía de la configuración
Un `Hidden::make('fixed_price_snapshot')` a nivel venta guarda, al montar el formulario, `activo|base|seguro`. Si el snapshot dice prendido, el `TextInput::make('price')` se oculta y en su lugar un `TextEntry` "Precio" muestra `FixedTicketPrice::fromSnapshot(snapshot, travels_with_child, is_round_trip)`. Lo mismo hace el resumen (`tickets/summary.blade.php`). El valor se calcula en cada render, así que se actualiza al marcar el menor o al pasar a ida y vuelta, también cuando el formulario cambia esos campos por código (`$set`), que no dispara `afterStateUpdated`.
*Por qué calcular desde el snapshot y no desde la configuración vigente*: lo que se muestra es exactamente lo que vale el snapshot. Si el snapshot coincide con la configuración vigente al emitir, el precio que calcula el servidor es el que vio el vendedor, y no hace falta enviar ni comparar el precio del formulario (que se ignora).
*Alternativa descartada*: un `TextInput` `readOnly()` recalculado con `afterStateUpdated`. Falla porque `is_round_trip` se apaga por código desde varios lugares del wizard y el precio quedaría desactualizado.

### 4. Emisión: cálculo y rechazo en `CreateTicket`
Antes de la transacción (junto a la validación de métodos de pago), si el precio fijo está prendido ahora o lo estaba en el snapshot, se compara el snapshot con la configuración vigente. Si difiere, se lanza la notificación "Cambió la configuración de precios. Revisá los precios de la venta y volvé a emitir.", se actualiza el snapshot del formulario (así se muestran los precios nuevos, o vuelve el campo editable si se apagó) y se corta sin crear nada. Si coincide, se reemplaza `passengerData['price']` por el valor calculado antes de los dos caminos de creación, así ninguno usa el valor del cliente. Los boletos de vuelta siguen con `price = 0`, sin cambios.
*Alternativa descartada*: emitir con el precio vigente sin avisar, que cobraría un precio distinto del informado al pasajero.

### 5. Edición: precio bloqueado y ajuste en el boleto de ida
Con el precio fijo prendido, `price` se muestra `disabled` y no `dehydrated` en todos los boletos, y `applyChanges` ignora `data['price']`. Si cambia `travels_with_child`, se calcula `insuranceDelta`:
- Boleto de ida: se ajusta su propio `price`.
- Boleto de vuelta: se busca la ida vigente del mismo pasajero en la misma venta (`is_return_leg = false`, sin soft delete, misma lógica que `Ticket::getDisplayPriceAttribute`). Si no existe, se rechaza la edición con una notificación (spec "Vuelta sin boleto de ida activo"); si existe, se ajusta su `price` dentro de la misma transacción.

La auditoría del ajuste se registra como `TicketEdit` con `field = price` sobre el boleto de ida, con el motivo de la edición; si viene de la vuelta, el `reason` se completa con "(menor modificado en la vuelta)". El resumen de confirmación (`changesSummary`) agrega la línea "Precio: $X → $Y". Delta 0 (seguro 0) no genera cambio de precio ni registro.

### 6. Recálculo del total en la edición
Después de guardar la edición, dentro de la transacción, `$ticket->sale?->recalculateTotal()`. Corre siempre que cambió el precio de algún boleto (manual o por ajuste), así también se corrige el caso manual de hoy.

### 7. Permiso
`settings.fixed_price` => "Configurar precio fijo de los boletos" en `Permissions::catalog()['settings']`, fuera de los defaults de Administrador y Vendedor. Se aplica con `php artisan permissions:sync`.

## Risks / Trade-offs

- [Un vendedor con la venta abierta mucho tiempo ve el rechazo después de cargar todo] → Se conservan los datos cargados y solo se actualizan los precios; un clic más para emitir.
- [El ajuste desde la vuelta modifica otro boleto (la ida), lo que puede sorprender] → El resumen de confirmación muestra el cambio de precio y el historial de la ida lo registra con su aclaración.
- [El ajuste usa el seguro vigente y no el de la venta] → Decisión explícita del usuario; queda en un escenario del spec.
- [El menor puede quedar marcado en un tramo y no en el otro] → Comportamiento existente; el precio sigue la regla de un seguro por tramo con menor.
- [Con el precio fijo prendido no hay forma de hacer una cortesía] → Fuera de alcance por decisión del usuario; para un caso puntual se apaga el parámetro.

## Migration Plan

1. Deploy del código.
2. `php artisan permissions:sync` para crear `settings.fixed_price`.
3. El Super Administrador asigna el permiso a quien corresponda, carga los montos y prende el parámetro. Hasta entonces nada cambia.

Rollback: apagar el parámetro devuelve el comportamiento manual sin tocar datos; no hay migraciones.
