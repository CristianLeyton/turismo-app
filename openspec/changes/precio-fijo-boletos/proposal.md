# Proposal

## Why

Hoy el vendedor carga a mano el precio de cada boleto, lo que permite errores y precios distintos para el mismo viaje. La empresa cobra una tarifa única por tramo más un seguro cuando el pasajero viaja con un menor, así que el precio se puede calcular solo, a partir de parámetros.

## What Changes

- **Parámetro "Precio de los boletos"** (Parámetros > Venta): interruptor "Precio fijo activo" (arranca apagado), "Precio del boleto" y "Seguro para menores". Solo lo ve y lo cambia quien tiene el permiso nuevo `settings.fixed_price`, que no se asigna por defecto a ningún rol. Para prenderlo, los dos montos son obligatorios, en pesos enteros: el precio entre 1 y 999.999 y el seguro entre 0 y 999.999 (0 = no se cobra seguro). Al apagarlo los montos se conservan.
- **Venta con precio fijo**: el campo "Precio" de cada pasajero se muestra calculado y no se puede editar. Precio = (precio del boleto + seguro si viaja con menor) × 2 si es ida y vuelta. El monto completo queda en el boleto de ida y la vuelta sigue en 0, haya fecha de vuelta o no. El servidor recalcula el precio al emitir y no confía en el valor del formulario.
- **Cambio de parámetros con la venta abierta**: si al emitir el precio calculado o el estado del parámetro no coinciden con lo que vio el vendedor, la venta se rechaza con un aviso para que la revise. Nunca se cobra un precio distinto del mostrado.
- **Editar boleto con precio fijo**: el precio no se puede editar. Agregar el menor en un tramo suma un seguro y quitarlo lo resta, con el monto vigente y siempre sobre el boleto de ida de ese pasajero, aunque se edite la vuelta. El ajuste queda en el "Historial de ediciones" del boleto de ida.
- **Total de la venta**: al editar el precio de un boleto (a mano o por el ajuste del seguro), se recalcula el total de la venta. Hoy no se recalcula.
- **Precio fijo apagado**: todo funciona como hoy, con el precio cargado a mano.
- Los boletos ya vendidos no se recalculan al cambiar los parámetros. Las mascotas no suman al precio.

## Capabilities

### New Capabilities
- `ticket-fixed-price`: cálculo del precio del boleto por parámetro: fórmula, ida y vuelta, seguro del menor, protección ante cambios de parámetros durante la venta y ajuste del seguro al editar un boleto.

### Modified Capabilities
- `settings-page`: la pestaña Venta suma, con su permiso, la sección "Precio de los boletos", con sus validaciones.
- `ticket-sale`: el precio por pasajero se carga a mano solo con el precio fijo apagado; editar el precio de un boleto recalcula el total de la venta.

## Impact

- Parámetros: `app/Models/Setting.php` (constantes y helpers), `app/Filament/Pages/TicketSettings.php` (sección, `TAB_FIELDS`, validación y guardado protegido por permiso), `app/Support/Permissions.php` (permiso nuevo; requiere `php artisan permissions:sync`).
- Venta: `app/Filament/Resources/Tickets/Schemas/TicketForm.php` (campo Precio calculado y no editable) y `app/Filament/Resources/Tickets/Pages/CreateTicket.php` (cálculo en el servidor y rechazo si cambió la configuración, en la venta con y sin fecha).
- Edición: `app/Filament/Resources/Tickets/Pages/EditTicket.php` y `EditTicket/EditTicketForm.php` (precio bloqueado, ajuste del seguro en el boleto de ida, historial y recálculo del total).
- Lógica nueva de cálculo en `app/Support/` (clase de soporte reutilizada por la venta y la edición).
- Sin migraciones ni dependencias nuevas. Tests en `tests/Feature/`.
