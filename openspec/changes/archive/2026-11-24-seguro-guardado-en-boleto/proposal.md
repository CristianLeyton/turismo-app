# Proposal

## Why

Al agregar o quitar el menor de un boleto ya vendido, el sistema reconstruye el seguro cobrado buscando en la grilla de períodos de precio, que se puede editar y borrar libremente. Borrar o corregir un período pasado cambia en silencio el seguro que se suma o resta a boletos de esa época. Los precios son críticos para el negocio: el boleto tiene que guardar cómo se vendió, sin depender de una configuración editable.

## What Changes

- Los boletos de ida vendidos desde este cambio guardan su modo de precio (`fixed` o `manual`) y, si se vendieron con el precio fijo, el seguro por tramo vigente al venderse. La vuelta no guarda nada: su modo y su seguro son siempre los de su ida.
- La migración solo agrega columnas `nullable`. No modifica ningún boleto ni venta existente; los boletos anteriores quedan sin modo (`null`).
- Al editar el menor con el precio fijo prendido:
  - Boleto `fixed`: se ajusta con el seguro guardado en el boleto, sin mirar la grilla.
  - Boleto `manual`: no hay ajuste automático y el campo "Precio" queda editable a mano. **BREAKING** respecto de hoy: estos boletos dejan de ajustarse solos y dejan de estar bloqueados con el precio fijo prendido.
  - Boleto anterior al cambio (`null`): sigue como hoy, con el seguro del período de su fecha de venta en la grilla.
- Borrar un período de la grilla pide siempre confirmación. Si hay boletos anteriores al cambio que dependen de ese período y todavía no viajaron, la confirmación avisa cuántos son y qué les va a pasar.
- Se actualiza el texto de ayuda de la grilla: los períodos pasados ya no se "conservan" como regla.
- Sin cambios en totales de venta, caja, reportes, reprogramación, PDF, link público ni panel de clientes.

## Capabilities

### New Capabilities

_Ninguna._

### Modified Capabilities

- `ticket-fixed-price`: el boleto de ida registra su modo de precio y su seguro al venderse; el ajuste del menor usa el seguro del boleto, no ajusta los boletos vendidos a mano y deja su precio editable; los boletos anteriores mantienen el comportamiento actual.
- `settings-page`: borrar un período de precio pide confirmación, con aviso detallado cuando hay boletos anteriores al cambio que dependen de él; los períodos pasados se pueden borrar.

## Impact

- **Base de datos:** migración que agrega `tickets.price_mode` y `tickets.child_insurance`, ambos `nullable`, sin relleno de datos.
- **Código:** `Ticket` (`$fillable`, casts), `CreateTicket` (los seis lugares que arman boletos), `FixedTicketPrice`, `EditTicket` (`fixedPriceAdjustment` y el campo precio en `EditTicketForm`), `TicketSettings` (acción de borrar del repeater y textos).
- **Futuro:** la propuesta `reservas-boletos-clientes` también crea boletos y va a tener que cargar estas columnas al confirmar una reserva.
- **Tests:** `TicketFixedPriceSaleTest`, `TicketFixedPriceEditTest`, `TicketSettingsTest`.
