# Propuesta: specs base de venta de boletos y reserva de asientos

## Why

El sistema no tiene ninguna spec: las reglas de venta (corte por horario, venta sin fecha, ida y vuelta, validación de pasajeros) y la reserva temporal de asientos viven solo en el código y en los tests. Documentarlas tal como funcionan hoy da una referencia para revisar cambios futuros y deja registradas las rarezas detectadas, en lugar de que se pierdan.

## What Changes

- Se crean las dos primeras specs de capacidad del proyecto, que describen el **comportamiento actual** del sistema sin modificarlo.
- Cada requisito se respalda con el código o el test que lo cumple. Ningún requisito describe comportamiento deseado que no exista hoy.
- Las rarezas y posibles defectos encontrados al relevar el código se registran como **hallazgos** en `design.md`, no en las specs. Cada hallazgo puede convertirse más adelante en un change propio.
- No se modifica código, base de datos ni configuración.

Queda fuera de esta tanda:

- Compartir boletos por link o WhatsApp (`ticket-sharing`): está en desarrollo y sin commitear. Se documenta cuando se estabilice.
- Edición, reprogramación y baja de boletos (`ticket-post-sale`): se documenta en la próxima tanda. Acá solo se mencionan donde la venta les deja datos preparados (boletos pendientes).
- Generación de PDFs, panel de clientes, pagos, flota y rutas.

## Capabilities

### New Capabilities

- `ticket-sale`: emisión de boletos desde el panel de administración. Cubre el wizard de venta (búsqueda de viaje, asientos, pasajeros, resumen), viajes de ida y vuelta ("Diferido"), venta sin fecha, venta fuera de término, validaciones de pasajeros (DNI, clientes baneados, padrón), acompañantes (menor o mascotas), precio y método de pago, y la creación de la venta y sus boletos.
- `seat-reservation`: bloqueo temporal de asientos mientras un vendedor completa una venta. Cubre la reserva por sesión con vencimiento, la renovación, la liberación, la detección de conflictos con otros vendedores y la verificación final de disponibilidad al emitir.

### Modified Capabilities

(ninguna: no existen specs previas)

## Impact

- Solo documentación: se agregan `openspec/specs/ticket-sale/spec.md` y `openspec/specs/seat-reservation/spec.md` al archivar el change.
- Código relevado (sin cambios):
  - `app/Filament/Resources/Tickets/Schemas/TicketForm.php` y `app/Filament/Resources/Tickets/Pages/CreateTicket.php`
  - `app/Support/SaleCutoff.php`
  - `app/Models/Ticket.php`, `Sale.php`, `Trip.php`, `SeatReservation.php`, `Schedule.php`
  - `app/Http/Controllers/Api/SeatReservationController.php` y `resources/views/tickets/seat-selector.blade.php`
  - `app/Console/Commands/CleanupExpiredReservationsCommand.php`
- Tests de referencia: `TicketSaleCutoffTest`, `TicketSaleCutoffWizardTest`, `SellWithoutDateTest`, `TicketSaleDniValidationTest`, `ReturnTripDifferentBusTest`.
