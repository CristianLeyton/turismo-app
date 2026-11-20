# Tasks

## 1. Parámetros, permiso y cálculo

- [x] 1.1 Agregar `settings.fixed_price` ("Configurar precio fijo de los boletos") al catálogo en `app/Support/Permissions.php`, fuera de los defaults de Administrador y Vendedor; verificar con un test en `RolesAndPermissionsTest` que la sincronización crea el permiso y no lo asigna a esos roles
- [x] 1.2 Agregar a `app/Models/Setting.php` las constantes `sales.precio_fijo_activo`, `sales.precio_fijo_boleto`, `sales.precio_fijo_seguro_menor` y sus helpers (default apagado, montos 0 sin configurar); verificar con un test unitario de los defaults y del valor guardado
- [x] 1.3 Crear `app/Support/FixedTicketPrice.php` con `isActive`, `basePrice`, `childInsurance`, `forPassenger` e `insuranceDelta` (design §1); verificar con tests de la fórmula: solo ida, con menor, ida y vuelta con menor, seguro 0 y deltas +/−/0

## 2. Página de Parámetros

- [x] 2.1 Sumar la sección "Precio de los boletos" al final de la pestaña Venta en `TicketSettings` (toggle y montos visibles solo prendido, prefijo `$`, tooltip si hace falta según el skill filament-ui), visible solo con `settings.fixed_price`, y agregar los campos a `TAB_FIELDS['Venta']`; verificar en `TicketSettingsTest` la visibilidad con y sin permiso y el valor inicial
- [x] 2.2 Validar los montos (obligatorios con el toggle prendido, enteros, precio 1–999.999, seguro 0–999.999, mensajes en voseo) y guardar según el permiso (sin permiso no se escribe nada; toggle apagado conserva los montos); verificar en `TicketSettingsTest` todos los escenarios de validación, el pedido manipulado y el guardado sin permiso del spec `settings-page`

## 3. Venta con precio fijo

- [x] 3.1 En `TicketForm`, con el precio fijo prendido, mostrar `price` como solo lectura con el valor calculado, recalcularlo al cambiar `travels_with_child` o `is_round_trip` y al agregar pasajeros, y guardar `fixed_price_snapshot` al montar; verificar con un test Livewire del wizard que el precio se actualiza al marcar el menor y al pasar a ida y vuelta
- [x] 3.2 En `CreateTicket`, antes de crear boletos, comparar el snapshot y los precios con la configuración vigente, rechazar con el aviso y refrescar los precios si difieren, y si coinciden reemplazar el precio de cada pasajero por el calculado en los caminos con fecha y sin fecha/mixtos; verificar en un test nuevo `TicketFixedPriceSaleTest` los escenarios de cálculo (solo ida, menor, ida y vuelta, mascota, vuelta sin fecha, ambos sin fecha), el pedido manipulado y los tres casos de rechazo, y que con el precio fijo apagado el precio a mano se respeta
- [x] 3.3 Correr los tests existentes de venta (`SellWithoutDateTest`, `ReturnLegMarkTest`, `EmissionAtomicityTest`, `TicketSaleCutoffWizardTest`) y verificar que siguen pasando con el precio fijo apagado

## 4. Edición de boletos

- [x] 4.1 En `EditTicketForm`/`EditTicket`, con el precio fijo prendido, bloquear `price` en todos los boletos e ignorarlo en `applyChanges`; verificar en `TicketFixedPriceEditTest` el campo bloqueado y el pedido manipulado
- [x] 4.2 Ajustar el seguro al agregar o quitar el menor: en la ida sobre su propio precio, en la vuelta sobre la ida vigente del pasajero en la venta, rechazando si no existe; registrar el `TicketEdit` de precio en la ida con el motivo (y la aclaración si viene de la vuelta) y mostrar "Precio: $X → $Y" en el resumen; verificar en `TicketFixedPriceEditTest` los escenarios del spec (ida, vuelta, quitar, seguro cambiado, seguro 0, ida eliminada, historial, resumen y precio fijo apagado)
- [x] 4.3 Recalcular el total de la venta cuando la edición cambia algún precio, manual o por ajuste; verificar con tests del total en edición manual (precio fijo apagado) y por ajuste del seguro, y correr `TicketEditTest` y `CompanionParamEditTest` para confirmar que siguen pasando

## 5. Cierre

- [x] 5.1 Correr `vendor/bin/pint --dirty --format agent` y verificar que no quedan cambios de formato
- [x] 5.2 Correr `openspec validate precio-fijo-boletos --strict` y verificar que pasa sin errores ni advertencias

## Workflow follow-up

- En el deploy: correr `php artisan permissions:sync` y asignar `settings.fixed_price` a quien corresponda.
- Archivar el cambio después de revisarlo.
