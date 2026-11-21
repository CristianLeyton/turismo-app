# Tareas

## 1. Períodos y cálculo

- [x] 1.1 Agregar a `app/Models/Setting.php` la constante `sales.precio_fijo_periodos` y `precioFijoPeriodos()`, que decodifica, descarta filas inválidas y ordena por `desde` (design §1). Eliminar las constantes y los helpers de `PRECIO_FIJO_BOLETO` y `PRECIO_FIJO_SEGURO_MENOR`. Verificar con un test de la lista vacía sin configurar, el orden y las filas inválidas descartadas.
- [x] 1.2 En `FixedTicketPrice`, agregar `periodFor()` y `hasCurrentPeriod()`, hacer que `basePrice()` y `childInsurance()` usen el período de hoy, y que `insuranceDelta()` reciba la fecha de la venta, con el período más antiguo como respaldo (design §2 y §3). Verificar en `FixedTicketPriceTest` con `travelTo`: período vigente, primer día del período siguiente, después del último, sin período vigente, seguro de una venta anterior al primer período y fotografía igual o distinta al cruzar la medianoche.
- [x] 1.3 Agregar en los tests un helper que cargue un período desde hoy con montos dados, y migrar a ese helper los usos de `PRECIO_FIJO_BOLETO` y `PRECIO_FIJO_SEGURO_MENOR` en `TicketFixedPriceSaleTest`, `TicketFixedPriceEditTest`, `FixedTicketPriceTest` y `TicketSettingsTest`. Verificar que esos archivos pasan sin cambiar sus escenarios.

## 2. Migración de datos

- [x] 2.1 Crear con `php artisan make:migration convert_fixed_price_to_periods` la migración que convierte los montos actuales en un período desde hoy, solo si hay precio cargado y no existen períodos. Las claves viejas se conservan y `down()` borra los períodos (design §6). Verificar con un test que cubra los dos escenarios de "Migración del precio fijo a períodos" y que la venta cobre lo mismo después de migrar.

## 3. Página de Parámetros

- [x] 3.1 En `TicketSettings`, reemplazar los dos campos de monto por el `Repeater` "Períodos de precio" (Desde, Precio del boleto, Seguro para menores, `itemLabel` con "Hasta" y "Vigente"), siguiendo el skill filament-ui, y actualizar `TAB_FIELDS['Venta']` y la carga inicial (design §5). Verificar en `TicketSettingsTest` los escenarios "Valor inicial", "Hasta calculado", "Período vigente marcado" y la visibilidad con y sin permiso.
- [x] 3.2 Validar los períodos (obligatorios con el interruptor prendido, `Desde` sin repetir, montos enteros en rango) y la cobertura de hoy en `save()`. Guardar ordenado, sin depurar los pasados, respetando el permiso y el interruptor apagado. Verificar en `TicketSettingsTest` todos los escenarios de "Validación de los montos del precio fijo", "Cobertura del día de hoy", "Configurar el precio fijo de los boletos" ("Prender", "Orden al guardar", "Apagar conserva los montos") y "Permiso para configurar el precio fijo".

## 4. Venta y edición

- [x] 4.1 En `CreateTicket::applyFixedPrice`, rechazar la emisión con el aviso "No hay un precio vigente para hoy…" si el precio fijo está prendido y no hay un período vigente (design §4). Verificar en `TicketFixedPriceSaleTest` los escenarios de "Precio vigente según la fecha de venta" (período, día siguiente, después del último, viaje en otro período, vuelta sin fecha), "Venta sin período vigente" y los dos de cruce de medianoche de "Rechazo si cambió el precio durante la venta".
- [x] 4.2 En `EditTicket::fixedPriceAdjustment`, pasar la fecha de la venta del boleto a `insuranceDelta()` (design §3). Verificar en `TicketFixedPriceEditTest` los escenarios "Seguro cambiado desde la venta", "Seguro cambiado en el mismo período" y "Venta anterior al primer período", y que los demás escenarios del ajuste siguen pasando.
- [x] 4.3 Verificar en un test que reprogramar un boleto a una fecha de otro período no le cambia el precio, y correr `TicketRescheduleServiceTest`, `RescheduleTicketPageTest`, `SellWithoutDateTest` y `TicketEditTest` para confirmar que siguen pasando.

## 5. Cierre

- [x] 5.1 Correr `vendor/bin/pint --dirty --format agent` y verificar que no quedan cambios de formato.
- [x] 5.2 Correr `openspec validate precios-por-periodo --strict` y verificar que pasa sin errores ni advertencias.
