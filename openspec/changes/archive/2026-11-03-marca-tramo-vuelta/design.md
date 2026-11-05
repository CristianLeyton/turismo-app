# Design

## Context

Ver `proposal.md` (Why). Estado actual relevado:

- La columna `is_return_leg` existe desde la migración `2026_10_06_000002`, que hizo backfill con la convención histórica **solo en boletos vigentes** (`whereNull('deleted_at')`). En la base local quedan 1146 boletos dados de baja que cumplen la convención y no tienen marca.
- El hook `creating` de `Ticket` pone `is_return_leg = true` si el boleto es de ida y vuelta, sin `return_trip_id` y con precio 0, y el valor actual es falso. Como no distingue entre "no indicado" y "false explícito", pisa la ida que `createPendingTickets` marca a propósito como `false`.
- La emisión no marca de forma explícita los boletos de vuelta con fecha (`handleRecordCreation` y la vuelta con fecha de `createPendingTickets`): dependen del hook. Varios tests crean boletos de vuelta sin indicar la marca y también dependen de él.
- Hay cuatro predicados que todavía usan el precio:
  - `Ticket::isReturnLeg()`: marca **o** convención.
  - `Ticket::isPartOfRoundTrip()`: marca, o `is_round_trip` y (viaje de vuelta **o** precio 0). Una ida mixta con precio mayor a 0 da `false`.
  - `Ticket::getDisplayPriceAttribute()`: busca el precio de la ida si `isPartOfRoundTrip()` y no hay viaje de vuelta.
  - `EditTicket::isReturnLegWithSharedPrice()`: ida y vuelta, sin viaje de vuelta y precio 0. Una ida a precio 0 queda con el precio bloqueado.
- En la base local no hay ventas afectadas por H2 ni grupos (venta, pasajero) con más de 2 boletos vigentes. Producción no se pudo revisar.

## Goals / Non-Goals

**Goals:**
- Que ninguna ida quede ni se lea como vuelta por tener precio 0.
- Que toda la lógica de ida y vuelta lea la marca, sin heurísticas de precio.

**Non-Goals:**
- Cambiar cómo se guarda el precio en ida y vuelta (sigue entero en la ida y 0 en la vuelta).
- Revisar `RepairRoundTripLinks` u otras reparaciones de vínculos entre tramos.
- H5 y cualquier otro hallazgo.

## Decisions

### D1. El hook solo completa la marca cuando no se indicó
El hook `creating` aplica la convención solo si `is_return_leg` no está entre los atributos del modelo (`! array_key_exists('is_return_leg', $ticket->getAttributes())`). Un `false` explícito se respeta.
- *Por qué:* mantiene funcionando los tests y cualquier código que crea vueltas sin marca, y arregla el caso de H2 sin depender de que todos los caminos estén al día.
- *Alternativa descartada:* borrar el hook. Obliga a tocar todos los tests que crean vueltas y deja sin red a un camino nuevo que olvide la marca.

### D2. La emisión marca siempre los dos tramos
En `CreateTicket`, todo boleto de ida lleva `is_return_leg => false` y todo boleto de vuelta lleva `is_return_leg => true`: venta con fecha, ida sin fecha (incluida la de ambos tramos sin fecha) y vuelta con fecha del flujo mixto.
- *Por qué:* la emisión es la única que sabe qué tramo crea. Con D1, esto cierra los dos casos de H2 (venta mixta y ambos tramos sin fecha).

### D3. Los predicados leen solo la marca
- `isReturnLeg()` devuelve `(bool) $this->is_return_leg`.
- `isPartOfRoundTrip()` devuelve `is_round_trip || is_return_leg`. Una venta solo de ida guarda `is_round_trip = false`, así que alcanza con la columna.
- `getDisplayPriceAttribute()` busca el precio de la ida hermana solo si `isReturnLeg()`. Si no, devuelve el precio propio.
- `EditTicket::isReturnLegWithSharedPrice()` pasa a ser `isReturnLeg()`.
- *Por qué:* después de la migración (D4) todos los boletos, vigentes o no, tienen la marca correcta. El fallback por precio solo produce errores.
- *Alternativa descartada:* dejar el fallback en lectura y excluir los boletos con marca explícita de ida. Una columna booleana no distingue "false explícito" de "sin dato", así que el fallback no tiene arreglo.

### D4. Migración de datos, sin cambios de esquema
Una migración nueva que, en `up()`:
1. Marca `is_return_leg = true` en los boletos **dados de baja** que cumplen la convención y no tienen marca. Completa el backfill original.
2. Corrige H2: por cada (venta, pasajero) con exactamente dos boletos de ida y vuelta, los dos marcados como vuelta y ninguno como ida, pone `is_return_leg = false` en el de menor `id`. La emisión siempre crea la ida antes que la vuelta. Los grupos que no cumplen esta forma no se tocan.

`down()` no revierte los datos: dejaría marcas incorrectas a propósito.
- *Por qué migración y no comando:* corre sola en el deploy, como el backfill original, y queda en el historial de migraciones.

## Risks / Trade-offs

- [En producción puede haber grupos de H2 con otra forma, por ejemplo con boletos de baja] → El paso 2 de D4 solo corrige grupos con forma exacta. Antes del deploy se corre la consulta de detección en producción y se revisa a mano lo que quede afuera.
- [Si el pasajero de un boleto se editó, el par (venta, pasajero) puede no coincidir] → Mismo criterio: si el grupo no tiene exactamente dos boletos, no se toca.
- [Código fuera de `CreateTicket` que cree vueltas sin marca y con precio distinto de 0] → No hay ninguno: la vuelta siempre se crea con precio 0, así que D1 la sigue marcando.
- [Los boletos de baja pasan a mostrarse como vuelta en listados con baja] → Es lo correcto. Hoy ya se ven así por el fallback de lectura.

## Migration Plan

1. Antes del deploy, en producción: contar los boletos de baja que cumplen la convención sin marca y listar los grupos (venta, pasajero) de ida y vuelta sin ninguna ida vigente.
2. Deploy y `php artisan migrate`.
3. Repetir la consulta del paso 1: ambos resultados tienen que dar 0, salvo los grupos anómalos revisados a mano.
4. Rollback: revertir el código, sin revertir la migración. Con el código anterior, las idas corregidas a precio 0 se vuelven a leer como vuelta por el fallback (es el comportamiento de hoy), pero el resto de los datos sigue siendo válido.
