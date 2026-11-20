# Tasks

## 1. Parámetro y permiso

- [x] 1.1 Agregar `Setting::SELECTORES_DETALLADOS` (`sales.selectores_detallados`) y `Setting::selectoresDetallados(): bool` con `getBool(..., false)`. Verificar con un test en `TicketSettingsTest` que, sin configurar, devuelve `false` y que, guardado en `'1'`, devuelve `true`.
- [x] 1.2 Agregar `settings.sale_selectors` con la etiqueta "Configurar selectores de origen y destino" en el módulo `settings` de `Permissions::catalog()`, sin sumarlo a `defaultsFor(ROLE_ADMIN)`. Verificar con un test (en `RolesAndPermissionsTest` o en `TicketSettingsTest`, junto a los de `client_panel` y `appearance`) que después de `syncCatalog` el permiso existe y el rol Administrador no lo tiene.

## 2. Sección en Parámetros > Venta

- [x] 2.1 En `TicketSettings`, agregar `SALE_SELECTORS_PERMISSION` y `canManageSaleSelectors()`, sumar `selectores_detallados` al mapa de pestañas en `'Venta'` y al `fill()` de `mount()`, y agregar en `saleComponents()` la sección "Selectores de origen y destino" con el toggle "Selectores de origen y destino detallados" (una oración de ayuda y el detalle en un tooltip, `default(false)`), visible solo con el permiso. Verificar con tests: con el permiso se ve el toggle apagado por defecto; sin el permiso no se ve la sección.
- [x] 2.2 En `save()`, guardar el parámetro solo si se cumplen `canManageSaleSelectors()` y `array_key_exists('selectores_detallados', $data)`. Verificar con tests: con el permiso, prender y guardar deja `Setting::selectoresDetallados() === true`; sin el permiso, un pedido manipulado que manda `true` guarda los demás parámetros y el valor sigue en `false`.
- [x] 2.3 Correr `php artisan test --compact tests/Feature/TicketSettingsTest.php` y verificar que pasa completo, incluidos los tests de pestañas existentes.

## 3. Selectores de la venta

- [x] 3.1 En `TicketForm`, agregar un helper privado `detailedSelectors(): bool` que lea `Setting::selectoresDetallados()`, y cambiar las firmas a `originOptions($busId, bool $detailed)` y `destinationOptions($busId, $originId, bool $detailed)`, manteniendo la misma consulta (línea, rutas activas, localidades activas, paradas posteriores). En modo detallado la salida queda como hoy. En modo simple: el origen devuelve `[location_id => nombre]` sin número; el destino devuelve una lista plana sin repetir, ordenada por `stop_order` y después por `route_id`. Verificar con los tests de la tarea 3.3.
- [x] 3.2 Configurar los selects `origin_location_id` y `destination_location_id` con `native(! $detailed)` y `searchable($detailed && count(...) > 10)`, pasando el modo a los métodos de opciones. No tocar `afterStateUpdated` ni la preselección del horario. Verificar con un test Livewire del wizard (`CreateTicket`) que, con el parámetro apagado, al elegir colectivo, origen y destino con un único horario, `schedule_id` queda preseleccionado.
- [x] 3.3 Actualizar `tests/Feature/LineaSaleSearchTest.php`. Los tests actuales de origen y destino pasan `detailed: true` y siguen pasando sin cambiar sus aserciones. Agregar casos del modo simple: el origen de la línea sin números e incluyendo la parada del refuerzo; el destino desde Pichanal como lista plana "Salta"/"Orán" sin grupos ni repetidos; y que las claves (`location_id`) de destino son las mismas en los dos modos, comparando las del modo detallado aplanadas. Correr `php artisan test --compact tests/Feature/LineaSaleSearchTest.php` y verificar que pasa.

## 4. Cierre

- [x] 4.1 Correr `vendor/bin/pint --dirty --format agent` y, después, `php artisan test --compact --filter="LineaSaleSearch|TicketSettings|RolesAndPermissions|TicketSale"`, y verificar que todo pasa.
- [x] 4.2 Verificar en el navegador la venta con el parámetro apagado (selects nativos, lista plana) y prendido (buscador, números, grupos "Hacia X"), y que la sección de Parámetros > Venta aparece solo con el permiso.

## Workflow follow-up

- Deploy: `php artisan permissions:sync` y asignar `settings.sale_selectors` a mano desde la matriz de roles a quien corresponda.
- Actualizar la memoria de pendientes de deploy con este cambio.
- Archivar el cambio con `/opsx:archive` una vez revisado.
