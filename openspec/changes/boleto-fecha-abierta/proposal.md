# Proposal

## Why

Hoy un boleto puede venderse sin fecha, pero una vez que tiene fecha no hay forma de volverlo a ese estado: si el pasajero no sabe cuándo va a viajar (o perdió el colectivo), el personal solo puede reprogramarlo a otra fecha concreta o eliminarlo. La empresa quiere poder dejar un boleto "con fecha abierta" desde la misma reprogramación, con registro de quién lo hizo y por qué, y que el pasajero elija después la fecha nueva desde su panel de clientes.

## What Changes

- La pantalla "Reprogramar boleto" ofrece la opción "Dejar con fecha abierta" además de elegir una fecha nueva. Solo se muestra a quien tiene `tickets.reschedule` y también `tickets.vender_sin_fecha`. El servidor lo vuelve a validar.
- Dejar con fecha abierta quita el viaje y el asiento del tramo (o de los dos tramos, con el mismo selector de alcance que reprogramar), libera el asiento en el viaje original y conserva el número de boleto, la venta, el precio y el colectivo. Se permite también con viajes que ya salieron.
- Exige que el pasajero esté en el padrón de clientes, igual que la venta sin fecha. Si no está, se rechaza con un mensaje que indica registrarlo desde Clientes.
- Respeta la confirmación por contraseña configurada para reprogramar.
- Admite un motivo opcional en texto libre.
- El registro de reprogramaciones (`ticket_date_changes`) distingue el tipo de cambio: reprogramación o fecha abierta, y guarda el motivo. Las filas existentes quedan como reprogramación.
- El Historial de boletos muestra "Dejó el boleto con fecha abierta", con el viaje y el asiento de origen y el motivo si lo hay.
- Al confirmar, la entrega es la misma que al reprogramar: PDF automático o modal de entrega por link, con el boleto mostrado "sin fecha".
- En el panel de clientes, el boleto vuelve a "Pendientes" y el cliente puede elegir la fecha nueva con las reglas que ya existen (anticipación, días de servicio, orden ida → vuelta, una persona por viaje).

Fuera de alcance: que el cliente deje su boleto con fecha abierta por su cuenta; permisos nuevos.

## Capabilities

### New Capabilities
- `ticket-open-date`: dejar un boleto fechado con fecha abierta desde la reprogramación: quién puede, alcance por tramo, exigencia del padrón, liberación del asiento, motivo, confirmación y entrega.

### Modified Capabilities
- `ticket-audit-actor`: cada cambio de fecha registra su tipo (reprogramación o fecha abierta) y un motivo opcional, y el detalle del Historial lo muestra.
- `client-tickets-panel`: un boleto que el personal dejó con fecha abierta vuelve a "Pendientes" y el cliente puede elegir su fecha otra vez.
- `ticket-delivery-modal`: el modal de entrega de la reprogramación también se usa al dejar con fecha abierta, con un título propio.

## Impact

- `app/Services/TicketRescheduleService.php`: nueva operación de fecha abierta (transacción, lock, permisos, padrón, contraseña, auditoría).
- `app/Filament/Resources/Tickets/Pages/RescheduleTicket.php` y `Schemas/TicketRescheduleForm.php`: la opción, el motivo y el envío.
- `app/Models/TicketDateChange.php` y una migración nueva: columnas `kind` y `reason` en `ticket_date_changes`.
- `resources/views/filament/resources/ticket-audit/ticket-audit-detail.blade.php`: cómo se muestra la fecha abierta en el Historial.
- `app/Support/TicketDelivery.php`: título del modal de entrega para la fecha abierta.
- Panel de clientes: sin cambios de código previstos. `MyTickets` ya toma como pendientes los boletos con `trip_id` NULL.
- Relación con el cambio en curso `reservas-boletos-clientes`: un boleto con fecha abierta no es una reserva y no debe mezclarse con ellas.
