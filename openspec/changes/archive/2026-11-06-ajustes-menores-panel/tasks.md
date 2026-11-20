# Tasks

## 1. PDF: etiqueta "VUELTA"

- [x] 1.1 En `resources/views/tickets/pdf/passenger-tickets.blade.php`, dejar `$legLabel = 'VUELTA'` para la vuelta (sin `roundTripOutboundTicket()`) y actualizar el comentario; verificar que el PDF de un diferido muestra "IDA" y "VUELTA" sin "VUELTA de N°".
- [x] 1.2 Actualizar `tests/Feature/PendingTicketPdfTest.php`: las aserciones de etiqueta esperan "VUELTA" y no "VUELTA de N°{id}", y el test de orden de hojas compara posiciones con otro marcador único por boleto; correr `php artisan test --compact tests/Feature/PendingTicketPdfTest.php tests/Feature/TicketLegDisplayTest.php` y que pasen.

## 2. Viajes: switch "solo hoy"

- [x] 2.1 En `TripResource`, agregar `Filter::make('only_today_trips')` con `Toggle` "Mostrar solo los viajes que parten hoy" (default apagado), `whereDate('trip_date', today())`, salteado cuando hay `trip_id` buscado, ubicado al lado de "Ver también viajes sin pasajeros"; verificar con los tests de 2.2.
- [x] 2.2 Agregar tests en `tests/Feature/TripListEmptyTripsTest.php` (o un test hermano) para: apagado por defecto muestra todas las fechas, encendido solo hoy (incluido un horario ya pasado), combinado con viajes sin pasajeros, búsqueda por Nº lo saltea, apagarlo vuelve a mostrar todo; correr el archivo y que pase.

## 3. Clientes: banear con motivo

- [x] 3.1 En `ClientsResource::toggleBanAction()`, agregar el campo "Motivo del baneo" obligatorio (solo al banear, precargado con `comments`, mensaje "Ingresá el motivo del baneo.") y guardarlo en `comments`; "Habilitar" sigue con confirmación simple y no toca `comments`; verificar con 3.3.
- [x] 3.2 Reemplazar las copias inline de escritorio de `toggleBan` y `resetPassword` por llamadas a los builders con `->button()->hiddenLabel()->extraAttributes([...])`; verificar que ambas variantes (menú celular y escritorio) siguen apareciendo con el test de 3.3.
- [x] 3.3 Crear tests en `tests/Feature/` (por ejemplo `ClientBanTest`) para: banear con motivo guarda `can_buy = false` y `comments`, banear sin motivo da error y no banea, el motivo viene precargado, habilitar no pide motivo y conserva `comments`, sin permiso `clients.ban` no se ve; correrlos y que pasen.

## 4. Clientes: resetear contraseña

- [x] 4.1 En `resetPasswordAction()`, condicionar `visible` a `Setting::panelClientesActivo()` además del permiso, y reescribir los textos del modal y la notificación con caracteres reales y voseo (design.md, decisión 5); verificar con 4.2.
- [x] 4.2 Agregar tests: con el panel apagado la acción está oculta, con el panel encendido se ve y resetea la contraseña al DNI con `must_change_password = true`, y la descripción del modal y la notificación no contienen `\u`; correrlos y que pasen.

## 5. Usuarios: primer administrador

- [x] 5.1 Agregar `User::FIRST_ADMIN_ID` y `User::isFirstAdmin()`, y en `UserPolicy` hacer que `delete`/`forceDelete` devuelvan `false` para él y que `update` exija administrador; verificar con 5.3.
- [x] 5.2 En `UserResource`, reemplazar los `id === 2`: Edit y Restablecer contraseña habilitados solo para administrador en esa fila, Eliminar y Eliminar permanentemente siempre deshabilitados, y el campo `roles` deshabilitado al editarlo; aplicar en las dos variantes (menú celular y escritorio); verificar con 5.3.
- [x] 5.3 Agregar tests en `tests/Feature/UserResourceTest.php`: el admin edita el nombre del primer admin y los roles no cambian, un admin no ve editar deshabilitado, Delete/ForceDelete deshabilitados para el admin, la policy rechaza borrarlo (`Gate::forUser(...)->denies('delete', $firstAdmin)`); correr el archivo y que pase.

## 6. "Copiar link" con el color de la empresa

- [x] 6.1 Cambiar `color="info"` por `color="primary"` (manteniendo `outlined`) en `delivery-modal.blade.php` y en los dos botones de `infolist-pdf-preview.blade.php`; verificar con un test que renderiza el modal o la vista previa y no encuentra el botón con color `info`, y correr `BrandColorUsageTest`.

## 7. Cierre

- [x] 7.1 Correr `vendor/bin/pint --dirty --format agent` y los tests afectados (`--filter` de los archivos tocados); todos verdes.
- [x] 7.2 Revisar en el navegador la lista de viajes (dos switches lado a lado y apilados en celular) y el color de "Copiar link"; avisar al usuario que corra `npm run build` si no ve los cambios.
