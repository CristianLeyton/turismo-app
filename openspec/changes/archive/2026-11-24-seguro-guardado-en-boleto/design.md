# Design

## Context

Ver proposal.md (Why). Estado actual relevante:

- `tickets` guarda solo `price` y `travels_with_child`. En una ida y vuelta, el precio completo va en la ida y la vuelta vale 0.
- `FixedTicketPrice::insuranceDelta()` reconstruye el seguro con `periodFor(fecha de venta)` sobre la grilla `Setting::PRECIO_FIJO_PERIODOS` (un JSON en `settings`, no una tabla).
- `EditTicket::fixedPriceAdjustment()` solo actúa si el precio fijo está prendido **hoy**, y siempre ajusta la ida (`Ticket::outboundLeg()` cuando se edita la vuelta).
- `CreateTicket` arma boletos en seis lugares: ida y vuelta normales, ida y vuelta sin fecha, y los dos modos mixtos. `applyFixedPrice()` corta la emisión si la configuración cambió desde que se abrió la venta, así que al emitir los montos vigentes son los que vio el vendedor.
- La reprogramación (`TicketRescheduleService`) actualiza `trip_id` y `seat_id` con `UPDATE` sobre el mismo boleto: no crea boletos nuevos.
- Totales, caja y reportes suman solo `tickets.price`.

## Goals / Non-Goals

**Goals:**
- Que el ajuste del menor de un boleto nuevo no dependa nunca de la grilla.
- No modificar ninguna fila existente.

**Non-Goals:**
- Reconstruir el modo de los boletos viejos: no hay registro histórico de la llave.
- Registrar en la vuelta: todo lo que es plata sigue en la ida.
- Cambiar el modo al editar: el modo registra cómo se vendió, no las ediciones posteriores.
- Que las reservas de clientes (`reservas-boletos-clientes`) carguen estas columnas. Lo hará esa propuesta.

## Decisions

### 1. Dos columnas `nullable` en `tickets`, sin relleno de datos
`price_mode` (`string(10)`, `nullable`) y `child_insurance` (`unsignedInteger`, `nullable`). Con una sola columna, el `null` mezclaría "manual" con "vendido antes del cambio". Los valores del modo van como constantes en `Ticket` (`PRICE_MODE_FIXED = 'fixed'`, `PRICE_MODE_MANUAL = 'manual'`), como `TicketEdit::FIELD_PRICE`. No se crea un enum porque el proyecto no tiene `app/Enums` y crearlo sería una carpeta base nueva.
*Alternativa descartada:* guardar en `sales`. Hay boletos sin venta (`$ticket->sale?->`), y la venta no distingue entre pasajeros.

### 2. Un solo helper para los datos de precio de la ida
`FixedTicketPrice::outboundPricingAttributes(): array` devuelve `['price_mode' => …, 'child_insurance' => …]` según la configuración vigente (`fixed` + `childInsurance()`, o `manual` + `null`). `CreateTicket` lo calcula una vez después de `applyFixedPrice()` y lo agrega (`+`) a los tres arrays de ida. Los tres de vuelta no cambian. Si se olvidara un camino, ese boleto queda en `null` y se comporta como uno viejo, no queda roto.

### 3. Resolver primero la ida, después el monto
`fixedPriceAdjustment()` se reordena así:
1. Si la llave está apagada, `null` (como hoy).
2. Se busca el objetivo (la ida). Si se edita una vuelta sin ida vigente, `false` (como hoy).
3. Según `target->price_mode`:
   - `fixed`: delta de ± `target->child_insurance`.
   - `manual`: `null`, sin ajuste.
   - `null`: `insuranceDelta()` con la grilla, como hoy.

El modo se lee siempre de la ida, aunque se edite la vuelta. `insuranceDelta()` sigue existiendo solo para los boletos viejos.

### 4. Precio editable en boletos `manual`
Hay un helper en `Ticket`, `isPricedManually()`: lee la ida (el propio boleto si es ida) y responde si es `manual`. El `disabled` de `EditTicketForm` y la lectura del precio enviado en `EditTicket` (`:292`, `:539`) pasan de `FixedTicketPrice::isActive()` a `isActive() && ! isPricedManually()`. Las vueltas con precio compartido siguen bloqueadas, como hoy.

### 5. Confirmación con `deleteAction()` del repeater
`Repeater::deleteAction(fn (Action $action) => $action->requiresConfirmation()->modalDescription(…))`. La descripción se calcula con `$arguments['item']` sobre el estado actual de la grilla, aunque no esté guardado. El rango del período se toma de esa fila hasta la siguiente, como ya hace `fixedPricePeriodLabel()`. Así el aviso refleja lo que el usuario ve. Antes de implementar, verificar la firma en la documentación de Filament 4.

### 6. Conteo de boletos viejos afectados
Helper `FixedTicketPrice::legacyTicketsDependingOn(string $from, ?string $until): int`, con Eloquent. Cuenta los boletos que cumplen todo esto:
- `price_mode` en `null`, `is_return_leg = false`, sin los eliminados (soft delete por defecto).
- Fecha de venta dentro del rango, en zona horaria de la app: la de `sale.created_at` si tiene venta, la de `tickets.created_at` si no. Es el mismo criterio que la edición. Para el período más antiguo no hay límite inferior.
- No viajado: el `trip` es `null` o su `trip_date` es `>= today()`, o existe un boleto de vuelta vigente de la misma venta y pasajero con `trip` en `null` o `trip_date >= today()`.

"Viajado" se mide por día de viaje, no por hora de salida, igual que `ClientTicketAssignmentService`. Un viaje de hoy todavía cuenta. Es el lado más prudente: el aviso puede durar unas horas de más.

## Risks / Trade-offs

- [Un camino de venta queda sin las columnas] → Falla hacia el comportamiento actual. Mitigación: tests de venta para los seis caminos, que verifiquen modo y seguro en la ida y `null` en la vuelta.
- [Un boleto viejo ya viajado se edita después de borrar su período] → Toma otro seguro sin aviso. Se aceptó porque es un caso raro.
- [El conteo usa el estado no guardado de la grilla] → Si el usuario movió fechas sin guardar, el rango puede diferir del guardado. Se aceptó porque coincide con lo que ve.
- [Los boletos `manual` ya no se ajustan solos con la llave prendida] → Es un cambio buscado. Lo compensa que el precio quede editable y se registre en el historial.
- [El conteo hace una consulta por cada apertura del modal] → Es una sola consulta con `exists` en una página de administración. Es aceptable.

## Migration Plan

1. Deploy con `php artisan migrate`: la migración solo agrega columnas. El `down()` las elimina sin perder datos previos.
2. Desde el deploy, los boletos nuevos se registran. Los viejos siguen igual que antes.
3. Rollback: revertir el código y correr el `down()`. Los boletos vendidos mientras tanto vuelven al comportamiento de la grilla.
