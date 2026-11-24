# Tasks

## 1. Datos

- [x] 1.1 Crear con `php artisan make:migration` la migración que agrega `tickets.price_mode` (`string(10)`, `nullable`) y `tickets.child_insurance` (`unsignedInteger`, `nullable`), con `down()` que las elimina. Verificar con `php artisan migrate` y `migrate:rollback` en local.
- [x] 1.2 En `Ticket`: agregar las constantes `PRICE_MODE_FIXED` y `PRICE_MODE_MANUAL`, las columnas en `$fillable`, el cast `child_insurance` a `integer` y el helper `isPricedManually()` (lee la ida, o el propio boleto si es ida). Test: un boleto creado antes del cambio queda con las dos columnas en `null` y su `price` intacto; `isPricedManually()` responde bien para ida, vuelta y boleto sin modo.

## 2. Registro en la venta

- [x] 2.1 Agregar `FixedTicketPrice::outboundPricingAttributes()` (`fixed` + seguro vigente, o `manual` + `null`) con test unitario en `FixedTicketPriceTest`.
- [x] 2.2 En `CreateTicket`, sumar esos atributos a los tres arrays de ida (normal `:660`, ida sin fecha `:987`, ida con fecha en modo mixto `:1055`), calculados una vez después de `applyFixedPrice()`. No tocar los de vuelta. Tests en `TicketFixedPriceSaleTest`: solo ida, ida y vuelta, vuelta sin fecha, ida sin fecha y ambos sin fecha, con la llave prendida (ida `fixed` + seguro, vuelta `null`) y apagada (ida `manual` + `null`). Incluir un pasajero sin menor, que también registra el seguro.
- [x] 2.3 Test: con un boleto `fixed` registrado, cambiar el seguro del período o borrarlo no cambia su `child_insurance`.

## 3. Edición del menor y del precio

- [x] 3.1 Reordenar `EditTicket::fixedPriceAdjustment()` según design.md (decisión 3): resolver la ida, y después el monto según `price_mode` (`fixed`, el seguro registrado; `manual`, sin ajuste; `null`, la grilla como hoy). Tests en `TicketFixedPriceEditTest` con todos los escenarios de "Ajuste del seguro al editar el menor": ida, vuelta, quitar, seguro de la grilla cambiado, período borrado, `manual` en la ida y desde la vuelta, y los escenarios de boletos viejos que ya existían, adaptados a `price_mode` en `null`.
- [x] 3.2 Precio editable en boletos `manual` con la llave prendida: en el `disabled` de `EditTicketForm.php:209` y en la lectura del precio enviado (`EditTicket.php:292` y `:539`), usar `isActive() && ! isPricedManually()`. Tests: `manual` editable y registrado en el "Historial de ediciones"; `fixed` y `null` siguen bloqueados e ignoran un precio manipulado; la vuelta con precio compartido sigue bloqueada.
- [x] 3.3 Verificar que "Vuelta sin boleto de ida activo" sigue rechazando la edición con el mismo aviso (test existente en verde).

## 4. Grilla de períodos

- [x] 4.1 Antes de escribir código, buscar en la documentación de Filament 4 `Repeater::deleteAction()` (confirmación y `arguments['item']`) y cómo probarlo con `TestAction`.
- [x] 4.2 Agregar `FixedTicketPrice::legacyTicketsDependingOn($from, $until)` según design.md (decisión 6). Tests: cuenta los viejos por viajar; ignora los ya viajados solo ida, los eliminados y los que tienen modo; cuenta la ida viajada con vuelta por viajar y los boletos sin fecha; el período más antiguo incluye ventas anteriores; boletos sin venta usan su `created_at`.
- [x] 4.3 En `TicketSettings`, agregar `deleteAction` con `requiresConfirmation()` en `precio_fijo_periodos`. Mostrar la descripción simple, o el aviso con la cantidad cuando el helper devuelve más de 0, con el rango calculado desde el estado actual de la grilla. Tests en `TicketSettingsTest`: confirmar quita la fila, cancelar la deja, el aviso aparece solo con boletos viejos por viajar, y se puede borrar un período pasado y guardar.
- [x] 4.4 Actualizar el `hintIcon` de la grilla (`TicketSettings.php:450`) para que diga que los boletos guardan su seguro y solo los vendidos antes del cambio dependen de la grilla. Seguir el skill `filament-ui` para los textos del modal y del aviso. Verificar que el texto se ve en la página.

## 5. Cierre

- [x] 5.1 Correr `vendor/bin/pint --dirty --format agent` y los tests de `FixedTicketPriceTest`, `TicketFixedPriceSaleTest`, `TicketFixedPriceEditTest`, `TicketSettingsTest` y `TicketEditTest`, todos en verde.
- [x] 5.2 Revisar a mano en el panel: vender con la llave prendida y apagada, editar el menor de cada tipo de boleto y borrar un período con y sin aviso.
