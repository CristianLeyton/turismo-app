# Propuesta: días de servicio configurables

## Why

Los días sin servicio están escritos en el código: el asistente de venta deshabilita todos los domingos, salvo el 13/09/2026, que es una excepción fija (hallazgo H8 del baseline). Cada día nuevo con o sin servicio, como un feriado, un domingo especial o un paro, exige un deploy. Además, la regla solo existe en el calendario de la venta: la reprogramación, el panel de clientes y el servidor no la aplican.

## What Changes

- Nueva configuración global de **días de servicio** en Configuración > Parametros:
  - **Días de la semana con servicio** (por defecto: lunes a sábado).
  - **Excepciones por fecha** en ambos sentidos: "con servicio" (habilita una fecha que por día de semana no tiene servicio) o "sin servicio" (la bloquea), con un motivo opcional.
- La regla se aplica a los 6 selectores que asignan fecha de viaje: fecha de ida y de vuelta de la venta, nueva fecha de ida y de vuelta de la reprogramación, fecha del panel de clientes ("asignar fecha") y fecha del formulario de crear viaje.
- La regla también se valida en el servidor al emitir, reprogramar, asignar fecha desde el panel de clientes y crear un viaje. Un día sin servicio se rechaza aunque el pedido no pase por el calendario.
- Nuevo permiso **`tickets.vender_dia_sin_servicio`** ("Vender en días sin servicio"). Permite elegir días sin servicio en la venta, la reprogramación y la creación de viajes. Ningún rol lo trae por defecto; el superadmin lo tiene por el bypass general. **Nunca** aplica al panel de clientes. Con el permiso, el calendario habilita esos días y avisa que la fecha elegida no tiene servicio.
- Configuración inicial equivalente a la actual: lunes a sábado con servicio, domingo sin servicio y la excepción 13/09/2026 con servicio. Nadie nota cambios hasta que se modifique la configuración.
- Se elimina la regla escrita en el código (`TicketForm::getDisabledTravelDates`).
- Los boletos y viajes existentes en días que pasen a no tener servicio siguen siendo válidos: la regla solo se aplica al **elegir** una fecha nueva.
- No cambian los filtros por fecha de los listados, las fechas de pago ni la fecha de venta.

## Capabilities

### New Capabilities

- `service-days`: calendario de días con servicio (días de la semana más excepciones por fecha), su configuración, el permiso para operar en días sin servicio y los puntos donde se aplica.

### Modified Capabilities

- `ticket-sale`: el requisito "Fechas de viaje seleccionables" deja de fijar los domingos y la excepción 13/09/2026, y pasa a depender de `service-days`.

## Impact

- **Código:**
  - `app/Filament/Resources/Tickets/Schemas/TicketForm.php`: selectores de ida y de vuelta, validación del paso 1 y eliminación de `getDisabledTravelDates`.
  - `app/Filament/Resources/Tickets/Pages/CreateTicket.php`: control final al emitir.
  - `app/Filament/Resources/Tickets/Schemas/TicketRescheduleForm.php` y `app/Services/TicketRescheduleService.php`.
  - `app/Filament/Client/Schemas/ClientTicketAssignForm.php` y `app/Services/ClientTicketAssignmentService.php`.
  - `app/Filament/Resources/Trips/TripResource.php`: formulario de crear viaje.
  - `app/Models/Setting.php` y `app/Filament/Pages/TicketSettings.php`.
  - `app/Support/Permissions.php`: permiso nuevo.
  - Clase de soporte nueva en `app/Support/` que concentra la regla, igual que `SaleCutoff`.
- **Datos:** dos claves nuevas en la tabla `settings`. No hay migraciones de esquema.
- **Tests:** nuevos para la regla, la configuración, el permiso y cada punto de control. Se actualizan los tests que dependan de los domingos.
- **Sin cambios** en la API de reservas, los PDFs ni los listados.
