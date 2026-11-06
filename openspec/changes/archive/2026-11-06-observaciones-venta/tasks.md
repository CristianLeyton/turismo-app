# Tasks

## 1. Dato en el boleto

- [x] 1.1 Crear con `php artisan make:migration add_observation_to_tickets_table --table=tickets --no-interaction` la columna `observation` (`text`, nullable, después de `pet_count`) y su `down()`; verificar con `php artisan migrate` que la columna existe.
- [x] 1.2 En `app/Models/Ticket.php`, sumar `observation` al `$fillable` y un mutator que haga `trim` y convierta el vacío en `null`(no hay `TicketFactory`: los tests crean los boletos a mano, como el resto de la suite); verificar con un test unitario o de feature que `'  '` se guarda como `null` y `' hola '` como `'hola'`.

## 2. Venta: campo en el Resumen y copia a los boletos

- [x] 2.1 Dividir `resources/views/tickets/summary.blade.php` en la parte del viaje y la de pasajeros, y en el `Step::make('Resumen')` de `TicketForm` poner `ViewField` (viaje), `Textarea::make('observation')` (label "Observaciones", `rows(3)`, `maxLength(280)`, contador, helper "Se guarda en todos los boletos de esta venta. Opcional.", mensaje de máximo en español) y `ViewField` (pasajeros); verificar en el navegador que el resumen se ve igual que antes, con el campo en medio.
- [x] 2.2 En `CreateTicket`, validar del lado del servidor que `observation` no supere los 280 caracteres antes de emitir (con el mismo estilo de aviso que los demás rechazos) y pasar `observation` en todos los arrays de creación de boletos: ida, vuelta y pendientes (`createPendingTickets` y las variantes con un tramo sin fecha); verificar con 2.3.
- [x] 2.3 Crear `tests/Feature/TicketObservationSaleTest.php` (`php artisan make:test --phpunit TicketObservationSaleTest`) con: venta sin observación deja `null`, solo espacios deja `null`, 280 caracteres se guarda, 281 se rechaza y no crea venta, ida y vuelta con 2 pasajeros copia la observación a los 4 boletos, vuelta sin fecha y ida sin fecha copian al boleto pendiente; correr `php artisan test --compact tests/Feature/TicketObservationSaleTest.php` y que pase.

## 3. Edición por boleto

- [x] 3.1 En `EditTicketForm`, agregar `Textarea::make('observation')` con las mismas reglas y `fieldLabel('observation') => 'Observación'`. En `EditTicket`, cargarla en el fill, compararla normalizada, sumarla a `$changes` y guardarla solo en el boleto editado (validando 280 del lado del servidor); verificar con 3.2.
- [x] 3.2 Agregar tests (por ejemplo `tests/Feature/TicketObservationEditTest.php`): cambiar la observación de un boleto no cambia la del otro boleto de la venta, vaciarla la deja en `null`, se crea un `TicketEdit` con `field_label` "Observación" y los valores anterior y nuevo, no editarla no crea `TicketEdit` de observación, 281 caracteres se rechaza; correr el archivo y que pase.
- [x] 3.3 Agregar un test de reprogramación: asignarle fecha a un boleto pendiente con observación la conserva y el boleto aparece con ella en `getPassengersWithDetails()` del viaje asignado; correrlo junto con los tests de reprogramación existentes y que pasen.

## 4. Lista de pasajeros del viaje (modal, PDF y Excel)

- [x] 4.1 En `Trip::getPassengersWithDetails()`, agregar `'observation'` a la fila `adult` (las filas `child` y `pet` sin observación); verificar con 4.5.
- [x] 4.2 En `resources/views/filament/trips/trip-details.blade.php`, envolver el `<th>` y el `<td>` de Teléfono en `@if ($showPhone)` (con `false` por defecto) y agregar al final la columna "Observación" (texto `text-sm` con salto de línea, fondo ámbar y borde izquierdo cuando hay observación, "-" gris cuando no, con soporte `dark:`); verificar con 4.5 y en el navegador.
- [x] 4.3 En `resources/views/pdf/trip-details.blade.php`, el mismo flag para Teléfono, los anchos nuevos (7/20/11/8/13/13/28) y la columna "Observación" con fondo `#eeeeee` cuando hay observación y `word-wrap: break-word`; verificar descargando el PDF de un viaje con una observación de 280 caracteres y con 4.5.
- [x] 4.4 En `resources/views/excel/trip-details.blade.php`, sumar "Observaciones" como última columna (Teléfono se mantiene) y pasar el `colspan` vacío a 9. En `TripExcelExport`, ajuste de texto y ancho fijo para esa columna; verificar con 4.5.
- [x] 4.5 Crear `tests/Feature/TripPassengerListObservationTest.php`: el modal muestra "Observación" y no muestra el encabezado "Teléfono", la observación aparece una sola vez aunque el adulto viaje con un menor, un pasajero sin observación muestra "-". El HTML de `pdf.trip-details` no tiene "Teléfono" y sí tiene la observación. El Excel (`Excel::fake()`, o leyendo la vista `excel.trip-details`) tiene "Teléfono" y, al final, "Observaciones" con el texto. Correr el archivo junto con los tests existentes de exportación de viajes y que pasen.

## 5. Detalle del boleto y auditoría

- [x] 5.1 En `resources/views/tickets/infolist-sale-details.blade.php`, agregar el bloque "Observación" a todo el ancho, solo si `filled($record->observation)`, con soporte dark; verificar con 5.3.
- [x] 5.2 En `resources/views/filament/resources/ticket-audit/ticket-audit-detail.blade.php`, agregar el mismo bloque siguiendo el estilo de cajas de la vista; verificar con 5.3.
- [x] 5.3 Agregar tests: el "Ver boleto" de un boleto con observación muestra el texto y el de uno sin observación no muestra "Observación"; el detalle de auditoría lo muestra cuando existe; correrlos y que pasen.

## 6. Observación solo interna

- [x] 6.1 Agregar tests que verifiquen que un boleto con observación no la muestra en el PDF del pasajero (`TicketPdfService`, el HTML de `tickets/pdf/passenger-tickets`), en la página pública del boleto (link con `share_token`) ni en "Mis boletos" del panel de clientes; correrlos y que pasen.

## 7. Cierre

- [x] 7.1 Correr `vendor/bin/pint --dirty --format agent` y `npm run build`; verificar que no quedan cambios de formato y que el build termina sin errores.
- [x] 7.2 Correr todos los tests de este cambio y los vecinos (venta, edición, reprogramación, exportación de viajes, detalle del boleto) con `php artisan test --compact --filter=...` y que pasen; preguntarle al usuario si quiere correr la suite completa.

## 8. Parámetro "Observaciones en la venta" y recuadro redondeado

- [x] 8.1 Agregar el permiso `settings.sale_observations` ("Activar/desactivar observaciones") en `Permissions::catalog()`, sin defaults. Agregar `Setting::OBSERVACIONES_VENTA` y `Setting::observacionesActivas()` (en `false` por defecto). Sumar a `TicketSettings` la sección "Observaciones" en Venta (Toggle `observaciones_activas`, visible con el permiso), la entrada en `TAB_FIELDS`, el valor en `getFormData()` y el guardado protegido. Verificar con 8.4.
- [x] 8.2 Condicionar la funcionalidad al parámetro: el Textarea de la venta y el de la edición con `visible()`. `CreateTicket` guarda `null` con el parámetro apagado. `EditTicket` no toca la observación ni la registra en el historial con el parámetro apagado (tampoco en el resumen de confirmación). Las vistas del modal, el PDF y el Excel reciben `$showObservation` y `$showPhone = ! $showObservation` por defecto, y `TripExcelExport` solo configura la columna I cuando corresponde. El detalle y la auditoría muestran el bloque solo con el parámetro prendido. Verificar con 8.4.
- [x] 8.3 Mostrar la observación en un recuadro redondeado en el modal (`rounded-lg` con borde izquierdo y fondo ámbar, como el detalle) y en el PDF (`div` con `border-radius`, fondo gris y borde izquierdo); verificar con 8.4 y `npm run build`.
- [x] 8.4 Tests: en `TicketSettingsTest`, la sección se ve con el permiso y no sin él, arranca apagada, un pedido manipulado sin el permiso no la cambia, y el permiso no está en los defaults. En los tests de observaciones, prenderla en el `setUp` y agregar los casos con el parámetro apagado: sin campo en la venta, una venta forzada guarda `null`, la edición conserva la observación sin historial, el modal y el PDF muestran Teléfono y no Observación, el Excel no tiene la columna, y el detalle y la auditoría no la muestran. Correr esos archivos y `TicketSettingsTest` y que pasen; después Pint y `npm run build`.

