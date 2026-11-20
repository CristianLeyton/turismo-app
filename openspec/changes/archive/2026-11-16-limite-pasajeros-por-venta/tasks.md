# Tareas

## 1. Parámetro y permiso

- [x] 1.1 Agregar a `app/Models/Setting.php` las constantes `LIMITE_PASAJEROS_VENTA` (`sales.limite_pasajeros`), `LIMITE_PASAJEROS_VENTA_DEFAULT = 4` y `LIMITE_PASAJEROS_VENTA_MAX = 8`, y el getter `limitePasajerosVenta()`, acotado a [1, 8], que devuelve 4 si no hay valor o no es numérico. Verificar con un test que cubra: sin configurar → 4; guardado 6 → 6; guardado 0, 50 o texto → valor acotado o el valor por defecto.
- [x] 1.2 Agregar `'passenger_limit' => 'Configurar límite de pasajeros por venta'` al módulo `settings` de `app/Support/Permissions.php`, sin sumarlo a `defaultsFor()`. Verificar en `RolesAndPermissionsTest` que, después de `permissions:sync`, el permiso existe y ni Administrador ni Vendedor lo tienen.

## 2. Parámetros > Venta

- [x] 2.1 En `app/Filament/Pages/TicketSettings.php`: la constante `PASSENGER_LIMIT_PERMISSION`, `canManagePassengerLimit()`, la sección "Pasajeros por venta" después de "Venta fuera de término" con el campo "Máximo de pasajeros por venta" (entero 1..8, obligatorio, mensajes en voseo) visible solo con el permiso, la carga inicial con `Setting::limitePasajerosVenta()` y el guardado solo con el permiso y si el campo llegó en el estado. Verificar con tests en `TicketSettingsTest`, siguiendo los del precio fijo: con el permiso ve la sección con 4; guardar 6 lo persiste; 0, 9, 2,5 y vacío no guardan nada y muestran el error; sin el permiso no ve la sección; guardar sin el permiso conserva el valor; un pedido manipulado sin el permiso no lo cambia.
- [x] 2.2 Verificar que "Precio de los boletos" sigue al final de la pestaña Venta cuando el usuario tiene los dos permisos (test de orden de secciones o aserción sobre el orden).

## 3. Venta del mostrador

- [x] 3.1 En `app/Filament/Resources/Tickets/Schemas/TicketForm.php`, que el `Select` `passengers_count` arme sus opciones, su regla y su mensaje ("La cantidad de pasajeros debe ser entre 1 y N") a partir de `Setting::limitePasajerosVenta()`, evaluados en cada render y validación. Verificar con tests de la venta: sin configurar, las opciones van de 1 a 4 y una cantidad de 5 se rechaza con "entre 1 y 4"; con 6, se emite una venta de 6 pasajeros; con 2, una venta de 3 se rechaza con "entre 1 y 2" sin crear boletos; si el límite baja de 4 a 3 con la venta armada, la emisión se rechaza con "entre 1 y 3".
- [x] 3.2 Correr los tests existentes que usan `passengers_count` (`TicketFixedPriceSaleTest`, `SellWithoutDateTest`, `EmissionAtomicityTest`, `SeatReservationWizardTest`, `TicketSaleDniValidationTest` y los demás de la lista) y verificar que pasan sin cambios.

## 4. Cierre

- [x] 4.1 Correr `vendor/bin/pint --dirty --format agent` y los tests de los archivos tocados con `php artisan test --compact` y verificar que todo pasa. Después, preguntarle al usuario si quiere correr la suite completa.
- [x] 4.2 Actualizar la memoria de pendientes con el paso de deploy `php artisan permissions:sync` y verificar que quedó anotado.

## Workflow follow-up

- Archivar el cambio con `/opsx:archive` una vez revisado y probado.
