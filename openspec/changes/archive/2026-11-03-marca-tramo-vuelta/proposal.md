# Proposal

## Why

Una venta de ida y vuelta a precio 0 (cortesía o canje) puede quedar con la ida marcada como tramo de vuelta (hallazgo H2 del baseline, confirmado con un test). Pasa porque, al crear el boleto, el sistema aplica la convención histórica (ida y vuelta + sin viaje de vuelta + precio 0) y pisa la marca explícita de ida. Además, varias pantallas siguen usando esa convención para decidir qué boleto es la vuelta. El resultado es una venta con dos "vueltas" y ninguna ida: la reprogramación, el PDF, el panel de clientes y la edición del precio tratan mal a la ida.

## What Changes

- La marca explícita de tramo de vuelta pasa a ser la **única** fuente de verdad para identificar la vuelta. La emisión marca cada boleto como ida o como vuelta, en todos los caminos (venta con fecha, sin fecha y mixta).
- **BREAKING (comportamiento):** una marca explícita de ida ya no se pisa al crear el boleto. La convención histórica (ida y vuelta + sin viaje de vuelta + precio 0) solo completa la marca cuando quien crea el boleto no la indicó, y deja de usarse al leerlo. Una ida a precio 0 sigue siendo ida.
- La pertenencia a una venta de ida y vuelta, el precio que se muestra en la vuelta y la regla de "precio compartido" en la edición usan la marca explícita, no el precio.
- Migración de datos:
  - Marca como tramo de vuelta a los boletos **dados de baja** que cumplen la convención y no tienen marca (el backfill original solo cubrió los vigentes; en la base local son 1146).
  - Corrige las ventas ya afectadas por H2: en cada par de boletos de ida y vuelta del mismo pasajero y la misma venta que quedó sin ida, el boleto creado primero vuelve a ser la ida.

## Capabilities

### New Capabilities

_Ninguna._

### Modified Capabilities

- `ticket-sale`: cambia el requisito "Identificación del tramo de vuelta". La vuelta se identifica solo por la marca explícita, la emisión marca cada tramo y ya no se infiere por precio.

## Impact

- `app/Models/Ticket.php`: hook `creating`, `isReturnLeg()`, `isPartOfRoundTrip()` y `getDisplayPriceAttribute()`.
- `app/Filament/Resources/Tickets/Pages/CreateTicket.php`: marca explícita en los boletos de ida y de vuelta de la venta con fecha y de la venta sin fecha o mixta.
- `app/Filament/Resources/Tickets/Pages/EditTicket.php`: `isReturnLegWithSharedPrice()`.
- Nueva migración de datos sobre `tickets` (sin cambios de esquema).
- Consumidores que no cambian de código, pero sí de resultado para las idas a precio 0: reprogramación (`TicketRescheduleService`, `TicketRescheduleForm`), panel de clientes (`MyTickets`, `ClientTicketAssignForm`), detalle del boleto y PDF.
- Tests: `SellWithoutDateTest`, `TicketEditTest` y tests de reprogramación que crean boletos de vuelta sin marca.
- Fuera de alcance: H5 (`api-reservas-segura`), que queda para el próximo change.
