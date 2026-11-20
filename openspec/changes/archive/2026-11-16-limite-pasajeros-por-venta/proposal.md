# Propuesta

## Por qué

La venta del mostrador permite como máximo 4 pasajeros por venta, y ese número está fijo en el código: el selector "Cantidad de pasajeros", su regla de validación y su mensaje de error. La empresa necesita ajustarlo sin pedir un cambio de código, y la próxima función de reservas de clientes va a usar el mismo límite por transacción. Por eso conviene tenerlo como parámetro, probado y funcionando, antes de construir las reservas.

## Qué cambia

- Nuevo parámetro "Pasajeros por venta", en una sección propia de la pestaña Venta de Parámetros: un entero de 1 a 8, con 4 por defecto, que es el valor de hoy. Sin configurar, la venta se comporta exactamente como ahora.
- El selector "Cantidad de pasajeros" de la venta ofrece de 1 al valor configurado. La validación del servidor y el mensaje ("La cantidad de pasajeros debe ser entre 1 y N") usan el mismo valor.
- Si el límite baja mientras un vendedor tiene una venta a medio hacer con más pasajeros, la emisión se rechaza con ese mensaje y no se crea nada.
- Nuevo permiso "Configurar límite de pasajeros por venta" (`settings.passenger_limit`) en el módulo Configuración. Ningún rol lo recibe por defecto. Solo quien lo tiene ve la sección y puede cambiar el parámetro. Sin el permiso, guardar Parámetros conserva el valor guardado aunque el pedido traiga otro.
- Se modifica el requisito de ventas: hoy dice "entre 1 y 4". Pasa a decir "entre 1 y el límite configurado".

## Capacidades

### Capacidades nuevas
- (ninguna)

### Capacidades modificadas
- `ticket-sale`: la cantidad de pasajeros por venta deja de ser fija (1 a 4) y pasa a ir de 1 al límite configurado, con el mensaje de rechazo según ese límite.
- `settings-page`: nueva sección "Pasajeros por venta" en la pestaña Venta, con su validación, su valor por defecto y su permiso propio. También se suma esa sección al orden de la pestaña Venta.

## Impacto

- `app/Models/Setting.php`: nueva clave, valor por defecto, máximo y getter.
- `app/Support/Permissions.php`: nuevo permiso en el catálogo del módulo `settings`, sin agregarlo a los permisos por defecto de ningún rol.
- `app/Filament/Pages/TicketSettings.php`: sección, carga inicial, verificación del permiso y guardado condicionado al permiso.
- `app/Filament/Resources/Tickets/Schemas/TicketForm.php`: el selector `passengers_count`, con opciones, regla `in` y mensaje dinámicos.
- Tests: `TicketSettingsTest`, `RolesAndPermissionsTest` y un test de la venta que cubra el límite.
- Deploy: correr `php artisan permissions:sync` para crear el permiso nuevo. No hace falta ninguna migración, porque los parámetros viven en la tabla `settings`.
