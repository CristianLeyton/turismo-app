# Proposal

## Why

Hoy se le pueden vender dos o más boletos a la misma persona en un mismo viaje. Cada venta crea pasajeros nuevos sin buscar por DNI, y ninguna validación compara el DNI contra los boletos ya emitidos. El resultado son asientos ocupados de más, manifiestos con nombres repetidos y devoluciones manuales. La misma puerta está abierta al reprogramar un boleto, cuando el cliente elige la fecha de un boleto sin fecha y cuando se edita el DNI de un boleto ya vendido.

## What Changes

- Una persona, identificada por su DNI, no puede tener más de un boleto activo en el mismo viaje (mismo colectivo, horario y fecha), sin importar el tramo. Cuenta tanto el DNI del pasajero de un boleto como el de un menor que viaja con él.
- La regla no tiene excepciones: ningún permiso la saltea.
- **Venta:**
  - Al finalizar, si algún DNI ya está en el viaje de ida o de vuelta, se bloquea la venta y no se crea nada.
  - La alerta es persistente y tiene dos acciones: "Ver boleto N°X", que abre ese boleto en otra pestaña, y "Volver a la venta", que deja el formulario intacto.
  - El chequeo se repite dentro de la transacción de emisión, con los viajes bloqueados.
- **Aviso anticipado:** al presionar "Siguiente" en el paso "Pasajeros", un modal muestra cada persona que ya tiene boleto en el viaje, con "Ver boleto" en otra pestaña y "Volver a pasajeros".
- **DNI únicos en la venta:** el formulario no permite cargar dos veces el mismo DNI en una venta, sumando adultos y menores. La emisión lo verifica de nuevo.
- **Reprogramación:**
  - Boletería no puede mover un boleto a un viaje donde su pasajero o su menor ya tiene otro boleto activo. Recibe la misma alerta, con "Ver boleto N°X".
  - El cliente, al elegir fecha desde su panel, recibe el mismo rechazo pero sin el enlace de administración.
- **Edición:** al cambiar el DNI del pasajero o del menor, o al agregar un menor, se bloquea si el nuevo DNI ya está en alguno de los viajes de los boletos de ese pasajero (ida y vuelta).
- Los duplicados que ya existen no se modifican. La regla solo frena operaciones nuevas.

## Capabilities

### New Capabilities
- `passenger-trip-uniqueness`: unicidad de una persona (por DNI, como adulto o como menor acompañante) por viaje. Se aplica en la venta, la reprogramación de boletería, la asignación de fecha desde el panel de clientes y la edición de boletos, con la alerta y sus acciones.

### Modified Capabilities
- `ticket-sale`: agrega el requisito de DNI únicos dentro de una misma venta (adultos y menores), validado en el formulario y en la emisión, y el aviso de asientos liberados al presionar "Finalizar".

## Impact

- **Venta:** `app/Filament/Resources/Tickets/Pages/CreateTicket.php`, para el bloqueo previo y el re-chequeo en la transacción. `app/Filament/Resources/Tickets/Schemas/TicketForm.php`, para el modal del paso "Pasajeros" y la regla de DNI distintos. `resources/views/filament/tickets/duplicate-passenger-modal.blade.php`, con las tarjetas del modal.
- **Reprogramación:** `app/Services/TicketRescheduleService.php`, que cubre boletería y panel de clientes. `app/Filament/Resources/Tickets/Pages/RescheduleTicket.php`, para la alerta con "Ver boleto N°X". El panel de clientes (`ClientTicketAssignmentService`, `MyTickets`) muestra el rechazo sin cambios de código.
- **Edición:** `app/Filament/Resources/Tickets/Pages/EditTicket.php`.
- **Lógica compartida nueva:** una clase de soporte para buscar los conflictos, junto con un tipo de error que lleva los boletos en conflicto.
- **Aviso de asientos liberados:** `CreateTicket::onValidationError()`.
- **Sin cambios de esquema ni de dependencias.**
- **Tests:** feature tests nuevos para venta, reprogramación, asignación de fecha por el cliente y edición.
