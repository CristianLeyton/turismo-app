# Design

## Context

En `desarrollo` el precio de un ida y vuelta está entero en el boleto de ida y la vuelta vale 0. El modo de precio (`price_mode`: `fixed`, `manual` o null) y el seguro (`child_insurance`) se guardan solo en la ida, y `Ticket::isPricedManually()` los lee desde cualquiera de los dos tramos.

Todo el bloqueo del precio en la edición sale de `EditTicket::isPriceLockedByFixedPrice()`, que hoy es `FixedTicketPrice::isActive() && ! $ticket->isPricedManually()`. La usan tres lugares:

- el campo "Precio" de `EditTicketForm` (`disabled`, `dehydrated` y `hint`);
- el resumen de confirmación (`EditTicket`, sección "Venta");
- el guardado (`EditTicket`, paso 4 "Datos económicos"), que ignora el precio enviado si está bloqueado.

El ajuste automático del seguro (`fixedPriceAdjustment()` y `applyFixedPriceAdjustment()`) toma el precio de la ida **antes** de la transacción y lo pisa con `viejo ± seguro`. Hoy no choca con un precio cargado a mano porque un boleto "fijo" no se puede editar y un "manual" no recibe ajuste. Con el permiso nuevo los dos podrían pasar en la misma edición.

Ventas suma `tickets.price` por `sales.sale_date`, y la edición ya llama a `Sale::recalculateTotal()` cuando cambia un precio. No hace falta tocar nada ahí.

## Goals / Non-Goals

**Goals:**
- Que la regla de bloqueo siga viviendo en un solo método, así el formulario, el resumen y el guardado no se desincronizan.
- Que el servidor rechace un precio enviado por quien no tiene el permiso, aunque manipule el pedido.

**Non-Goals:**
- Llevar la diferencia de precio al día de la edición en Ventas: el usuario confirmó que alcanza con el día de la venta original.
- Precio propio en la vuelta o reparto entre tramos: eso es de `precio-por-boleto`.
- Registrar el cambio de modo como una fila aparte del historial: la fila de "Precio" ya dice quién, cuándo y por qué.

## Decisions

**1. El permiso entra en `isPriceLockedByFixedPrice()`.**
Queda `FixedTicketPrice::isActive() && ! $ticket->isPricedManually() && ! Auth::user()?->can('tickets.edit_fixed_price')`. Como el formulario, el resumen y el guardado consultan el mismo método, los tres cambian juntos y el servidor sigue descartando el precio de quien no tiene el permiso. Alternativa descartada: chequear el permiso solo en el formulario, porque el pedido manipulado lo saltearía.

**2. El permiso va en el catálogo de Boletos, al lado de `edit`.**
`'edit_fixed_price' => 'Editar precio fijo'`, sin agregarlo a `defaultsFor()` del Administrador ni del Vendedor. El Super lo recibe por `self::all()`. Sigue el patrón de los permisos "por bandera" (`vender_sin_fecha`, `edit`), que se asignan a mano desde la matriz.

**3. El paso a "manual" se hace en `applyChanges()`.**
Si `$changes` trae `price` (solo puede pasar fuera de la vuelta compartida), se pone `price_mode = manual` en el boleto editado antes del `save()`. `child_insurance` no se toca. Vale con el precio fijo prendido o apagado. `$changes` solo trae `price` si el valor nuevo es distinto del anterior, así que guardar el mismo precio no cambia el modo. Alternativa descartada: pasarlo a "manual" solo cuando se usa el permiso nuevo. El usuario eligió aplicarlo siempre, que además es lo que hace `precio-por-boleto` y facilita el merge.

**4. Con precio cargado a mano no hay ajuste del seguro.**
En `save()`, si `$changes` trae `price`, el ajuste del seguro no se aplica: se trata como null. En el resumen de confirmación, se calcula primero el cambio manual del precio, y si existe, se omite la línea del ajuste. Así no aparecen dos "Precio" ni el ajuste pisa el monto cargado. Desde la vuelta el precio no es editable, así que el ajuste de la ida por un cambio del menor en la vuelta no cambia.

**5. El aviso del campo depende de quién edita.**
`hint()` mantiene "El precio viaja en el boleto de ida" y "Lo calcula el precio fijo". Para quien tiene el permiso, con el precio fijo prendido y un boleto que no es "manual", el campo muestra debajo (`helperText`) "Precio fijo. Si lo cambiás, el boleto pasa a precio manual." Se usa `helperText` porque el texto es largo para el hint de arriba a la derecha.

## Risks / Trade-offs

- [Después del primer cambio, el boleto "manual" lo edita cualquiera con permiso de edición] → Es la decisión del usuario, y es igual a como funcionan hoy los boletos vendidos a mano. El historial registra cada cambio de precio con su autor.
- [Al mergear `precio-por-boleto`, su versión de `isPriceLockedByFixedPrice()` y de la edición por pasaje pisa esta] → Al resolver el conflicto hay que conservar la condición del permiso y el "precio a mano gana sobre el seguro". Queda anotado en las tareas y en el proposal.
- [`Auth::user()` dentro de un método estático usado en los closures del schema] → Ya pasa en otros campos de la venta (`TicketForm`). En los tests se usa `actingAs`.

## Migration Plan

Sin migraciones. Deploy: código, `php artisan permissions:sync` y asignar "Editar precio fijo" a mano desde la matriz. Sin ese paso nadie, salvo el Super, ve un cambio. Rollback: volver el código. El permiso que quede en la base no hace nada, y los boletos que pasaron a "manual" siguen editables a mano, que es lo correcto porque su precio ya no es el de la grilla.
