# Proposal

## Why

Con el precio fijo prendido, nadie puede corregir el precio de un boleto vendido, ni con todos los permisos: solo se editan los vendidos a mano. Cuando hay que hacer una excepción (un descuento acordado, un error de cobro), la empresa no tiene forma de cargarla sin apagar el precio fijo para todos. Tiene que salir en `desarrollo`, antes del cambio `precio-por-boleto`.

## What Changes

- Permiso nuevo **"Editar precio fijo"** (`tickets.edit_fixed_price`) en el módulo Boletos. No viene por defecto en ningún rol: lo asigna el Super Administrador desde la matriz.
- Con el precio fijo prendido, quien tiene el permiso puede editar el precio de un boleto "fijo" o sin modo de precio, igual que uno vendido a mano. Para quien tiene el permiso, el campo muestra el aviso "Precio fijo. Si lo cambiás, el boleto pasa a precio manual."
- Si al editar se guarda un precio distinto del que tenía, el boleto de ida queda registrado como "manual", con o sin precio fijo prendido. Guardar el mismo precio o cambiar otros campos no cambia el modo. Desde ese momento el precio sigue los mismos pasos que un boleto vendido a mano: lo edita cualquiera con permiso de edición y agregar o quitar el menor no lo cambia solo.
- Si en la misma edición se cambia el precio a mano y el menor, gana el precio cargado: no se suma ni se resta el seguro automático.
- Sin cambios en el historial ni en Ventas: el cambio de precio ya queda en el "Historial de ediciones" con el motivo, y Ventas toma el precio nuevo en el día de la venta original y para el mismo vendedor, como cualquier edición de precio hoy.
- La vuelta de un ida y vuelta sigue sin precio propio: el precio se edita desde el boleto de ida.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `ticket-fixed-price`: la regla "nadie puede cambiar el precio con el precio fijo prendido" pasa a tener la excepción del permiso nuevo; editar el precio a mano pasa el boleto a "manual"; el precio editado a mano gana sobre el ajuste del seguro.
- `role-permissions`: el permiso nuevo no viene por defecto en ningún rol del sistema.

## Impact

- `app/Support/Permissions.php`: acción nueva en el catálogo de Boletos.
- `app/Filament/Resources/Tickets/Pages/EditTicket.php`: `isPriceLockedByFixedPrice()` considera el permiso; el guardado pasa la ida a "manual" y saltea el ajuste del seguro si cambió el precio a mano; el resumen de confirmación muestra un solo cambio de precio.
- `app/Filament/Resources/Tickets/Pages/EditTicket/EditTicketForm.php`: aviso del campo "Precio" para quien tiene el permiso.
- Tests: `tests/Feature/TicketFixedPriceEditTest.php` y los de permisos por defecto.
- Sin migraciones. Deploy: `php artisan permissions:sync` y asignar el permiso a mano.
- Al mergear `precio-por-boleto`, que ya pasa a "manual" el pasaje editado, habrá que conservar la excepción del permiso en su versión de la regla de bloqueo.
